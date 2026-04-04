<?php

namespace App\Services;

use App\Models\Account;
use App\Models\BillingWallet;
use App\Models\ContentDeclaration;
use App\Models\KycVerification;
use App\Models\Shipment;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\VerificationRestriction;
use App\Models\WalletHold;
use App\Models\WalletTopup;
use App\Support\Kyc\AccountKycStatusMapper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class InternalReportDashboardService
{
    public function __construct(
        private readonly InternalExecutiveReportService $executiveReportService,
    ) {}

    /**
     * @return array<string, string>
     */
    public function domainOptions(): array
    {
        return [
            'shipments' => 'Shipment operations',
            'kyc' => 'KYC operations',
            'billing' => 'Wallet & billing operations',
            'compliance' => 'Compliance & DG operations',
            'tickets' => 'Helpdesk & tickets operations',
            'executive' => 'Executive metrics',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function dashboard(string $domain, ?User $user): ?array
    {
        return match ($domain) {
            'shipments' => $this->shipmentsDashboard(),
            'kyc' => $this->kycDashboard(),
            'billing' => $this->billingDashboard(),
            'compliance' => $this->complianceDashboard(),
            'tickets' => $this->ticketsDashboard($user),
            'executive' => $this->executiveReportService->dashboard(),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function shipmentsDashboard(): array
    {
        $query = Shipment::query()->withoutGlobalScopes();
        $statusCounts = $this->countByValues(clone $query, 'status', [
            Shipment::STATUS_PURCHASED => 'Purchased',
            Shipment::STATUS_READY_FOR_PICKUP => 'Ready for pickup',
            Shipment::STATUS_PICKED_UP => 'Picked up',
            Shipment::STATUS_IN_TRANSIT => 'In transit',
            Shipment::STATUS_OUT_FOR_DELIVERY => 'Out for delivery',
            Shipment::STATUS_REQUIRES_ACTION => 'Requires action',
            Shipment::STATUS_EXCEPTION => 'Exception',
            Shipment::STATUS_FAILED => 'Failed',
            Shipment::STATUS_DELIVERED => 'Delivered',
            Shipment::STATUS_KYC_BLOCKED => 'KYC blocked',
        ]);

        return [
            'key' => 'shipments',
            'title' => 'Shipment operations dashboard',
            'eyebrow' => 'Operational analytics / shipments',
            'description' => 'Live shipment flow counts, current status buckets, and safe recent volume trends for the internal shipment center.',
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
            'breakdowns' => [
                [
                    'title' => 'Current workflow status breakdown',
                    'items' => $statusCounts,
                ],
            ],
            'trend' => [
                'title' => 'Recent shipment intake',
                'summary' => 'Shipments created during the last seven days.',
                'points' => $this->dailyTrend(clone $query, 'created_at'),
            ],
            'action_summaries' => [
                $this->summaryLine('Action queue', (clone $query)->whereIn('status', [
                    Shipment::STATUS_REQUIRES_ACTION,
                    Shipment::STATUS_EXCEPTION,
                    Shipment::STATUS_FAILED,
                ])->count(), 'shipment(s) need operator attention now.'),
                $this->summaryLine('Final-mile focus', (clone $query)->where('status', Shipment::STATUS_OUT_FOR_DELIVERY)->count(), 'shipment(s) are already out for delivery.'),
                $this->summaryLine('Verification pressure', (clone $query)->where('status', Shipment::STATUS_KYC_BLOCKED)->count(), 'shipment(s) are blocked by upstream verification state.'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function kycDashboard(): array
    {
        $accounts = Account::query()->withoutGlobalScopes();
        $restrictedStatuses = $this->restrictedVerificationStatuses();

        $statusLabels = [
            KycVerification::STATUS_UNVERIFIED => 'Unverified',
            KycVerification::STATUS_PENDING => 'Pending',
            KycVerification::STATUS_APPROVED => 'Approved',
            KycVerification::STATUS_REJECTED => 'Rejected',
            KycVerification::STATUS_EXPIRED => 'Expired',
        ];

        $statusCounts = collect($statusLabels)->map(function (string $label, string $status) use ($accounts): array {
            return [
                'label' => $label,
                'value' => $this->countAccountsForVerificationStatus(clone $accounts, $status),
            ];
        })->values()->all();

        $pendingCount = $this->countAccountsForVerificationStatus(clone $accounts, KycVerification::STATUS_PENDING);
        $rejectedCount = $this->countAccountsForVerificationStatus(clone $accounts, KycVerification::STATUS_REJECTED);
        $restrictedCount = $this->countRestrictedAccounts(clone $accounts, $restrictedStatuses);
        $blockedShipments = Shipment::query()
            ->withoutGlobalScopes()
            ->where('status', Shipment::STATUS_KYC_BLOCKED)
            ->count();

        return [
            'key' => 'kyc',
            'title' => 'KYC operations dashboard',
            'eyebrow' => 'Operational analytics / KYC',
            'description' => 'Verification queue pressure, status mix, and safe recent submission volume across internal KYC operations.',
            'metrics' => [
                $this->metric('Tracked accounts', (clone $accounts)->count()),
                $this->metric('Pending review', $pendingCount),
                $this->metric('Rejected', $rejectedCount),
                $this->metric('Restricted', $restrictedCount),
            ],
            'breakdowns' => [
                [
                    'title' => 'Verification status breakdown',
                    'items' => $statusCounts,
                ],
            ],
            'trend' => [
                'title' => 'Recent KYC submissions',
                'summary' => 'Cases submitted during the last seven days.',
                'points' => $this->kycSubmissionTrend(),
            ],
            'action_summaries' => [
                $this->summaryLine('Pending review backlog', $pendingCount, 'account(s) are waiting for an internal review decision.'),
                $this->summaryLine('Blocked shipments', $blockedShipments, 'shipment(s) are currently held by KYC state.'),
                $this->summaryLine('Restricted accounts', $restrictedCount, 'account(s) still carry verification-linked operational restrictions.'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function billingDashboard(): array
    {
        $wallets = BillingWallet::query()->withoutGlobalScopes();
        $holds = Schema::hasTable('wallet_holds')
            ? WalletHold::query()->withoutGlobalScopes()
            : null;
        $topups = Schema::hasTable('wallet_topups')
            ? WalletTopup::query()->withoutGlobalScopes()
            : null;

        $walletStatusBreakdown = $this->countByValues(clone $wallets, 'status', [
            'active' => 'Active',
            'frozen' => 'Frozen',
        ]);

        $holdStatusBreakdown = $holds
            ? $this->countByValues(clone $holds, 'status', [
                WalletHold::STATUS_ACTIVE => 'Reserved',
                WalletHold::STATUS_CAPTURED => 'Captured',
                WalletHold::STATUS_RELEASED => 'Released',
                WalletHold::STATUS_EXPIRED => 'Expired',
            ])
            : [];

        $lowBalanceCount = (clone $wallets)
            ->whereNotNull('low_balance_threshold')
            ->whereColumn('available_balance', '<', 'low_balance_threshold')
            ->count();
        $activeHoldsCount = $holds ? (clone $holds)->where('status', WalletHold::STATUS_ACTIVE)->count() : 0;
        $frozenWalletsCount = (clone $wallets)->where('status', 'frozen')->count();
        $confirmedTopups24h = $topups
            ? (clone $topups)
                ->where('status', WalletTopup::STATUS_SUCCESS)
                ->where('created_at', '>=', now()->subDay())
                ->count()
            : 0;

        return [
            'key' => 'billing',
            'title' => 'Wallet & billing dashboard',
            'eyebrow' => 'Operational analytics / billing',
            'description' => 'Current wallet risk posture, hold lifecycle visibility, and safe recent billing volume trends.',
            'metrics' => [
                $this->metric('Wallet accounts', (clone $wallets)->count()),
                $this->metric('Low balance', $lowBalanceCount),
                $this->metric('Active holds', $activeHoldsCount),
                $this->metric('Confirmed top-ups (24h)', $confirmedTopups24h),
            ],
            'breakdowns' => array_values(array_filter([
                [
                    'title' => 'Wallet status breakdown',
                    'items' => $walletStatusBreakdown,
                ],
                $holdStatusBreakdown !== [] ? [
                    'title' => 'Preflight hold lifecycle',
                    'items' => $holdStatusBreakdown,
                ] : null,
            ])),
            'trend' => [
                'title' => 'Recent confirmed top-ups',
                'summary' => 'Confirmed wallet top-ups during the last seven days.',
                'points' => $topups
                    ? $this->dailyTrend(
                        (clone $topups)->where('status', WalletTopup::STATUS_SUCCESS),
                        'created_at'
                    )
                    : $this->emptyTrend(),
            ],
            'action_summaries' => [
                $this->summaryLine('Balance risk', $lowBalanceCount, 'wallet(s) are below their configured low-balance threshold.'),
                $this->summaryLine('Reserved funds', $activeHoldsCount, 'active shipment preflight reservation(s) are still holding funds.'),
                $this->summaryLine('Frozen wallets', $frozenWalletsCount, 'wallet(s) are currently frozen and need review before normal operations resume.'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function complianceDashboard(): array
    {
        $query = ContentDeclaration::query()->withoutGlobalScopes();

        return [
            'key' => 'compliance',
            'title' => 'Compliance & DG dashboard',
            'eyebrow' => 'Operational analytics / compliance',
            'description' => 'Declaration queue posture, dangerous-goods review pressure, and safe recent compliance intake trends.',
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
            'breakdowns' => [
                [
                    'title' => 'Compliance status breakdown',
                    'items' => $this->countByValues(clone $query, 'status', [
                        ContentDeclaration::STATUS_PENDING => 'Pending',
                        ContentDeclaration::STATUS_REQUIRES_ACTION => 'Requires action',
                        ContentDeclaration::STATUS_HOLD_DG => 'DG hold',
                        ContentDeclaration::STATUS_EXPIRED => 'Expired',
                        ContentDeclaration::STATUS_COMPLETED => 'Completed',
                    ]),
                ],
            ],
            'trend' => [
                'title' => 'Recent declaration intake',
                'summary' => 'Compliance declarations created during the last seven days.',
                'points' => $this->dailyTrend(clone $query, 'created_at'),
            ],
            'action_summaries' => [
                $this->summaryLine('Manual DG holds', (clone $query)->where('status', ContentDeclaration::STATUS_HOLD_DG)->count(), 'case(s) are blocked in manual dangerous-goods review.'),
                $this->summaryLine('Correction requests', (clone $query)->where('status', ContentDeclaration::STATUS_REQUIRES_ACTION)->count(), 'case(s) are waiting for correction before workflow can continue.'),
                $this->summaryLine('Pending waivers', (clone $query)
                    ->where('dg_flag_declared', true)
                    ->where('contains_dangerous_goods', false)
                    ->where('waiver_accepted', false)
                    ->count(), 'non-DG declaration(s) still need legal acknowledgement.'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketsDashboard(?User $user): array
    {
        $query = SupportTicket::query()->withoutGlobalScopes();
        $stats = [
            'total' => (clone $query)->count(),
            'open' => (clone $query)->whereIn('status', ['open', 'in_progress', 'waiting_customer', 'waiting_agent'])->count(),
            'urgent' => (clone $query)->where('priority', 'urgent')->count(),
            'linked_shipments' => (clone $query)->whereNotNull('shipment_id')->count(),
        ];

        return [
            'key' => 'tickets',
            'title' => 'Helpdesk & tickets dashboard',
            'eyebrow' => 'Operational analytics / helpdesk',
            'description' => 'Queue state, triage posture, and safe recent support volume trends for the internal helpdesk center.',
            'metrics' => [
                $this->metric('Total tickets', $stats['total']),
                $this->metric('Open queue', $stats['open']),
                $this->metric('Urgent', $stats['urgent']),
                $this->metric('Linked shipments', $stats['linked_shipments']),
            ],
            'breakdowns' => [
                [
                    'title' => 'Workflow status breakdown',
                    'items' => $this->countByValues(clone $query, 'status', [
                        'open' => 'Open',
                        'in_progress' => 'In progress',
                        'waiting_customer' => 'Waiting on customer',
                        'waiting_agent' => 'Waiting agent',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed',
                    ]),
                ],
                [
                    'title' => 'Priority breakdown',
                    'items' => $this->countByValues(clone $query, 'priority', [
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'urgent' => 'Urgent',
                    ]),
                ],
            ],
            'trend' => [
                'title' => 'Recent ticket intake',
                'summary' => 'Tickets created during the last seven days.',
                'points' => $this->dailyTrend(clone $query, 'created_at'),
            ],
            'action_summaries' => [
                $this->summaryLine('Unassigned open', (clone $query)
                    ->whereIn('status', ['open', 'in_progress', 'waiting_customer', 'waiting_agent'])
                    ->whereNull('assigned_to')
                    ->count(), 'open ticket(s) still have no assignee.'),
                $this->summaryLine('Urgent open', (clone $query)
                    ->whereIn('status', ['open', 'in_progress', 'waiting_customer', 'waiting_agent'])
                    ->where('priority', 'urgent')
                    ->count(), 'urgent ticket(s) remain in the active queue.'),
                $this->summaryLine('Waiting on customer', (clone $query)
                    ->where('status', 'waiting_customer')
                    ->count(), 'ticket(s) are paused on customer follow-up.'),
            ],
        ];
    }

    /**
     * @return array{label: string, value: int}
     */
    private function metric(string $label, int $value): array
    {
        return ['label' => $label, 'value' => $value];
    }

    /**
     * @return array{title: string, detail: string}
     */
    private function summaryLine(string $title, int $value, string $suffix): array
    {
        return [
            'title' => $title,
            'detail' => number_format($value) . ' ' . $suffix,
        ];
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array<string, string> $labels
     * @return array<int, array{label: string, value: int}>
     */
    private function countByValues($query, string $column, array $labels): array
    {
        return collect($labels)->map(function (string $label, string $value) use ($query, $column): array {
            return [
                'label' => $label,
                'value' => (clone $query)->where($column, $value)->count(),
            ];
        })->values()->all();
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return array<int, array{label: string, value: int}>
     */
    private function dailyTrend($query, string $column): array
    {
        if (! Schema::hasColumn($query->getModel()->getTable(), $column)) {
            return $this->emptyTrend();
        }

        $start = now()->subDays(6)->startOfDay();
        $rows = (clone $query)
            ->where($column, '>=', $start)
            ->get([$column]);

        $counts = $rows
            ->map(function ($row) use ($column): ?string {
                $value = data_get($row, $column);

                if ($value === null) {
                    return null;
                }

                return Carbon::parse($value)->toDateString();
            })
            ->filter()
            ->countBy();

        return collect(range(0, 6))
            ->map(function (int $offset) use ($counts, $start): array {
                $date = (clone $start)->addDays($offset);
                $key = $date->toDateString();

                return [
                    'label' => $date->format('M d'),
                    'value' => (int) ($counts[$key] ?? 0),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    private function kycSubmissionTrend(): array
    {
        if (! Schema::hasTable('kyc_verifications') || ! Schema::hasColumn('kyc_verifications', 'submitted_at')) {
            return $this->emptyTrend();
        }

        return $this->dailyTrend(KycVerification::query()->withoutGlobalScopes(), 'submitted_at');
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    private function emptyTrend(): array
    {
        return collect(range(0, 6))
            ->map(function (int $offset): array {
                $date = now()->subDays(6 - $offset);

                return [
                    'label' => $date->format('M d'),
                    'value' => 0,
                ];
            })
            ->all();
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $accounts
     */
    private function countAccountsForVerificationStatus($accounts, string $status): int
    {
        if (Schema::hasColumn('accounts', 'kyc_status')) {
            return (clone $accounts)->where('kyc_status', AccountKycStatusMapper::fromVerificationStatus($status))->count();
        }

        if (! Schema::hasTable('kyc_verifications')) {
            return 0;
        }

        return (clone $accounts)->whereHas('kycVerifications', static function ($query) use ($status): void {
            $query->where('status', $status);
        })->count();
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $accounts
     * @param array<int, string> $restrictedStatuses
     */
    private function countRestrictedAccounts($accounts, array $restrictedStatuses): int
    {
        if ($restrictedStatuses === []) {
            return 0;
        }

        if (Schema::hasColumn('accounts', 'kyc_status')) {
            $restrictedAccountStatuses = collect($restrictedStatuses)
                ->map(static fn (string $status): string => AccountKycStatusMapper::fromVerificationStatus($status))
                ->filter()
                ->unique()
                ->values();

            return $restrictedAccountStatuses->isEmpty()
                ? 0
                : (clone $accounts)->whereIn('kyc_status', $restrictedAccountStatuses->all())->count();
        }

        if (! Schema::hasTable('kyc_verifications')) {
            return 0;
        }

        return (clone $accounts)->whereHas('kycVerifications', static function ($query) use ($restrictedStatuses): void {
            $query->whereIn('status', $restrictedStatuses);
        })->count();
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
