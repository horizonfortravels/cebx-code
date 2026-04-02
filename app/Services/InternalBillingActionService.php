<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\BillingWallet;
use App\Models\Shipment;
use App\Models\User;
use App\Models\WalletHold;
use Illuminate\Support\Facades\DB;

class InternalBillingActionService
{
    /**
     * @var array<int, string>
     */
    private const RECOVERY_SHIPMENT_STATUSES = [
        Shipment::STATUS_DRAFT,
        Shipment::STATUS_VALIDATED,
        Shipment::STATUS_KYC_BLOCKED,
        Shipment::STATUS_READY_FOR_RATES,
        Shipment::STATUS_RATED,
        Shipment::STATUS_OFFER_SELECTED,
        Shipment::STATUS_DECLARATION_REQUIRED,
        Shipment::STATUS_DECLARATION_COMPLETE,
        Shipment::STATUS_REQUIRES_ACTION,
        Shipment::STATUS_PAYMENT_PENDING,
        Shipment::STATUS_CANCELLED,
        Shipment::STATUS_FAILED,
    ];

    /**
     * @var array<int, string>
     */
    private const LOCKED_SHIPMENT_STATUSES = [
        Shipment::STATUS_PURCHASED,
        Shipment::STATUS_READY_FOR_PICKUP,
        Shipment::STATUS_PICKED_UP,
        Shipment::STATUS_IN_TRANSIT,
        Shipment::STATUS_OUT_FOR_DELIVERY,
        Shipment::STATUS_DELIVERED,
        Shipment::STATUS_RETURNED,
    ];

    public function __construct(
        private readonly BillingWalletService $billingWalletService,
        private readonly AuditService $auditService,
    ) {}

    /**
     * @return array{
     *   is_releasable: bool,
     *   is_active: bool,
     *   stale_flags: array<int, string>,
     *   headline: string,
     *   detail: string
     * }
     */
    public function staleReleaseSummary(WalletHold $hold, ?Shipment $shipment = null): array
    {
        $isExpired = $hold->expires_at?->isPast() ?? false;
        $shipmentMissing = ! $shipment instanceof Shipment;
        $shipmentStatus = (string) ($shipment?->status ?? '');
        $shipmentRecoverable = $shipment instanceof Shipment
            && in_array($shipmentStatus, self::RECOVERY_SHIPMENT_STATUSES, true);
        $shipmentLocked = $shipment instanceof Shipment
            && in_array($shipmentStatus, self::LOCKED_SHIPMENT_STATUSES, true);

        $staleFlags = [];

        if ($isExpired) {
            $staleFlags[] = 'expired_reservation';
        }

        if ($shipmentMissing) {
            $staleFlags[] = 'missing_shipment_context';
        }

        if ($shipmentRecoverable) {
            $staleFlags[] = 'shipment_needs_recovery';
        }

        if ($shipmentLocked) {
            $staleFlags[] = 'shipment_already_in_fulfillment';
        }

        $isReleasable = $hold->isActive()
            && ! $shipmentLocked
            && ($isExpired || $shipmentMissing || $shipmentRecoverable);

        $headline = $isReleasable
            ? 'Eligible for supervised stale-hold release'
            : 'Stale-hold release is not available';

        $detail = match (true) {
            ! $hold->isActive() => 'Only active reservations can be released from the internal billing center.',
            $shipmentLocked => 'This shipment has already progressed into fulfillment, so the reservation must stay managed by the normal shipment finance flow.',
            $isExpired => 'This reservation has expired and can be released safely with an internal operator reason.',
            $shipmentMissing => 'The linked shipment can no longer be resolved, so the reservation can be cleared with an internal operator reason.',
            $shipmentRecoverable => 'The linked shipment is already in a recovery state, so the stale reservation can be released with an internal operator reason.',
            default => 'Release becomes available only for expired reservations or shipments that are already in a recovery state.',
        };

        return [
            'is_releasable' => $isReleasable,
            'is_active' => $hold->isActive(),
            'stale_flags' => $staleFlags,
            'headline' => $headline,
            'detail' => $detail,
        ];
    }

    public function releaseStaleHold(Account $account, WalletHold $hold, User $actor, string $reason): WalletHold
    {
        if (! $actor->hasPermission('wallet.configure')) {
            throw BusinessException::permissionDenied();
        }

        $wallet = BillingWallet::query()
            ->withoutGlobalScopes()
            ->where('id', (string) $hold->wallet_id)
            ->where('account_id', (string) $account->id)
            ->firstOrFail();

        $shipment = $this->resolveShipment($hold);
        $reason = trim(preg_replace('/\s+/u', ' ', $reason) ?? '');

        if ($reason === '') {
            throw new BusinessException(
                'A clear operator reason is required before releasing a stale reservation.',
                'ERR_BILLING_REASON_REQUIRED',
                422
            );
        }

        $releaseSummary = $this->staleReleaseSummary($hold, $shipment);

        if (! $releaseSummary['is_releasable']) {
            throw new BusinessException(
                $releaseSummary['detail'],
                'ERR_BILLING_STALE_HOLD_RELEASE_UNAVAILABLE',
                422
            );
        }

        return DB::transaction(function () use ($account, $wallet, $hold, $shipment, $actor, $reason, $releaseSummary): WalletHold {
            $oldValues = [
                'hold_status' => (string) $hold->status,
                'wallet_reserved_balance' => (float) $wallet->reserved_balance,
                'shipment_status' => (string) ($shipment?->status ?? ''),
                'shipment_balance_reservation_id' => (string) ($shipment?->balance_reservation_id ?? ''),
                'shipment_reserved_amount' => $shipment?->reserved_amount !== null ? (float) $shipment->reserved_amount : null,
            ];

            try {
                $releasedHold = $this->billingWalletService->releaseHold((string) $hold->id);
            } catch (\RuntimeException $exception) {
                throw new BusinessException(
                    'The billing reservation could not be released safely in its current state.',
                    'ERR_BILLING_STALE_HOLD_RELEASE_FAILED',
                    422
                );
            }

            $shipmentLinkCleared = false;

            if ($shipment instanceof Shipment && (string) ($shipment->balance_reservation_id ?? '') === (string) $hold->id) {
                $shipment->forceFill([
                    'balance_reservation_id' => null,
                    'reserved_amount' => null,
                ])->save();

                $shipmentLinkCleared = true;
            }

            $wallet->refresh();
            $releasedHold->refresh();
            $shipment = $shipment?->fresh();

            $this->auditService->info(
                (string) $account->id,
                (string) $actor->id,
                'billing.hold_released',
                AuditLog::CATEGORY_FINANCIAL,
                'WalletHold',
                (string) $releasedHold->id,
                $oldValues,
                [
                    'hold_status' => (string) $releasedHold->status,
                    'wallet_reserved_balance' => (float) $wallet->reserved_balance,
                    'shipment_status' => (string) ($shipment?->status ?? ''),
                    'shipment_balance_reservation_id' => (string) ($shipment?->balance_reservation_id ?? ''),
                    'shipment_reserved_amount' => $shipment?->reserved_amount !== null
                        ? (float) $shipment->reserved_amount
                        : null,
                ],
                [
                    'reason' => $reason,
                    'wallet_id' => (string) $wallet->id,
                    'shipment_id' => (string) ($hold->shipment_id ?? ''),
                    'shipment_link_cleared' => $shipmentLinkCleared,
                    'release_scope' => 'internal_billing_actions',
                    'stale_flags' => $releaseSummary['stale_flags'],
                ]
            );

            return $releasedHold;
        });
    }

    private function resolveShipment(WalletHold $hold): ?Shipment
    {
        $shipmentId = trim((string) ($hold->shipment_id ?? ''));

        if ($shipmentId === '') {
            return null;
        }

        return Shipment::query()
            ->withoutGlobalScopes()
            ->find($shipmentId);
    }
}
