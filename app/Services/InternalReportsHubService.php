<?php

namespace App\Services;

use App\Models\Account;
use App\Models\BillingWallet;
use App\Models\ContentDeclaration;
use App\Models\KycVerification;
use App\Models\Shipment;
use App\Models\User;
use App\Models\VerificationRestriction;
use App\Models\WalletHold;
use App\Models\WalletTopup;
use App\Support\Kyc\AccountKycStatusMapper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class InternalReportsHubService
{
    public function __construct(
        private readonly InternalTicketReadService $ticketReadService,
        private readonly InternalExecutiveReportService $executiveReportService,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function cards(?User $user): Collection
    {
        return collect([
            $this->shipmentsCard(),
            $this->kycCard(),
            $this->billingCard(),
            $this->complianceCard(),
            $this->ticketsCard($user),
            $this->executiveReportService->hubCard(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function domainOptions(): array
    {
        return [
            'shipments' => 'Shipments',
            'kyc' => 'KYC',
            'billing' => 'Wallet & billing',
            'compliance' => 'Compliance & DG',
            'tickets' => 'Tickets & helpdesk',
            'executive' => 'Executive metrics',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shipmentsCard(): array
    {
        $query = Shipment::query()->withoutGlobalScopes();

        return [
            'key' => 'shipments',
            'title' => 'Shipments',
            'eyebrow' => 'Operational flow',
            'description' => 'High-level shipment flow health across the internal shipment center.',
            'summary' => 'Focused on live queue pressure, in-flight movement, and blocked shipment visibility.',
            'route_name' => 'internal.shipments.index',
            'cta_label' => 'Open shipment center',
            'metrics' => [
                $this->metric('Total shipments', (clone $query)->count()),
                $this->metric('In flight', (clone $query)->whereIn('status', [
                    Shipment::STATUS_PURCHASED,
                    Shipment::STATUS_READY_FOR_PICKUP,
                    Shipment::STATUS_PICKED_UP,
                    Shipment::STATUS_IN_TRANSIT,
                    Shipment::STATUS_OUT_FOR_DELIVERY,
                ])->count()),
                $this->metric('Needs attention', (clone $query)->whereIn('status', [
                    Shipment::STATUS_REQUIRES_ACTION,
                    Shipment::STATUS_EXCEPTION,
                    Shipment::STATUS_FAILED,
                ])->count()),
                $this->metric('KYC blocked', (clone $query)->where('status', Shipment::STATUS_KYC_BLOCKED)->count()),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function kycCard(): array
    {
        $accounts = Account::query()->withoutGlobalScopes();
        $restrictedStatuses = $this->restrictedVerificationStatuses();

        if (Schema::hasColumn('accounts', 'kyc_status')) {
            $pendingStatus = AccountKycStatusMapper::fromVerificationStatus(KycVerification::STATUS_PENDING);
            $rejectedStatus = AccountKycStatusMapper::fromVerificationStatus(KycVerification::STATUS_REJECTED);
            $restrictedAccountStatuses = collect($restrictedStatuses)
                ->map(static fn (string $status): string => AccountKycStatusMapper::fromVerificationStatus($status))
                ->filter()
                ->unique()
                ->values();

            $pendingCount = (clone $accounts)->where('kyc_status', $pendingStatus)->count();
            $rejectedCount = (clone $accounts)->where('kyc_status', $rejectedStatus)->count();
            $restrictedCount = $restrictedAccountStatuses->isEmpty()
                ? 0
                : (clone $accounts)->whereIn('kyc_status', $restrictedAccountStatuses->all())->count();
        } else {
            $pendingCount = Schema::hasTable('kyc_verifications')
                ? (clone $accounts)->whereHas('kycVerifications', static function ($query): void {
                    $query->where('status', KycVerification::STATUS_PENDING);
                })->count()
                : 0;
            $rejectedCount = Schema::hasTable('kyc_verifications')
                ? (clone $accounts)->whereHas('kycVerifications', static function ($query): void {
                    $query->where('status', KycVerification::STATUS_REJECTED);
                })->count()
                : 0;
            $restrictedCount = Schema::hasTable('kyc_verifications') && $restrictedStatuses !== []
                ? (clone $accounts)->whereHas('kycVerifications', static function ($query) use ($restrictedStatuses): void {
                    $query->whereIn('status', $restrictedStatuses);
                })->count()
                : 0;
        }

        return [
            'key' => 'kyc',
            'title' => 'KYC',
            'eyebrow' => 'Verification queue',
            'description' => 'Current KYC review posture across internal verification operations.',
            'summary' => 'Highlights pending reviews, rejected cases, and accounts that still carry verification-linked restrictions.',
            'route_name' => 'internal.kyc.index',
            'cta_label' => 'Open KYC center',
            'metrics' => [
                $this->metric('Tracked accounts', (clone $accounts)->count()),
                $this->metric('Pending review', $pendingCount),
                $this->metric('Rejected', $rejectedCount),
                $this->metric('Restricted', $restrictedCount),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function billingCard(): array
    {
        $wallets = Schema::hasTable('billing_wallets')
            ? BillingWallet::query()->withoutGlobalScopes()
            : null;
        $holds = Schema::hasTable('wallet_holds')
            ? WalletHold::query()->withoutGlobalScopes()
            : null;
        $topups = Schema::hasTable('wallet_topups')
            ? WalletTopup::query()->withoutGlobalScopes()
            : null;

        return [
            'key' => 'billing',
            'title' => 'Wallet & billing',
            'eyebrow' => 'Balance oversight',
            'description' => 'Read-only financial operations summary for wallet health and preflight activity.',
            'summary' => 'Only high-level balance risk and reservation activity is surfaced here; detailed financial internals stay in the billing center.',
            'route_name' => 'internal.billing.index',
            'cta_label' => 'Open billing center',
            'metrics' => [
                $this->metric('Wallet accounts', $wallets ? (clone $wallets)->count() : 0),
                $this->metric('Low balance', $wallets
                    ? (clone $wallets)
                        ->whereNotNull('low_balance_threshold')
                        ->whereColumn('available_balance', '<', 'low_balance_threshold')
                        ->count()
                    : 0),
                $this->metric('Active holds', $holds
                    ? (clone $holds)->where('status', WalletHold::STATUS_ACTIVE)->count()
                    : 0),
                $this->metric('Confirmed top-ups (24h)', $topups
                    ? (clone $topups)
                        ->where('status', WalletTopup::STATUS_SUCCESS)
                        ->where('created_at', '>=', now()->subDay())
                        ->count()
                    : 0),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function complianceCard(): array
    {
        $query = ContentDeclaration::query()->withoutGlobalScopes();

        return [
            'key' => 'compliance',
            'title' => 'Compliance & DG',
            'eyebrow' => 'Declaration review',
            'description' => 'Operational view into dangerous-goods and declaration review pressure.',
            'summary' => 'Tracks cases that need compliance attention, pending waivers, and DG-flagged shipments without exposing raw legal payloads.',
            'route_name' => 'internal.compliance.index',
            'cta_label' => 'Open compliance center',
            'metrics' => [
                $this->metric('Total cases', (clone $query)->count()),
                $this->metric('Needs attention', (clone $query)->whereIn('status', [
                    ContentDeclaration::STATUS_HOLD_DG,
                    ContentDeclaration::STATUS_REQUIRES_ACTION,
                ])->count()),
                $this->metric('Waiver pending', (clone $query)
                    ->where('dg_flag_declared', true)
                    ->where('contains_dangerous_goods', false)
                    ->where('waiver_accepted', false)
                    ->count()),
                $this->metric('DG flagged', (clone $query)->where('contains_dangerous_goods', true)->count()),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketsCard(?User $user): array
    {
        $stats = $this->ticketReadService->stats($user);

        return [
            'key' => 'tickets',
            'title' => 'Tickets & helpdesk',
            'eyebrow' => 'Support queue',
            'description' => 'Internal helpdesk queue pressure, urgency, and shipment-linked ticket load.',
            'summary' => 'Summarizes the active ticket queue without exposing note bodies or hidden escalation content from the helpdesk center.',
            'route_name' => 'internal.tickets.index',
            'cta_label' => 'Open ticket center',
            'metrics' => [
                $this->metric('Total tickets', (int) ($stats['total'] ?? 0)),
                $this->metric('Open queue', (int) ($stats['open'] ?? 0)),
                $this->metric('Urgent', (int) ($stats['urgent'] ?? 0)),
                $this->metric('Linked shipments', (int) ($stats['linked_shipments'] ?? 0)),
            ],
        ];
    }

    /**
     * @return array{label: string, value: int}
     */
    private function metric(string $label, int $value): array
    {
        return [
            'label' => $label,
            'value' => $value,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function restrictedVerificationStatuses(): array
    {
        if (! Schema::hasTable('verification_restrictions')) {
            return [];
        }

        return VerificationRestriction::query()
            ->active()
            ->get()
            ->flatMap(static fn (VerificationRestriction $restriction): array => $restriction->applies_to_statuses ?? [])
            ->filter(static fn ($status): bool => is_string($status) && trim($status) !== '')
            ->map(static fn (string $status): string => trim($status))
            ->unique()
            ->values()
            ->all();
    }
}
