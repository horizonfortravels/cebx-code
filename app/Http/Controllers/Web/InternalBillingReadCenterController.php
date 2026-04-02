<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BillingWallet;
use App\Models\Shipment;
use App\Models\User;
use App\Models\VerificationRestriction;
use App\Models\WalletHold;
use App\Models\WalletLedgerEntry;
use App\Models\WalletRefund;
use App\Models\WalletTopup;
use App\Services\InternalBillingActionService;
use App\Services\InternalKycOperationalEffectService;
use App\Support\Internal\InternalControlPlane;
use App\Support\Kyc\AccountKycStatusMapper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InternalBillingReadCenterController extends Controller
{
    public function __construct(
        private readonly InternalKycOperationalEffectService $operationalEffectService,
        private readonly InternalBillingActionService $billingActionService,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => $this->normalizedFilter((string) $request->query('status', ''), ['active', 'frozen']),
            'currency' => strtoupper(trim((string) $request->query('currency', ''))),
            'low_balance' => $this->normalizedBooleanFilter((string) $request->query('low_balance', '')),
        ];

        $walletAccounts = $this->walletAccountsQuery($filters)
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $walletAccounts->setCollection(
            $walletAccounts->getCollection()->map(
                fn (Account $account): array => $this->buildIndexRow($account)
            )
        );

        return view('pages.admin.billing-index', [
            'accounts' => $walletAccounts,
            'filters' => $filters,
            'currencyOptions' => $this->currencyOptions(),
            'statusOptions' => [
                'active' => 'Active',
                'frozen' => 'Frozen',
            ],
        ]);
    }

    public function show(Request $request, string $account, InternalControlPlane $controlPlane): View
    {
        $accountModel = $this->resolveBillingAccount($account);
        $wallet = $accountModel->billingWallet;
        $legacyWallet = $accountModel->wallet;

        abort_if(! $wallet && ! $legacyWallet, 404);

        return view('pages.admin.billing-show', [
            'account' => $accountModel,
            'walletSummary' => $this->walletSummary($accountModel),
            'ledgerEntries' => $wallet ? $this->recentLedgerEntries($wallet) : collect(),
            'topups' => $wallet ? $this->recentTopups($wallet) : collect(),
            'adjustments' => $wallet ? $this->recentAdjustments($wallet) : collect(),
            'holds' => $wallet ? $this->recentPreflightReservations($wallet) : collect(),
            'shipmentWalletEvents' => $wallet ? $this->recentShipmentWalletEvents($wallet) : collect(),
            'kycSummary' => $this->kycSummary($accountModel),
            'canViewAccount' => $this->canViewAccount($request->user(), $controlPlane),
            'canViewShipment' => $this->canViewShipment($request->user(), $controlPlane),
            'walletBackfillOnly' => ! $wallet && $legacyWallet !== null,
        ]);
    }

    public function showPreflight(Request $request, string $account, string $hold, InternalControlPlane $controlPlane): View
    {
        $accountModel = $this->resolveBillingAccount($account);
        $wallet = $this->resolveBillingWalletOrAbort($accountModel);
        $holdModel = WalletHold::query()
            ->with(['shipment' => static function ($query): void {
                $query->withoutGlobalScopes();
            }])
            ->where('wallet_id', (string) $wallet->id)
            ->where('account_id', (string) $accountModel->id)
            ->findOrFail($hold);

        $shipment = $holdModel->shipment instanceof Shipment ? $holdModel->shipment : null;

        return view('pages.admin.billing-preflight-show', [
            'account' => $accountModel,
            'walletSummary' => $this->walletSummary($accountModel),
            'preflightSummary' => $this->preflightDetailSummary($wallet, $holdModel),
            'shipmentSummary' => $shipment instanceof Shipment ? $this->shipmentSummary($wallet, $shipment) : null,
            'balanceSnapshot' => $this->walletBalanceSnapshot($wallet),
            'relatedLedgerEntries' => $this->relatedLedgerEntriesForHold($wallet, $holdModel),
            'canViewAccount' => $this->canViewAccount($request->user(), $controlPlane),
            'canViewShipment' => $this->canViewShipment($request->user(), $controlPlane),
            'canManageBillingActions' => $this->canManageBillingActions($request->user(), $controlPlane),
            'staleReleaseAction' => $this->billingActionService->staleReleaseSummary($holdModel, $shipment),
        ]);
    }

    public function showLedger(Request $request, string $account, string $entry, InternalControlPlane $controlPlane): View
    {
        $accountModel = $this->resolveBillingAccount($account);
        $wallet = $this->resolveBillingWalletOrAbort($accountModel);
        $entryModel = WalletLedgerEntry::query()
            ->where('wallet_id', (string) $wallet->id)
            ->findOrFail($entry);

        $linkedShipment = $this->resolveLinkedShipmentForEntry($wallet, $entryModel);
        $linkedPreflight = $this->resolvePreflightHoldForEntry($wallet, $entryModel);
        $linkedTopup = $this->resolveTopupForEntry($wallet, $entryModel);

        return view('pages.admin.billing-ledger-show', [
            'account' => $accountModel,
            'walletSummary' => $this->walletSummary($accountModel),
            'ledgerEntry' => $this->ledgerEntryRow($wallet, $entryModel),
            'linkedShipment' => $linkedShipment instanceof Shipment ? $this->shipmentSummary($wallet, $linkedShipment) : null,
            'linkedPreflight' => $linkedPreflight instanceof WalletHold ? $this->preflightDetailSummary($wallet, $linkedPreflight) : null,
            'linkedTopup' => $linkedTopup instanceof WalletTopup ? $this->topupDetailSummary($wallet, $linkedTopup) : null,
            'canViewAccount' => $this->canViewAccount($request->user(), $controlPlane),
            'canViewShipment' => $this->canViewShipment($request->user(), $controlPlane),
        ]);
    }

    /**
     * @param array{q: string, status: string, currency: string, low_balance: string} $filters
     */
    private function walletAccountsQuery(array $filters): Builder
    {
        return Account::query()
            ->withoutGlobalScopes()
            ->with([
                'organizationProfile',
                'billingWallet',
                'wallet',
                'kycVerification',
            ])
            ->where(function (Builder $query): void {
                $query->whereHas('billingWallet');

                if (Schema::hasTable('wallets')) {
                    $query->orWhereHas('wallet');
                }
            })
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $search = '%' . $filters['q'] . '%';

                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', $search)
                        ->orWhere('slug', 'like', $search)
                        ->orWhereHas('organizationProfile', function (Builder $organizationQuery) use ($search): void {
                            $organizationQuery->where('legal_name', 'like', $search)
                                ->orWhere('trade_name', 'like', $search);
                        })
                        ->orWhereHas('users', function (Builder $userQuery) use ($search): void {
                            $userQuery->withoutGlobalScopes()
                                ->where(function (Builder $userInner) use ($search): void {
                                    $userInner->where('name', 'like', $search)
                                        ->orWhere('email', 'like', $search);
                                });
                        });
                });
            })
            ->when($filters['status'] !== '', function (Builder $query) use ($filters): void {
                $query->whereHas('billingWallet', static function (Builder $walletQuery) use ($filters): void {
                    $walletQuery->where('status', $filters['status']);
                });
            })
            ->when($filters['currency'] !== '', function (Builder $query) use ($filters): void {
                $query->whereHas('billingWallet', static function (Builder $walletQuery) use ($filters): void {
                    $walletQuery->where('currency', $filters['currency']);
                });
            })
            ->when($filters['low_balance'] !== '', function (Builder $query) use ($filters): void {
                $query->whereHas('billingWallet', static function (Builder $walletQuery) use ($filters): void {
                    $walletQuery
                        ->whereNotNull('low_balance_threshold')
                        ->whereColumn('available_balance', $filters['low_balance'] === 'yes' ? '<' : '>=', 'low_balance_threshold');
                });
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildIndexRow(Account $account): array
    {
        return [
            'account' => $account,
            'accountLabel' => (string) $account->name,
            'accountTypeLabel' => $account->isOrganization() ? 'Organization' : 'Individual',
            'organizationSummary' => trim((string) ($account->organizationProfile?->legal_name ?: $account->organizationProfile?->trade_name ?: '')),
            'wallet' => $this->walletSummary($account),
            'kycSummary' => $this->kycSummary($account),
        ];
    }

    /**
     * @return array{
     *   source: string,
     *   source_label: string,
     *   currency: string,
     *   status: string,
     *   status_label: string,
     *   current_balance: string,
     *   reserved_balance: string,
     *   available_balance: string,
     *   total_credited: string,
     *   total_debited: string,
     *   low_balance: bool,
     *   summary_note: string
     * }
     */
    private function walletSummary(Account $account): array
    {
        if ($account->billingWallet instanceof BillingWallet) {
            $wallet = $account->billingWallet;
            $currency = (string) $wallet->currency;

            return [
                'source' => 'billing_wallet',
                'source_label' => 'Billing wallet',
                'currency' => $currency,
                'status' => (string) $wallet->status,
                'status_label' => $this->walletStatusLabel((string) $wallet->status),
                'current_balance' => $this->money($wallet->available_balance, $currency),
                'reserved_balance' => $this->money($wallet->reserved_balance, $currency),
                'available_balance' => $this->money($wallet->getEffectiveBalance(), $currency),
                'total_credited' => $this->money($wallet->total_credited, $currency),
                'total_debited' => $this->money($wallet->total_debited, $currency),
                'low_balance' => $wallet->isLowBalance(),
                'summary_note' => 'Read-only balance, ledger, hold, and funding visibility from the billing wallet source of truth.',
            ];
        }

        $wallet = $account->wallet;
        $currency = (string) ($wallet->currency ?? 'SAR');
        $available = (float) ($wallet->available_balance ?? 0);
        $reserved = (float) ($wallet->reserved_balance ?? 0);

        return [
            'source' => 'legacy_wallet',
            'source_label' => 'Legacy wallet summary',
            'currency' => $currency,
            'status' => (string) ($wallet->status ?? 'active'),
            'status_label' => $this->walletStatusLabel((string) ($wallet->status ?? 'active')),
            'current_balance' => $this->money($available, $currency),
            'reserved_balance' => $this->money($reserved, $currency),
            'available_balance' => $this->money($available - $reserved, $currency),
            'total_credited' => 'Not available',
            'total_debited' => 'Not available',
            'low_balance' => false,
            'summary_note' => 'This account still resolves through the legacy wallet fallback, so ledger and preflight summaries stay hidden from the internal billing center.',
        ];
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function recentLedgerEntries(BillingWallet $wallet): Collection
    {
        if (!Schema::hasTable('wallet_ledger_entries')) {
            return collect();
        }

        return WalletLedgerEntry::query()
            ->where('wallet_id', (string) $wallet->id)
            ->orderByDesc('sequence')
            ->limit(8)
            ->get()
            ->map(fn (WalletLedgerEntry $entry): array => $this->ledgerEntryRow($wallet, $entry))
            ->values();
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function recentTopups(BillingWallet $wallet): Collection
    {
        if (!Schema::hasTable('wallet_topups')) {
            return collect();
        }

        return WalletTopup::query()
            ->where('wallet_id', (string) $wallet->id)
            ->latest()
            ->limit(6)
            ->get()
            ->map(function (WalletTopup $topup) use ($wallet): array {
                return [
                    'amount' => $this->money($topup->amount, (string) $wallet->currency),
                    'status' => $this->topupStatusLabel((string) $topup->status),
                    'gateway' => $this->headline((string) ($topup->payment_gateway ?: 'manual')),
                    'created_at' => optional($topup->created_at)->format('Y-m-d H:i') ?? '-',
                    'confirmed_at' => optional($topup->confirmed_at)->format('Y-m-d H:i') ?? '-',
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function recentAdjustments(BillingWallet $wallet): Collection
    {
        if (!Schema::hasTable('wallet_ledger_entries')) {
            return collect();
        }

        return WalletLedgerEntry::query()
            ->where('wallet_id', (string) $wallet->id)
            ->where('transaction_type', WalletLedgerEntry::TYPE_ADJUSTMENT)
            ->orderByDesc('sequence')
            ->limit(5)
            ->get()
            ->map(function (WalletLedgerEntry $entry) use ($wallet): array {
                return [
                    'amount' => $this->money($entry->amount, (string) $wallet->currency),
                    'direction' => $this->headline((string) ($entry->direction ?: 'debit')),
                    'created_at' => optional($entry->created_at)->format('Y-m-d H:i') ?? '-',
                    'note' => Str::limit(trim((string) ($entry->notes ?? $entry->description ?? 'Adjustment')), 120, '...'),
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function recentPreflightReservations(BillingWallet $wallet): Collection
    {
        if (!Schema::hasTable('wallet_holds')) {
            return collect();
        }

        return WalletHold::query()
            ->with(['shipment' => static function ($query): void {
                $query->withoutGlobalScopes();
            }])
            ->where('wallet_id', (string) $wallet->id)
            ->latest()
            ->limit(6)
            ->get()
            ->map(function (WalletHold $hold) use ($wallet): array {
                $shipment = $hold->shipment instanceof Shipment ? $hold->shipment : null;

                return [
                    'id' => (string) $hold->id,
                    'shipment_id' => $shipment instanceof Shipment ? (string) $shipment->id : '',
                    'shipment_reference' => $shipment instanceof Shipment ? (string) $shipment->reference_number : 'Unlinked shipment',
                    'shipment_status' => $shipment instanceof Shipment
                        ? $this->shipmentStatusLabel((string) $shipment->status)
                        : 'Shipment context unavailable',
                    'status' => $this->holdStatusLabel((string) $hold->status),
                    'outcome' => $this->preflightOutcomeLabel((string) $hold->status),
                    'amount' => $this->money($hold->amount, (string) $wallet->currency),
                    'source' => $this->headline((string) ($hold->source ?: 'shipment_preflight')),
                    'shipment_total' => $shipment instanceof Shipment && $shipment->total_charge !== null
                        ? $this->money($shipment->total_charge, (string) $wallet->currency)
                        : 'Not captured',
                    'created_at' => optional($hold->created_at)->format('Y-m-d H:i') ?? '-',
                    'expires_at' => optional($hold->expires_at)->format('Y-m-d H:i') ?? '-',
                    'captured_at' => optional($hold->captured_at)->format('Y-m-d H:i') ?? '-',
                    'released_at' => optional($hold->released_at)->format('Y-m-d H:i') ?? '-',
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function recentShipmentWalletEvents(BillingWallet $wallet): Collection
    {
        if (!Schema::hasTable('wallet_ledger_entries')) {
            return collect();
        }

        $entries = WalletLedgerEntry::query()
            ->where('wallet_id', (string) $wallet->id)
            ->where(function (Builder $query): void {
                $query->where('reference_type', 'shipment')
                    ->orWhere('reference_type', 'hold')
                    ->orWhere('reference_type', 'refund')
                    ->orWhereIn('transaction_type', ['debit', 'refund', 'hold_capture', 'hold_release']);
            })
            ->orderByDesc('sequence')
            ->limit(8)
            ->get();

        if ($entries->isEmpty()) {
            return collect();
        }

        $holdIds = $entries->where('reference_type', 'hold')->pluck('reference_id')->filter()->values();
        $refundIds = $entries->where('reference_type', 'refund')->pluck('reference_id')->filter()->values();

        $holds = $holdIds->isEmpty()
            ? collect()
            : WalletHold::query()->whereIn('id', $holdIds)->get()->keyBy('id');

        $refunds = $refundIds->isEmpty()
            ? collect()
            : WalletRefund::query()
                ->where('wallet_id', (string) $wallet->id)
                ->whereIn('id', $refundIds)
                ->get()
                ->keyBy('id');

        $shipmentIds = $entries->map(function (WalletLedgerEntry $entry) use ($holds, $refunds): ?string {
            $referenceType = trim((string) ($entry->reference_type ?? ''));

            return match ($referenceType) {
                'shipment' => (string) $entry->reference_id,
                'hold' => (string) data_get($holds->get((string) $entry->reference_id), 'shipment_id'),
                'refund' => (string) data_get($refunds->get((string) $entry->reference_id), 'shipment_id'),
                default => null,
            };
        })->filter()->unique()->values();

        $shipments = $shipmentIds->isEmpty()
            ? collect()
            : Shipment::query()
                ->withoutGlobalScopes()
                ->whereIn('id', $shipmentIds)
                ->get()
                ->keyBy('id');

        return $entries->map(function (WalletLedgerEntry $entry) use ($wallet, $holds, $refunds, $shipments): array {
            $referenceType = trim((string) ($entry->reference_type ?? ''));
            $shipmentId = match ($referenceType) {
                'shipment' => (string) $entry->reference_id,
                'hold' => (string) data_get($holds->get((string) $entry->reference_id), 'shipment_id'),
                'refund' => (string) data_get($refunds->get((string) $entry->reference_id), 'shipment_id'),
                default => '',
            };
            $shipment = $shipmentId !== '' ? $shipments->get($shipmentId) : null;

            return [
                'ledger_id' => (string) $entry->id,
                'shipment_id' => $shipmentId,
                'label' => $this->ledgerTypeLabel((string) ($entry->transaction_type ?: $entry->type)),
                'shipment_reference' => $shipment instanceof Shipment ? (string) $shipment->reference_number : 'Shipment context unavailable',
                'shipment_status' => $shipment instanceof Shipment ? $this->shipmentStatusLabel((string) $shipment->status) : 'Not available',
                'amount' => $this->money($entry->amount, (string) $wallet->currency),
                'created_at' => optional($entry->created_at)->format('Y-m-d H:i') ?? '-',
                'note' => Str::limit(trim((string) ($entry->notes ?? $entry->description ?? 'Shipment-linked wallet event')), 120, '...'),
            ];
        })->values();
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private function relatedLedgerEntriesForHold(BillingWallet $wallet, WalletHold $hold): Collection
    {
        if (!Schema::hasTable('wallet_ledger_entries')) {
            return collect();
        }

        return WalletLedgerEntry::query()
            ->where('wallet_id', (string) $wallet->id)
            ->where('reference_type', 'hold')
            ->where('reference_id', (string) $hold->id)
            ->orderByDesc('sequence')
            ->limit(10)
            ->get()
            ->map(fn (WalletLedgerEntry $entry): array => $this->ledgerEntryRow($wallet, $entry))
            ->values();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function kycSummary(Account $account): ?array
    {
        $verification = $account->kycVerification;
        $status = trim((string) (
            $verification?->status
            ?? AccountKycStatusMapper::toVerificationStatus((string) ($account->kyc_status ?? ''))
        ));

        if ($status === '') {
            return null;
        }

        $blockedShipmentsCount = Shipment::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $account->id)
            ->where('status', Shipment::STATUS_KYC_BLOCKED)
            ->count();

        $capabilities = $verification?->capabilities() ?? [];
        $effect = $this->operationalEffectService->summarize($account, $status, $capabilities, $blockedShipmentsCount);
        $restrictionNames = [];

        if (Schema::hasTable('verification_restrictions')) {
            $restrictionNames = VerificationRestriction::getForStatus($status)
                ->pluck('name')
                ->filter(static fn ($name): bool => is_string($name) && trim($name) !== '')
                ->map(static fn (string $name): string => trim($name))
                ->values()
                ->all();
        }

        return [
            'status_label' => $verification?->statusDisplay()['label'] ?? $this->headline($status),
            'queue_summary' => (string) ($effect['queue_summary'] ?? 'KYC status available'),
            'action_label' => (string) ($effect['action_label'] ?? 'No extra action captured'),
            'restriction_names' => $restrictionNames,
        ];
    }

    private function canViewAccount(?User $user, InternalControlPlane $controlPlane): bool
    {
        return $user instanceof User
            && $user->hasPermission('accounts.read')
            && $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_EXTERNAL_ACCOUNTS_DETAIL);
    }

    private function canViewShipment(?User $user, InternalControlPlane $controlPlane): bool
    {
        return $user instanceof User
            && $user->hasPermission('shipments.read')
            && $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_SHIPMENTS_DETAIL);
    }

    private function canManageBillingActions(?User $user, InternalControlPlane $controlPlane): bool
    {
        return $user instanceof User
            && $user->hasPermission('wallet.configure')
            && $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_BILLING_ACTIONS);
    }

    /**
     * @return Collection<int, string>
     */
    private function currencyOptions(): Collection
    {
        if (!Schema::hasTable('billing_wallets')) {
            return collect();
        }

        return BillingWallet::query()
            ->select('currency')
            ->distinct()
            ->orderBy('currency')
            ->pluck('currency')
            ->filter(static fn ($currency): bool => is_string($currency) && trim($currency) !== '')
            ->map(static fn (string $currency): string => strtoupper(trim($currency)))
            ->values();
    }

    private function walletStatusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            'active' => 'Active',
            'frozen' => 'Frozen',
            default => $this->headline($status),
        };
    }

    private function topupStatusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            WalletTopup::STATUS_PENDING => 'Pending',
            WalletTopup::STATUS_SUCCESS => 'Confirmed',
            WalletTopup::STATUS_FAILED => 'Failed',
            WalletTopup::STATUS_EXPIRED => 'Expired',
            default => $this->headline($status),
        };
    }

    private function holdStatusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            WalletHold::STATUS_ACTIVE => 'Reserved',
            WalletHold::STATUS_CAPTURED => 'Captured',
            WalletHold::STATUS_RELEASED => 'Released',
            WalletHold::STATUS_EXPIRED => 'Expired',
            default => $this->headline($status),
        };
    }

    private function preflightOutcomeLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            WalletHold::STATUS_ACTIVE => 'Reservation is still holding funds for shipment preflight.',
            WalletHold::STATUS_CAPTURED => 'Reserved funds were captured against the shipment charge.',
            WalletHold::STATUS_RELEASED => 'Reserved funds were released back to the wallet.',
            WalletHold::STATUS_EXPIRED => 'Reservation expired before the shipment moved forward.',
            default => 'Preflight outcome is not available.',
        };
    }

    private function ledgerTypeLabel(string $type): string
    {
        return match (strtolower(trim($type))) {
            'topup' => 'Top-up',
            'debit' => 'Shipment debit',
            'refund' => 'Refund',
            'adjustment' => 'Adjustment',
            'hold' => 'Reservation',
            'hold_capture' => 'Reservation capture',
            'hold_release' => 'Reservation release',
            'reversal' => 'Reversal',
            default => $this->headline($type),
        };
    }

    private function shipmentStatusLabel(string $status): string
    {
        return $this->headline($status);
    }

    private function ledgerReferenceLabel(WalletLedgerEntry $entry): string
    {
        $referenceType = trim((string) ($entry->reference_type ?? ''));
        $referenceId = trim((string) ($entry->reference_id ?? ''));

        if ($referenceType === '' && $referenceId === '') {
            return 'No linked reference';
        }

        if ($referenceType === 'shipment' && $referenceId !== '') {
            $shipmentReference = Shipment::query()
                ->withoutGlobalScopes()
                ->where('id', $referenceId)
                ->value('reference_number');

            if (is_string($shipmentReference) && trim($shipmentReference) !== '') {
                return 'Shipment ' . trim($shipmentReference);
            }
        }

        if ($referenceType === 'hold' && $referenceId !== '') {
            $shipmentId = WalletHold::query()->where('id', $referenceId)->value('shipment_id');
            $shipmentReference = Shipment::query()
                ->withoutGlobalScopes()
                ->where('id', $shipmentId)
                ->value('reference_number');

            if (is_string($shipmentReference) && trim($shipmentReference) !== '') {
                return 'Reservation for shipment ' . trim($shipmentReference);
            }

            return 'Reservation ' . $referenceId;
        }

        if ($referenceType === 'refund' && $referenceId !== '') {
            $shipmentId = WalletRefund::query()->where('id', $referenceId)->value('shipment_id');
            $shipmentReference = Shipment::query()
                ->withoutGlobalScopes()
                ->where('id', $shipmentId)
                ->value('reference_number');

            if (is_string($shipmentReference) && trim($shipmentReference) !== '') {
                return 'Refund for shipment ' . trim($shipmentReference);
            }
        }

        return trim($this->headline($referenceType) . ' ' . $referenceId);
    }

    /**
     * @return array<string, string>
     */
    private function walletBalanceSnapshot(BillingWallet $wallet): array
    {
        return [
            'current_balance' => $this->money($wallet->available_balance, (string) $wallet->currency),
            'reserved_balance' => $this->money($wallet->reserved_balance, (string) $wallet->currency),
            'available_balance' => $this->money($wallet->getEffectiveBalance(), (string) $wallet->currency),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function shipmentSummary(BillingWallet $wallet, Shipment $shipment): array
    {
        return [
            'id' => (string) $shipment->id,
            'reference' => (string) $shipment->reference_number,
            'status' => $this->shipmentStatusLabel((string) $shipment->status),
            'total_charge' => $shipment->total_charge !== null
                ? $this->money($shipment->total_charge, (string) $wallet->currency)
                : 'Not captured',
            'reserved_amount' => $shipment->reserved_amount !== null
                ? $this->money($shipment->reserved_amount, (string) $wallet->currency)
                : 'Not captured',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function preflightDetailSummary(BillingWallet $wallet, WalletHold $hold): array
    {
        return [
            'id' => (string) $hold->id,
            'status' => $this->holdStatusLabel((string) $hold->status),
            'outcome' => $this->preflightOutcomeLabel((string) $hold->status),
            'amount' => $this->money($hold->amount, (string) ($hold->currency ?: $wallet->currency)),
            'source' => $this->headline((string) ($hold->source ?: 'shipment_preflight')),
            'created_at' => optional($hold->created_at)->format('Y-m-d H:i') ?? '-',
            'expires_at' => optional($hold->expires_at)->format('Y-m-d H:i') ?? '-',
            'captured_at' => optional($hold->captured_at)->format('Y-m-d H:i') ?? '-',
            'released_at' => optional($hold->released_at)->format('Y-m-d H:i') ?? '-',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function topupDetailSummary(BillingWallet $wallet, WalletTopup $topup): array
    {
        return [
            'amount' => $this->money($topup->amount, (string) $wallet->currency),
            'status' => $this->topupStatusLabel((string) $topup->status),
            'gateway' => $this->headline((string) ($topup->payment_gateway ?: 'manual')),
            'created_at' => optional($topup->created_at)->format('Y-m-d H:i') ?? '-',
            'confirmed_at' => optional($topup->confirmed_at)->format('Y-m-d H:i') ?? '-',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function ledgerEntryRow(BillingWallet $wallet, WalletLedgerEntry $entry): array
    {
        return [
            'id' => (string) $entry->id,
            'type' => $this->ledgerTypeLabel((string) ($entry->transaction_type ?: $entry->type)),
            'direction' => $this->headline((string) ($entry->direction ?: 'debit')),
            'amount' => $this->money($entry->amount, (string) $wallet->currency),
            'running_balance' => $this->money($entry->running_balance, (string) $wallet->currency),
            'reference' => $this->ledgerReferenceLabel($entry),
            'created_at' => optional($entry->created_at)->format('Y-m-d H:i') ?? '-',
            'note' => Str::limit(trim((string) ($entry->notes ?? $entry->description ?? '')), 120, '...') ?: 'No extra note',
        ];
    }

    private function resolveLinkedShipmentForEntry(BillingWallet $wallet, WalletLedgerEntry $entry): ?Shipment
    {
        $referenceType = trim((string) ($entry->reference_type ?? ''));
        $referenceId = trim((string) ($entry->reference_id ?? ''));

        $shipmentId = match ($referenceType) {
            'shipment' => $referenceId,
            'hold' => (string) WalletHold::query()
                ->where('wallet_id', (string) $wallet->id)
                ->where('id', $referenceId)
                ->value('shipment_id'),
            'refund' => (string) WalletRefund::query()
                ->where('wallet_id', (string) $wallet->id)
                ->where('id', $referenceId)
                ->value('shipment_id'),
            default => '',
        };

        if ($shipmentId === '') {
            return null;
        }

        return Shipment::query()
            ->withoutGlobalScopes()
            ->find($shipmentId);
    }

    private function resolvePreflightHoldForEntry(BillingWallet $wallet, WalletLedgerEntry $entry): ?WalletHold
    {
        if (trim((string) ($entry->reference_type ?? '')) !== 'hold') {
            return null;
        }

        return WalletHold::query()
            ->where('wallet_id', (string) $wallet->id)
            ->find((string) $entry->reference_id);
    }

    private function resolveTopupForEntry(BillingWallet $wallet, WalletLedgerEntry $entry): ?WalletTopup
    {
        if (trim((string) ($entry->reference_type ?? '')) !== 'topup') {
            return null;
        }

        return WalletTopup::query()
            ->where('wallet_id', (string) $wallet->id)
            ->find((string) $entry->reference_id);
    }

    private function resolveBillingAccount(string $account): Account
    {
        return Account::query()
            ->withoutGlobalScopes()
            ->with([
                'organizationProfile',
                'billingWallet',
                'wallet',
                'kycVerification',
            ])
            ->findOrFail($account);
    }

    private function resolveBillingWalletOrAbort(Account $account): BillingWallet
    {
        abort_if(! ($account->billingWallet instanceof BillingWallet), 404);

        return $account->billingWallet;
    }

    private function normalizedFilter(string $value, array $allowed): string
    {
        $value = strtolower(trim($value));

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function normalizedBooleanFilter(string $value): string
    {
        $value = strtolower(trim($value));

        return in_array($value, ['yes', 'no'], true) ? $value : '';
    }

    private function money(float|string|int|null $amount, string $currency): string
    {
        return trim($currency . ' ' . number_format((float) $amount, 2));
    }

    private function headline(string $value): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return 'Not available';
        }

        return Str::of($normalized)
            ->replace(['.', '_', '-'], ' ')
            ->squish()
            ->title()
            ->value();
    }
}
