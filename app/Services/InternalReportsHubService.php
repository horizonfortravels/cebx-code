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
            'shipments' => 'الشحنات',
            'kyc' => 'التحقق KYC',
            'billing' => 'المحفظة والفوترة',
            'compliance' => 'الامتثال والمواد الخطرة',
            'tickets' => 'التذاكر ومركز الدعم',
            'executive' => 'المؤشرات التنفيذية',
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
            'title' => 'الشحنات',
            'eyebrow' => 'التدفق التشغيلي',
            'description' => 'نظرة تشغيلية عالية المستوى على صحة تدفق الشحنات داخل مركز الشحن الداخلي.',
            'summary' => 'يركز هذا الملخص على ضغط الطوابير الحية، وحركة الشحنات الجارية، وحالات التعطيل التي تحتاج متابعة.',
            'route_name' => 'internal.shipments.index',
            'cta_label' => 'فتح مركز الشحن',
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
            'title' => 'التحقق KYC',
            'eyebrow' => 'طابور التحقق',
            'description' => 'الوضع الحالي لمراجعات التحقق عبر العمليات الداخلية الخاصة بـ KYC.',
            'summary' => 'يبرز هذا الملخص المراجعات المعلقة، والحالات المرفوضة، والحسابات التي ما زالت تحمل قيودًا مرتبطة بالتحقق.',
            'route_name' => 'internal.kyc.index',
            'cta_label' => 'فتح مركز KYC',
            'metrics' => [
                $this->metric('الحسابات المتعقبة', (clone $accounts)->count()),
                $this->metric('بانتظار المراجعة', $pendingCount),
                $this->metric('مرفوضة', $rejectedCount),
                $this->metric('مقيّدة', $restrictedCount),
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
            'title' => 'المحفظة والفوترة',
            'eyebrow' => 'متابعة الأرصدة',
            'description' => 'ملخص مالي للقراءة فقط يعرض صحة المحفظة ونشاط الحجوزات المسبقة.',
            'summary' => 'يعرض هذا المركز مؤشرات المخاطر العامة على الرصيد ونشاط الحجوزات فقط، بينما تبقى التفاصيل المالية الدقيقة داخل مركز الفوترة.',
            'route_name' => 'internal.billing.index',
            'cta_label' => 'فتح مركز الفوترة',
            'metrics' => [
                $this->metric('حسابات المحافظ', $wallets ? (clone $wallets)->count() : 0),
                $this->metric('رصيد منخفض', $wallets
                    ? (clone $wallets)
                        ->whereNotNull('low_balance_threshold')
                        ->whereColumn('available_balance', '<', 'low_balance_threshold')
                        ->count()
                    : 0),
                $this->metric('حجوزات نشطة', $holds
                    ? (clone $holds)->where('status', WalletHold::STATUS_ACTIVE)->count()
                    : 0),
                $this->metric('عمليات شحن مؤكدة (24 ساعة)', $topups
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
            'title' => 'الامتثال والمواد الخطرة',
            'eyebrow' => 'مراجعة الإقرارات',
            'description' => 'نظرة تشغيلية على ضغط مراجعات الامتثال والتصاريح المرتبطة بالمواد الخطرة.',
            'summary' => 'يتابع الحالات التي تحتاج انتباه فريق الامتثال، والإقرارات القانونية المعلقة، والشحنات الموسومة بالمواد الخطرة دون كشف الحمولات القانونية الخام.',
            'route_name' => 'internal.compliance.index',
            'cta_label' => 'فتح مركز الامتثال',
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
            'title' => 'التذاكر ومركز الدعم',
            'eyebrow' => 'طابور الدعم',
            'description' => 'ضغط طابور الدعم الداخلي، ومستوى الإلحاح، وحجم التذاكر المرتبطة بالشحنات.',
            'summary' => 'يلخّص الطابور النشط للتذاكر دون كشف نصوص الملاحظات أو تفاصيل التصعيد الداخلي المخفية داخل مركز الدعم.',
            'route_name' => 'internal.tickets.index',
            'cta_label' => 'فتح مركز التذاكر',
            'metrics' => [
                $this->metric('إجمالي التذاكر', (int) ($stats['total'] ?? 0)),
                $this->metric('الطابور المفتوح', (int) ($stats['open'] ?? 0)),
                $this->metric('العاجلة', (int) ($stats['urgent'] ?? 0)),
                $this->metric('المرتبطة بالشحنات', (int) ($stats['linked_shipments'] ?? 0)),
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
