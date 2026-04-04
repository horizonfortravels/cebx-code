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
            'shipments' => 'عمليات الشحن',
            'kyc' => 'عمليات KYC',
            'billing' => 'عمليات المحفظة والفوترة',
            'compliance' => 'عمليات الامتثال والمواد الخطرة',
            'tickets' => 'عمليات الدعم والتذاكر',
            'executive' => 'المؤشرات التنفيذية',
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
            Shipment::STATUS_PURCHASED => 'تم الشراء',
            Shipment::STATUS_READY_FOR_PICKUP => 'جاهزة للاستلام',
            Shipment::STATUS_PICKED_UP => 'تم الاستلام',
            Shipment::STATUS_IN_TRANSIT => 'في الطريق',
            Shipment::STATUS_OUT_FOR_DELIVERY => 'خرجت للتسليم',
            Shipment::STATUS_REQUIRES_ACTION => 'تحتاج إجراء',
            Shipment::STATUS_EXCEPTION => 'استثناء',
            Shipment::STATUS_FAILED => 'فشلت',
            Shipment::STATUS_DELIVERED => 'تم التسليم',
            Shipment::STATUS_KYC_BLOCKED => 'محجوبة بسبب KYC',
        ]);

        return [
            'key' => 'shipments',
            'title' => 'لوحة عمليات الشحن',
            'eyebrow' => 'تحليلات تشغيلية / الشحنات',
            'description' => 'أعداد تدفق الشحنات الحية، وتوزيع الحالات الحالية، واتجاهات الحجم الحديثة الآمنة داخل مركز الشحن الداخلي.',
            'metrics' => [
                $this->metric('إجمالي الشحنات', (clone $query)->count()),
                $this->metric('قيد التنفيذ', (clone $query)->whereIn('status', [
                    Shipment::STATUS_PURCHASED,
                    Shipment::STATUS_READY_FOR_PICKUP,
                    Shipment::STATUS_PICKED_UP,
                    Shipment::STATUS_IN_TRANSIT,
                    Shipment::STATUS_OUT_FOR_DELIVERY,
                ])->count()),
                $this->metric('تحتاج متابعة', (clone $query)->whereIn('status', [
                    Shipment::STATUS_REQUIRES_ACTION,
                    Shipment::STATUS_EXCEPTION,
                    Shipment::STATUS_FAILED,
                ])->count()),
                $this->metric('محجوبة بسبب KYC', (clone $query)->where('status', Shipment::STATUS_KYC_BLOCKED)->count()),
            ],
            'breakdowns' => [
                [
                    'title' => 'توزيع حالات سير العمل الحالية',
                    'items' => $statusCounts,
                ],
            ],
            'trend' => [
                'title' => 'الاستقبال الحديث للشحنات',
                'summary' => 'الشحنات التي أُنشئت خلال الأيام السبعة الماضية.',
                'points' => $this->dailyTrend(clone $query, 'created_at'),
            ],
            'action_summaries' => [
                $this->summaryLine('طابور الإجراءات', (clone $query)->whereIn('status', [
                    Shipment::STATUS_REQUIRES_ACTION,
                    Shipment::STATUS_EXCEPTION,
                    Shipment::STATUS_FAILED,
                ])->count(), 'شحنة تحتاج الآن إلى متابعة المشغّل.'),
                $this->summaryLine('تركيز الميل الأخير', (clone $query)->where('status', Shipment::STATUS_OUT_FOR_DELIVERY)->count(), 'شحنة خرجت بالفعل للتسليم.'),
                $this->summaryLine('ضغط التحقق', (clone $query)->where('status', Shipment::STATUS_KYC_BLOCKED)->count(), 'شحنة محجوبة بسبب حالة تحقق سابقة.'),
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
            KycVerification::STATUS_UNVERIFIED => 'غير موثّق',
            KycVerification::STATUS_PENDING => 'قيد المراجعة',
            KycVerification::STATUS_APPROVED => 'مقبول',
            KycVerification::STATUS_REJECTED => 'مرفوض',
            KycVerification::STATUS_EXPIRED => 'منتهي',
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
            'title' => 'لوحة عمليات KYC',
            'eyebrow' => 'تحليلات تشغيلية / KYC',
            'description' => 'ضغط طابور التحقق، وتوزيع الحالات، وحجم الإرسالات الحديثة الآمنة عبر عمليات KYC الداخلية.',
            'metrics' => [
                $this->metric('الحسابات المتعقبة', (clone $accounts)->count()),
                $this->metric('بانتظار المراجعة', $pendingCount),
                $this->metric('مرفوضة', $rejectedCount),
                $this->metric('مقيّدة', $restrictedCount),
            ],
            'breakdowns' => [
                [
                    'title' => 'توزيع حالات التحقق',
                    'items' => $statusCounts,
                ],
            ],
            'trend' => [
                'title' => 'إرسالات KYC الحديثة',
                'summary' => 'الحالات التي أُرسلت خلال الأيام السبعة الماضية.',
                'points' => $this->kycSubmissionTrend(),
            ],
            'action_summaries' => [
                $this->summaryLine('تراكم المراجعات المعلقة', $pendingCount, 'حساب بانتظار قرار المراجعة الداخلية.'),
                $this->summaryLine('الشحنات المحجوبة', $blockedShipments, 'شحنة محجوبة حاليًا بسبب حالة KYC.'),
                $this->summaryLine('الحسابات المقيّدة', $restrictedCount, 'حساب ما زال يحمل قيودًا تشغيلية مرتبطة بالتحقق.'),
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
            'active' => 'نشطة',
            'frozen' => 'مجمّدة',
        ]);

        $holdStatusBreakdown = $holds
            ? $this->countByValues(clone $holds, 'status', [
                WalletHold::STATUS_ACTIVE => 'محجوزة',
                WalletHold::STATUS_CAPTURED => 'محصّلة',
                WalletHold::STATUS_RELEASED => 'محرّرة',
                WalletHold::STATUS_EXPIRED => 'منتهية',
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
            'title' => 'لوحة المحفظة والفوترة',
            'eyebrow' => 'تحليلات تشغيلية / الفوترة',
            'description' => 'الوضع الحالي لمخاطر المحافظ، ووضوح دورة حياة الحجوزات، واتجاهات حجم الفوترة الحديثة الآمنة.',
            'metrics' => [
                $this->metric('حسابات المحافظ', (clone $wallets)->count()),
                $this->metric('رصيد منخفض', $lowBalanceCount),
                $this->metric('الحجوزات النشطة', $activeHoldsCount),
                $this->metric('عمليات شحن مؤكدة (24 ساعة)', $confirmedTopups24h),
            ],
            'breakdowns' => array_values(array_filter([
                [
                    'title' => 'توزيع حالات المحافظ',
                    'items' => $walletStatusBreakdown,
                ],
                $holdStatusBreakdown !== [] ? [
                    'title' => 'دورة حياة الحجز المسبق',
                    'items' => $holdStatusBreakdown,
                ] : null,
            ])),
            'trend' => [
                'title' => 'عمليات الشحن المؤكدة الحديثة',
                'summary' => 'عمليات شحن المحفظة المؤكدة خلال الأيام السبعة الماضية.',
                'points' => $topups
                    ? $this->dailyTrend(
                        (clone $topups)->where('status', WalletTopup::STATUS_SUCCESS),
                        'created_at'
                    )
                    : $this->emptyTrend(),
            ],
            'action_summaries' => [
                $this->summaryLine('مخاطر الرصيد', $lowBalanceCount, 'محفظة تحت حد الرصيد المنخفض المهيأ لها.'),
                $this->summaryLine('الأموال المحجوزة', $activeHoldsCount, 'حجز مسبق للشحنات ما زال يحتفظ بالأموال.'),
                $this->summaryLine('المحافظ المجمّدة', $frozenWalletsCount, 'محفظة مجمّدة وتحتاج مراجعة قبل استئناف التشغيل الطبيعي.'),
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
            'title' => 'لوحة الامتثال والمواد الخطرة',
            'eyebrow' => 'تحليلات تشغيلية / الامتثال',
            'description' => 'وضع طابور الإقرارات، وضغط مراجعات المواد الخطرة، واتجاهات الاستقبال الحديثة الآمنة لحالات الامتثال.',
            'metrics' => [
                $this->metric('إجمالي الحالات', (clone $query)->count()),
                $this->metric('تحتاج متابعة', (clone $query)->whereIn('status', [
                    ContentDeclaration::STATUS_HOLD_DG,
                    ContentDeclaration::STATUS_REQUIRES_ACTION,
                ])->count()),
                $this->metric('إقرار قانوني معلّق', (clone $query)
                    ->where('dg_flag_declared', true)
                    ->where('contains_dangerous_goods', false)
                    ->where('waiver_accepted', false)
                    ->count()),
                $this->metric('موسومة كمواد خطرة', (clone $query)->where('contains_dangerous_goods', true)->count()),
            ],
            'breakdowns' => [
                [
                    'title' => 'توزيع حالات الامتثال',
                    'items' => $this->countByValues(clone $query, 'status', [
                        ContentDeclaration::STATUS_PENDING => 'قيد الانتظار',
                        ContentDeclaration::STATUS_REQUIRES_ACTION => 'تحتاج إجراء',
                        ContentDeclaration::STATUS_HOLD_DG => 'حجز مواد خطرة',
                        ContentDeclaration::STATUS_EXPIRED => 'منتهية',
                        ContentDeclaration::STATUS_COMPLETED => 'مكتملة',
                    ]),
                ],
            ],
            'trend' => [
                'title' => 'الإقرارات الحديثة',
                'summary' => 'إقرارات الامتثال التي أُنشئت خلال الأيام السبعة الماضية.',
                'points' => $this->dailyTrend(clone $query, 'created_at'),
            ],
            'action_summaries' => [
                $this->summaryLine('حجوزات المراجعة اليدوية', (clone $query)->where('status', ContentDeclaration::STATUS_HOLD_DG)->count(), 'حالة محجوبة في مراجعة المواد الخطرة اليدوية.'),
                $this->summaryLine('طلبات التصحيح', (clone $query)->where('status', ContentDeclaration::STATUS_REQUIRES_ACTION)->count(), 'حالة بانتظار التصحيح قبل استمرار سير العمل.'),
                $this->summaryLine('الإقرارات المعلقة', (clone $query)
                    ->where('dg_flag_declared', true)
                    ->where('contains_dangerous_goods', false)
                    ->where('waiver_accepted', false)
                    ->count(), 'إقرار غير خطِر ما زال يحتاج إلى إقرار قانوني.'),
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
            'title' => 'لوحة الدعم والتذاكر',
            'eyebrow' => 'تحليلات تشغيلية / الدعم',
            'description' => 'حالة الطابور، ووضع الفرز، واتجاهات حجم الدعم الحديثة الآمنة داخل مركز الدعم الداخلي.',
            'metrics' => [
                $this->metric('إجمالي التذاكر', $stats['total']),
                $this->metric('الطابور المفتوح', $stats['open']),
                $this->metric('العاجلة', $stats['urgent']),
                $this->metric('المرتبطة بالشحنات', $stats['linked_shipments']),
            ],
            'breakdowns' => [
                [
                    'title' => 'توزيع حالات سير العمل',
                    'items' => $this->countByValues(clone $query, 'status', [
                        'open' => 'مفتوحة',
                        'in_progress' => 'قيد المعالجة',
                        'waiting_customer' => 'بانتظار العميل',
                        'waiting_agent' => 'بانتظار الفريق',
                        'resolved' => 'محلولة',
                        'closed' => 'مغلقة',
                    ]),
                ],
                [
                    'title' => 'توزيع الأولويات',
                    'items' => $this->countByValues(clone $query, 'priority', [
                        'low' => 'منخفضة',
                        'medium' => 'متوسطة',
                        'high' => 'مرتفعة',
                        'urgent' => 'عاجلة',
                    ]),
                ],
            ],
            'trend' => [
                'title' => 'التذاكر الحديثة',
                'summary' => 'التذاكر التي أُنشئت خلال الأيام السبعة الماضية.',
                'points' => $this->dailyTrend(clone $query, 'created_at'),
            ],
            'action_summaries' => [
                $this->summaryLine('مفتوحة بلا إسناد', (clone $query)
                    ->whereIn('status', ['open', 'in_progress', 'waiting_customer', 'waiting_agent'])
                    ->whereNull('assigned_to')
                    ->count(), 'تذكرة مفتوحة ما زالت بلا مسؤول.'),
                $this->summaryLine('عاجلة مفتوحة', (clone $query)
                    ->whereIn('status', ['open', 'in_progress', 'waiting_customer', 'waiting_agent'])
                    ->where('priority', 'urgent')
                    ->count(), 'تذكرة عاجلة ما زالت في الطابور النشط.'),
                $this->summaryLine('بانتظار العميل', (clone $query)
                    ->where('status', 'waiting_customer')
                    ->count(), 'تذكرة متوقفة بانتظار متابعة العميل.'),
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
                    'label' => $date->format('d/m'),
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
                    'label' => $date->format('d/m'),
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
