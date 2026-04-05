<?php

namespace App\Services;

use App\Models\Account;
use App\Models\BillingWallet;
use App\Models\CarrierError;
use App\Models\IntegrationHealthLog;
use App\Models\KycVerification;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletHold;
use App\Support\Internal\InternalControlPlane;
use App\Support\Kyc\AccountKycStatusMapper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InternalLandingDashboardService
{
    /**
     * @var array<int, string>
     */
    private const ACTIVE_SHIPMENT_STATUSES = [
        Shipment::STATUS_PURCHASED,
        Shipment::STATUS_READY_FOR_PICKUP,
        Shipment::STATUS_PICKED_UP,
        Shipment::STATUS_IN_TRANSIT,
        Shipment::STATUS_OUT_FOR_DELIVERY,
    ];

    /**
     * @var array<int, string>
     */
    private const ATTENTION_SHIPMENT_STATUSES = [
        Shipment::STATUS_REQUIRES_ACTION,
        Shipment::STATUS_EXCEPTION,
        Shipment::STATUS_FAILED,
    ];

    /**
     * @var array<string, string>
     */
    private const TONE_MAP = [
        'neutral' => 'neutral',
        'info' => 'info',
        'success' => 'success',
        'warning' => 'warning',
        'danger' => 'danger',
    ];

    public function __construct(
        private readonly InternalControlPlane $controlPlane,
        private readonly InternalReportDashboardService $reportDashboardService,
        private readonly InternalTicketReadService $ticketReadService,
        private readonly InternalIntegrationReadService $integrationReadService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildForAdmin(User $user, ?Account $selectedAccount): array
    {
        $canTenantContext = $this->canSelectTenantContext($user);
        $shipmentsDashboard = $this->dashboardIfVisible($user, 'shipments', InternalControlPlane::SURFACE_INTERNAL_REPORTS_SHIPMENTS_DASHBOARD);
        $kycDashboard = $this->dashboardIfVisible($user, 'kyc', InternalControlPlane::SURFACE_INTERNAL_REPORTS_KYC_DASHBOARD);
        $billingDashboard = $this->dashboardIfVisible($user, 'billing', InternalControlPlane::SURFACE_INTERNAL_REPORTS_BILLING_DASHBOARD);
        $actions = $this->adminActions($user, $selectedAccount, $canTenantContext);
        $roleProfile = [
            'name' => InternalControlPlane::ROLE_SUPER_ADMIN,
            'label' => 'مدير المنصة',
            'description' => 'رؤية إدارية كاملة عبر المراكز الداخلية مع وصول تنفيذي للتشغيل والتقارير وضبط المنصة.',
            'landing_route' => 'admin.index',
        ];
        $hasDeprecatedRoleAssignments = false;
        $pulseItems = [
            $this->summaryMetric('التذاكر المفتوحة', $this->openTicketCount($user), 'warning'),
            $this->summaryMetric('الحجوزات النشطة', $this->activeHoldCount(), 'info'),
            $this->summaryMetric('المحافظ المعرضة للخطر', $this->lowBalanceWalletCount(), 'danger'),
            $this->summaryMetric('تنبيهات التكامل', $this->integrationAttentionCount($user), 'warning'),
        ];

        return $this->withLegacyShellRoleContext([
            'page_title' => 'لوحة الإدارة الداخلية',
            'eyebrow' => 'المركز الداخلي / لوحة الإدارة الداخلية',
            'title' => 'لوحة الإدارة الداخلية',
            'description' => 'لوحة قيادة تنفيذية تبدأ بها الرحلة الداخلية فور تسجيل الدخول، وتمنح الفريق الإداري صورة فورية عن الشحن، الدعم، التحقق، وصحة التكاملات من نقطة عمل واحدة.',
            'pills' => array_values(array_filter([
                $this->pill('مدير المنصة', 'info'),
                $selectedAccount
                    ? $this->pill('الحساب الجاري: '.$selectedAccount->name, 'success')
                    : $this->pill('المنظور الحالي: المنصة كاملة', 'neutral'),
            ])),
            'hero_actions' => array_slice($actions, 0, 3),
            'kpis' => [
                $this->kpi('إجمالي الحسابات', $this->totalAccounts(), 'عدد الحسابات الخارجية المعروضة على مستوى المنصة.', 'info'),
                $this->kpi('إجمالي مستخدمي المنصة', $this->totalUsers(), 'يشمل المستخدمين الداخليين والخارجيين.', 'neutral'),
                $this->kpi('إجمالي الشحنات', $this->totalShipments(), 'الحجم التراكمي المسجل داخل المنصة.', 'info'),
                $this->kpi('الشحنات النشطة الآن', $this->activeShipmentCount(), 'شحنات تتحرك حاليًا داخل دورة التنفيذ.', 'success'),
                $this->kpi('الشحنات الاستثنائية', $this->exceptionShipmentCount(), 'حالات تحتاج إلى متابعة تشغيلية الآن.', 'danger'),
                $this->kpi('تم التسليم اليوم', $this->deliveredTodayCount(), 'شحنات اكتمل تسليمها منذ بداية اليوم.', 'success'),
                $this->kpi('KYC معلق أو مرفوض', $this->pendingAndRejectedKycCount(), 'حسابات تنتظر مراجعة أو تحتاج إلى إعادة تقديم.', 'warning'),
                $this->kpi('التذاكر المفتوحة', $this->openTicketCount($user), 'تذاكر ما زالت ضمن طابور المعالجة.', 'warning'),
                $this->kpi('تكاملات تحتاج متابعة', $this->integrationAttentionCount($user), 'خدمات متدهورة أو تكاملات تتطلب انتباهًا.', 'danger'),
            ],
            'main_chart' => $this->trendCard(
                'اتجاه الشحنات الحديثة',
                'آخر 14 يومًا على مستوى المنصة.',
                $this->shipmentTrendPoints()
            ),
            'chart_cards' => array_values(array_filter([
                $this->distributionCard(
                    'توزيع حالات الشحن',
                    'الحالات التشغيلية الحالية للشحنات النشطة والمستثناة.',
                    $this->distributionItemsFromDashboard($shipmentsDashboard, 0)
                ),
                $this->distributionCard(
                    'مزيج شركات الشحن',
                    'أعلى الشركات من حيث الحجم ومعدل التسليم.',
                    $this->carrierMixItems()
                ),
                $this->distributionCard(
                    'توزيع حالات التحقق',
                    'حالة KYC الحالية عبر الحسابات النشطة.',
                    $this->distributionItemsFromDashboard($kycDashboard, 0)
                ),
                $this->distributionCard(
                    'صحة التكاملات والناقلين',
                    'أحدث الفحوصات التي تحتاج متابعة.',
                    $this->integrationHealthItems(),
                    'health'
                ),
            ])),
            'stream_cards' => array_values(array_filter([
                $this->streamCard('أحدث الشحنات', 'آخر الشحنات المضافة عبر المنصة.', $this->recentShipmentRows()),
                $this->streamCard('الشحنات الاستثنائية', 'الحالات المفتوحة التي تحتاج متابعة تشغيلية.', $this->recentExceptionRows()),
                $this->streamCard('مراجعات KYC المعلقة', 'الحسابات التي تنتظر قرارًا أو إعادة تقديم.', $this->pendingKycRows()),
                $this->streamCard('تنبيهات الأعطال والتكاملات', 'أحدث الأعطال المرتبطة بالناقلين أو الفحوصات المتدهورة.', $this->recentFailureRows()),
            ])),
            'side_cards' => array_values(array_filter([
                $this->selectedAccountCard($user, $selectedAccount, $canTenantContext ? route('admin.tenant-context') : null),
                $this->actionsCard('إجراءات سريعة', 'تنقل مباشر نحو أكثر المهام استخدامًا في العمل الداخلي.', $actions),
                $this->summaryCard('نبض المنصة', $pulseItems),
                $billingDashboard !== null
                    ? $this->noteCard(
                        'مؤشر الفوترة',
                        (string) data_get($billingDashboard, 'action_summaries.0.detail', 'تظل مؤشرات المحفظة والفوترة معروضة هنا عند توفر البيانات.')
                    )
                    : null,
            ])),
        ], $roleProfile, $hasDeprecatedRoleAssignments, $selectedAccount);
    }

    /**
     * @param  array{name: string|null, label: string, description: string, landing_route: string}  $roleProfile
     * @return array<string, mixed>
     */
    public function buildForInternal(
        User $user,
        ?Account $selectedAccount,
        array $roleProfile,
        bool $hasDeprecatedRoleAssignments,
    ): array {
        $role = $this->controlPlane->primaryCanonicalRole($user);

        if ($role === InternalControlPlane::ROLE_CARRIER_MANAGER) {
            return $this->buildForCarrierManager($user, $selectedAccount, $roleProfile, $hasDeprecatedRoleAssignments);
        }

        return $this->buildForOperations($user, $selectedAccount, $roleProfile, $hasDeprecatedRoleAssignments);
    }

    /**
     * @param  array{name: string|null, label: string, description: string, landing_route: string}  $roleProfile
     * @return array<string, mixed>
     */
    private function buildForOperations(
        User $user,
        ?Account $selectedAccount,
        array $roleProfile,
        bool $hasDeprecatedRoleAssignments,
    ): array {
        $canTenantContext = $this->canSelectTenantContext($user);
        $shipmentsDashboard = $this->dashboardIfVisible($user, 'shipments', InternalControlPlane::SURFACE_INTERNAL_REPORTS_SHIPMENTS_DASHBOARD);
        $kycDashboard = $this->dashboardIfVisible($user, 'kyc', InternalControlPlane::SURFACE_INTERNAL_REPORTS_KYC_DASHBOARD);
        $ticketsDashboard = $this->dashboardIfVisible($user, 'tickets', InternalControlPlane::SURFACE_INTERNAL_REPORTS_TICKETS_DASHBOARD);
        $actions = $this->operationsActions($user, $selectedAccount, $canTenantContext);
        $rolePulse = [
            $this->summaryMetric('التذاكر المفتوحة', $this->openTicketCount($user), 'warning'),
            $this->summaryMetric('شحنات تحتاج متابعة', $this->exceptionShipmentCount(), 'danger'),
            $this->summaryMetric('طلبات KYC المعلقة', $this->pendingKycCount(), 'warning'),
            $this->summaryMetric('تنبيهات التكامل', $this->integrationAttentionCount($user), 'warning'),
        ];

        return $this->withLegacyShellRoleContext([
            'page_title' => 'لوحة العمليات الداخلية',
            'eyebrow' => 'المركز الداخلي / لوحة العمليات الداخلية',
            'title' => 'لوحة العمليات الداخلية',
            'description' => 'لوحة عمليات داخلية تبدأ منها المهام اليومية مباشرة، وتمنحك صورة مهنية وسريعة عن الطوابير الحرجة، اتجاه الشحن، الدعم، والتحقق قبل الانتقال إلى التفاصيل.',
            'pills' => array_values(array_filter([
                $this->pill($roleProfile['label'], 'info'),
                $selectedAccount
                    ? $this->pill('عدسة الحساب: '.$selectedAccount->name, 'success')
                    : $this->pill('المنظور الحالي: المنصة العامة', 'neutral'),
                $hasDeprecatedRoleAssignments
                    ? $this->pill('تم إخفاء الدور الداخلي القديم من الواجهة النشطة', 'warning')
                    : null,
            ])),
            'hero_actions' => array_slice($actions, 0, 3),
            'kpis' => [
                $this->kpi('إجمالي الشحنات', $this->totalShipments(), 'حجم الشحنات المسجلة على مستوى المنصة.', 'info'),
                $this->kpi('الشحنات النشطة الآن', $this->activeShipmentCount(), 'شحنات تتحرك داخل دورة التنفيذ الآن.', 'success'),
                $this->kpi('الشحنات الاستثنائية', $this->exceptionShipmentCount(), 'حالات تتطلب متابعة تشغيلية مباشرة.', 'danger'),
                $this->kpi('KYC معلق أو مرفوض', $this->pendingAndRejectedKycCount(), 'حسابات تنتظر حسمًا أو إعادة تقديم.', 'warning'),
                $this->kpi('التذاكر المفتوحة', $this->openTicketCount($user), 'التذاكر المتاحة للمتابعة الآن.', 'warning'),
                $this->kpi('التذاكر العاجلة', $this->urgentTicketCount(), 'تذاكر بأولوية عاجلة داخل الطابور الحالي.', 'danger'),
                $this->kpi('تكاملات تحتاج متابعة', $this->integrationAttentionCount($user), 'خدمات أو موصلات عليها تنبيه تشغيلي.', 'danger'),
            ],
            'main_chart' => $this->trendCard(
                'اتجاه الشحنات الحديثة',
                'آخر 14 يومًا من العمل التشغيلي.',
                $this->shipmentTrendPoints()
            ),
            'chart_cards' => array_values(array_filter([
                $this->distributionCard(
                    'توزيع حالات الشحن',
                    'الحالات الحالية داخل دورة التنفيذ والتسليم.',
                    $this->distributionItemsFromDashboard($shipmentsDashboard, 0)
                ),
                $this->distributionCard(
                    'توزيع أولويات الدعم',
                    'أولويات التذاكر الحالية داخل طابور العمل.',
                    $this->distributionItemsFromDashboard($ticketsDashboard, 1)
                ),
                $this->distributionCard(
                    'توزيع حالات KYC',
                    'صورة سريعة لحالة التحقق عبر الحسابات.',
                    $this->distributionItemsFromDashboard($kycDashboard, 0)
                ),
                $this->distributionCard(
                    'صحة التكاملات',
                    'أحدث الخدمات التي تحتاج متابعة أو مراجعة.',
                    $this->integrationHealthItems(),
                    'health'
                ),
            ])),
            'stream_cards' => array_values(array_filter([
                $this->streamCard('أحدث الشحنات', 'الشحنات التي دخلت النظام مؤخرًا.', $this->recentShipmentRows()),
                $this->streamCard('الشحنات الاستثنائية', 'الحالات المفتوحة التي قد تحتاج تدخلًا سريعًا.', $this->recentExceptionRows()),
                $this->streamCard('مراجعات KYC المعلقة', 'الحسابات التي ما زالت بانتظار قرار أو استكمال.', $this->pendingKycRows()),
                $this->streamCard('تنبيهات التشغيل', 'أعطال الناقلين أو الفحوصات المتدهورة الأحدث.', $this->recentFailureRows()),
            ])),
            'side_cards' => array_values(array_filter([
                $this->selectedAccountCard($user, $selectedAccount, $canTenantContext ? route('internal.tenant-context') : null),
                $this->actionsCard('مسارات العمل المتاحة', 'كل إجراء هنا يحترم حدود الدور الحالي والصلاحيات النشطة فقط.', $actions),
                $this->summaryCard('نبض الدور الحالي', $rolePulse),
                $this->noteCard('وصف الدور', $roleProfile['description']),
                $hasDeprecatedRoleAssignments
                    ? $this->warningCard('تنبيه أدوار قديمة', 'تم إخفاء الدور الداخلي القديم من الواجهة النشطة. اطلب إعادة التعيين إلى أحد الأدوار الداخلية المعتمدة إذا لزم الأمر.')
                    : null,
            ])),
        ], $roleProfile, $hasDeprecatedRoleAssignments, $selectedAccount);
    }

    /**
     * @param  array{name: string|null, label: string, description: string, landing_route: string}  $roleProfile
     * @return array<string, mixed>
     */
    private function buildForCarrierManager(
        User $user,
        ?Account $selectedAccount,
        array $roleProfile,
        bool $hasDeprecatedRoleAssignments,
    ): array {
        $filters = ['q' => '', 'type' => '', 'state' => '', 'health' => ''];
        $integrationRows = $this->integrationReadService->filteredRows($user, $filters);
        $integrationStats = $this->integrationReadService->stats($user);
        $actions = $this->carrierManagerActions($user, $selectedAccount);
        $attentionRows = $integrationRows
            ->where('needs_attention', true)
            ->take(6)
            ->values();

        return $this->withLegacyShellRoleContext([
            'page_title' => 'لوحة عمليات الناقلين',
            'eyebrow' => 'المركز الداخلي / لوحة عمليات الناقلين',
            'title' => 'لوحة عمليات الناقلين',
            'description' => 'لوحة عمليات مخصصة تبدأ منها متابعة الناقلين والتكاملات فور الدخول، مع رؤية مباشرة لصحة الخدمات، الأعطال الحديثة، والاستعداد التشغيلي ضمن حدود الدور الآمنة.',
            'pills' => array_values(array_filter([
                $this->pill($roleProfile['label'], 'info'),
                $this->pill('إعدادات SMTP', 'success'),
                $hasDeprecatedRoleAssignments
                    ? $this->pill('تم إخفاء الدور الداخلي القديم من الواجهة النشطة', 'warning')
                    : null,
            ])),
            'hero_actions' => array_slice($actions, 0, 3),
            'kpis' => [
                $this->kpi('إجمالي التكاملات المرئية', $integrationStats['total'], 'كل الموصلات والناقلين المتاحة لهذا الدور.', 'info'),
                $this->kpi('تكاملات مفعلة', $integrationStats['enabled'], 'التكاملات المفعلة حاليًا.', 'success'),
                $this->kpi('تكاملات تحتاج متابعة', $integrationStats['attention'], 'خدمات متدهورة أو مهيأة بشكل يحتاج متابعة.', 'danger'),
                $this->kpi('واجهات الناقلين', $integrationStats['carrier'], 'عدد أسطح الناقلين المرئية لهذا الدور.', 'neutral'),
                $this->kpi('أعطال ناقلين حديثة', $this->recentCarrierFailureCount(), 'أعطال غير محلولة تم تسجيلها مؤخرًا.', 'danger'),
                $this->kpi('فحوصات صحة متدهورة', $this->degradedHealthServiceCount(), 'خدمات سجلت حالة متدهورة أو متوقفة.', 'warning'),
            ],
            'main_chart' => $this->trendCard(
                'اتجاه تنبيهات الناقلين',
                'آخر 14 يومًا من أعطال الناقلين أو صحة الخدمات المتدهورة.',
                $this->carrierAlertTrendPoints()
            ),
            'chart_cards' => array_values(array_filter([
                $this->distributionCard(
                    'توزيع حالات الصحة',
                    'الصورة الحالية لصحة التكاملات المرئية.',
                    $this->integrationStateItems($integrationRows)
                ),
                $this->distributionCard(
                    'مزيج الأسطح المرئية',
                    'كيف يتوزع العمل بين الناقلين والموصلات والبوابات.',
                    $this->integrationKindItems($integrationRows)
                ),
                $this->distributionCard(
                    'خدمات تحتاج متابعة',
                    'أكثر الخدمات ظهورًا في قائمة الانتباه.',
                    $this->integrationAttentionItems($integrationRows),
                    'health'
                ),
            ])),
            'stream_cards' => array_values(array_filter([
                $this->streamCard(
                    'تنبيهات التكاملات',
                    'التكاملات التي تحتاج متابعة حاليًا.',
                    $attentionRows->map(fn (array $row): array => [
                        'title' => (string) $row['name'],
                        'meta' => trim(implode(' • ', array_filter([
                            (string) ($row['provider_name'] ?? ''),
                            (string) ($row['type_label'] ?? ''),
                        ]))),
                        'support' => (string) data_get($row, 'health_summary.request_summary', 'لا يوجد ملخص طلبات متاح'),
                        'value' => (string) ($row['health_label'] ?? 'غير معروف'),
                        'tone' => $this->healthTone((string) ($row['health_status'] ?? 'unknown')),
                    ])->all()
                ),
                $this->streamCard('أعطال الناقلين الحديثة', 'الأخطاء غير المحلولة المرتبطة بالناقلين.', $this->recentCarrierFailureRows()),
                $this->streamCard('فحوصات الصحة المتدهورة', 'آخر الخدمات التي سجلت حالة متدهورة أو متوقفة.', $this->recentHealthAlertRows()),
            ])),
            'side_cards' => array_values(array_filter([
                $this->selectedAccountCard($user, $selectedAccount, null),
                $this->actionsCard('إجراءات سريعة', 'الوصول المباشر لأسطح إدارة الناقلين والتكاملات المتاحة لهذا الدور.', $actions),
                $this->summaryCard('نبض التكاملات', [
                    $this->summaryMetric('تنبيهات حالية', $integrationStats['attention'], 'danger'),
                    $this->summaryMetric('خدمات سليمة', $this->healthyIntegrationCount($integrationRows), 'success'),
                    $this->summaryMetric('أعطال حديثة', $this->recentCarrierFailureCount(), 'danger'),
                ]),
                $this->noteCard('وصف الدور', $roleProfile['description']),
                $hasDeprecatedRoleAssignments
                    ? $this->warningCard('تنبيه أدوار قديمة', 'تم إخفاء الدور الداخلي القديم من الواجهة النشطة. اطلب إعادة التعيين إلى أحد الأدوار الداخلية المعتمدة إذا لزم الأمر.')
                    : null,
            ])),
        ], $roleProfile, $hasDeprecatedRoleAssignments, $selectedAccount);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function adminActions(User $user, ?Account $selectedAccount, bool $canTenantContext): array
    {
        return array_values(array_filter([
            $canTenantContext
                ? $this->action(
                    'اختيار الحساب',
                    route('admin.tenant-context'),
                    'تحديد الحساب المطلوب قبل فتح أدوات المستخدمين أو الأدوار أو التقارير.',
                    'primary'
                )
                : null,
            $this->selectedAccountRouteAction(
                $selectedAccount,
                $this->canAccessAccountUsers($user),
                'admin.users',
                'مستخدمو الحساب',
                'مراجعة مستخدمي الحساب المحدد وإدارتهم من نفس السياق.',
            ),
            $this->selectedAccountRouteAction(
                $selectedAccount,
                $this->canAccessAccountRoles($user),
                'admin.roles',
                'أدوار الحساب',
                'مراجعة الأدوار والصلاحيات الداخلية للحساب المحدد.',
            ),
            $this->selectedAccountRouteAction(
                $selectedAccount,
                $this->canAccessAccountReports($user),
                'admin.reports',
                'تقارير الحساب',
                'الانتقال إلى عدسة الحساب المحدد دون فقدان السياق الحالي.',
            ),
            $this->routeActionIf(
                $this->canOpenInternalReportsHub($user),
                'internal.reports.index',
                'مركز التقارير والتحليلات',
                'لوحات القراءة الآمنة حسب نطاق كل تقرير.',
            ),
            $this->routeActionIf(
                $this->canOpenInternalShipments($user),
                'internal.shipments.index',
                'مركز الشحنات',
                'تفاصيل الشحنات والحالات التشغيلية الحالية.',
            ),
            $this->routeActionIf(
                $this->canOpenInternalTickets($user),
                'internal.tickets.index',
                'مركز التذاكر',
                'طابور الدعم والمتابعة الداخلية.',
            ),
        ]));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function operationsActions(User $user, ?Account $selectedAccount, bool $canTenantContext): array
    {
        return array_values(array_filter([
            $this->routeActionIf(
                $this->canOpenInternalTickets($user),
                'internal.tickets.index',
                'مركز التذاكر',
                'متابعة الطابور، الأولويات، وسير المعالجة.',
                'primary'
            ),
            $this->routeActionIf(
                $this->canOpenInternalShipments($user),
                'internal.shipments.index',
                'مركز الشحنات',
                'تفاصيل الرحلة التشغيلية والاستثناءات الحالية.',
            ),
            $this->routeActionIf(
                $this->canOpenInternalReportsHub($user),
                'internal.reports.index',
                'مركز التقارير',
                'تقارير قراءة آمنة حسب صلاحيات الدور.',
            ),
            $this->routeActionIf(
                $this->canOpenInternalKyc($user),
                'internal.kyc.index',
                'مركز التحقق',
                'الطلبات المعلقة وحالات KYC الحالية.',
            ),
            $this->routeActionIf(
                $this->canOpenInternalIntegrations($user),
                'internal.integrations.index',
                'مركز التكاملات',
                'متابعة صحة الموصلات والواجهات.',
            ),
            $this->routeActionIf(
                $this->canOpenInternalCarriers($user),
                'internal.carriers.index',
                'إدارة الناقلين',
                'مراجعة الناقلين وحالة التكاملات المرتبطة بهم.',
            ),
            $this->routeActionIf(
                $this->canOpenInternalStaff($user),
                'internal.staff.index',
                'فريق المنصة',
                'استعراض فريق التشغيل الداخلي عند الحاجة.',
            ),
            $canTenantContext
                ? $this->action(
                    'اختيار الحساب',
                    route('internal.tenant-context'),
                    'تحديد حساب للعمل الحالي عندما يسمح الدور بذلك.',
                )
                : null,
            $selectedAccount && $this->canAccessAccountReports($user)
                ? $this->action(
                    'تقارير الحساب المحدد',
                    route('admin.reports'),
                    'الانتقال إلى عدسة الحساب المحدد الحالية.',
                )
                : null,
        ]));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function carrierManagerActions(User $user, ?Account $selectedAccount): array
    {
        return array_values(array_filter([
            $this->routeActionIf(
                $this->canOpenInternalCarriers($user),
                'internal.carriers.index',
                'إدارة الناقلين',
                'اللوحة الأساسية لمتابعة الناقلين.',
                'primary'
            ),
            $this->routeActionIf(
                $this->canOpenInternalIntegrations($user),
                'internal.integrations.index',
                'مركز التكاملات',
                'تفاصيل الموصلات والتنبيهات الصحية الحالية.',
            ),
            $this->routeActionIf(
                $this->canOpenSmtpSettings($user),
                'internal.smtp-settings.edit',
                'إعدادات SMTP',
                'مراجعة جاهزية البريد الداخلي المرتبط بالناقلين والتنبيهات.',
            ),
            $selectedAccount && $this->canAccessAccountReports($user)
                ? $this->action(
                    'تقارير الحساب المحدد',
                    route('admin.reports'),
                    'إذا كان هناك حساب محدد سابقًا فستظهر هنا عدسته المخصصة.',
                )
                : null,
        ]));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function selectedAccountCard(User $user, ?Account $selectedAccount, ?string $tenantContextUrl): ?array
    {
        if (! $selectedAccount instanceof Account) {
            return [
                'type' => 'context',
                'state' => 'empty',
                'title' => 'عدسة الحساب المحدد',
                'description' => 'يمكنك مواصلة متابعة المنصة دون حساب محدد. عند الحاجة إلى أدوات عميل بعينه سيظهر ملخصه هنا.',
                'cta' => $tenantContextUrl ? [
                    'label' => 'اختيار حساب للعمل الحالي',
                    'href' => $tenantContextUrl,
                ] : null,
            ];
        }

        $account = Account::query()
            ->withoutGlobalScopes()
            ->with(['kycVerification', 'wallet', 'billingWallet'])
            ->find((string) $selectedAccount->id);

        if (! $account instanceof Account) {
            return null;
        }

        $metrics = array_values(array_filter([
            $this->detailMetric('نوع الحساب', $account->isOrganization() ? 'منظمة' : 'فردي'),
            $this->canOpenInternalKyc($user) || $this->canAccessAccountReports($user)
                ? $this->detailMetric('حالة التحقق', $this->accountKycLabel($account))
                : null,
            $this->canOpenInternalShipments($user) || $this->canAccessAccountReports($user)
                ? $this->detailMetric('الشحنات', number_format($this->shipmentCountForAccount($account)))
                : null,
            $this->canAccessAccountUsers($user)
                ? $this->detailMetric('المستخدمون', number_format($this->userCountForAccount($account)))
                : null,
            $this->canAccessAccountRoles($user)
                ? $this->detailMetric('الأدوار', number_format($this->roleCountForAccount($account)))
                : null,
            $this->canViewWalletBalance($user)
                ? $this->detailMetric('الرصيد', $this->accountWalletBalance($account))
                : null,
        ]));

        return [
            'type' => 'context',
            'state' => 'selected',
            'title' => 'عدسة الحساب المحدد',
            'account_name' => (string) $account->name,
            'description' => 'يبقى هذا الملخص سياقًا مساعدًا داخل اللوحة، دون تحويل الصفحة كلها إلى تقرير عميل واحد.',
            'metrics' => $metrics,
            'mini_trend' => $this->trendCard(
                'اتجاه شحنات الحساب',
                'آخر 7 أيام للحساب المحدد.',
                $this->shipmentTrendPoints((string) $account->id, 7)
            ),
            'mini_distribution' => $this->distributionCard(
                'حالة شحنات الحساب',
                'التوزيع الحالي داخل الحساب المحدد.',
                $this->shipmentStatusItems((string) $account->id)
            ),
            'rows' => $this->recentShipmentRows((string) $account->id, 3),
            'cta' => $tenantContextUrl ? [
                'label' => 'تبديل الحساب',
                'href' => $tenantContextUrl,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dashboardIfVisible(User $user, string $domain, string $surface): ?array
    {
        if (! $this->canOpenReportDashboard($user, $surface)) {
            return null;
        }

        $dashboard = $this->reportDashboardService->dashboard($domain, $user);

        return is_array($dashboard) ? $dashboard : null;
    }

    private function canOpenReportDashboard(User $user, string $surface): bool
    {
        return $user->hasPermission('reports.read')
            && $user->hasPermission('analytics.read')
            && $this->controlPlane->canSeeSurface($user, $surface);
    }

    private function canOpenInternalReportsHub(User $user): bool
    {
        return Route::has('internal.reports.index')
            && $user->hasPermission('reports.read')
            && $user->hasPermission('analytics.read')
            && $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_REPORTS_INDEX);
    }

    private function canOpenInternalShipments(User $user): bool
    {
        return Route::has('internal.shipments.index')
            && $user->hasPermission('shipments.read')
            && $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_SHIPMENTS_INDEX);
    }

    private function canOpenInternalTickets(User $user): bool
    {
        return Route::has('internal.tickets.index')
            && $user->hasPermission('tickets.read')
            && $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_TICKETS_INDEX);
    }

    private function canOpenInternalKyc(User $user): bool
    {
        return Route::has('internal.kyc.index')
            && $user->hasPermission('kyc.read')
            && $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_KYC_INDEX);
    }

    private function canOpenInternalIntegrations(User $user): bool
    {
        return Route::has('internal.integrations.index')
            && $user->hasPermission('integrations.read')
            && $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_INTEGRATIONS_INDEX);
    }

    private function canOpenInternalCarriers(User $user): bool
    {
        return Route::has('internal.carriers.index')
            && $user->hasPermission('integrations.read')
            && $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_CARRIERS_INDEX);
    }

    private function canOpenInternalStaff(User $user): bool
    {
        return Route::has('internal.staff.index')
            && $user->hasPermission('users.read')
            && $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_STAFF_INDEX);
    }

    private function canOpenSmtpSettings(User $user): bool
    {
        return Route::has('internal.smtp-settings.edit')
            && $user->hasPermission('notifications.channels.manage')
            && $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_SMTP_SETTINGS);
    }

    private function canSelectTenantContext(User $user): bool
    {
        return $user->hasPermission('tenancy.context.select')
            && $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_TENANT_CONTEXT);
    }

    private function canAccessAccountUsers(User $user): bool
    {
        return Route::has('admin.users')
            && $user->hasPermission('users.read')
            && $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_ACCOUNT_USERS);
    }

    private function canAccessAccountRoles(User $user): bool
    {
        return Route::has('admin.roles')
            && $user->hasPermission('roles.read')
            && $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_ACCOUNT_ROLES);
    }

    private function canAccessAccountReports(User $user): bool
    {
        return Route::has('admin.reports')
            && $user->hasPermission('reports.read')
            && $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_ACCOUNT_REPORTS);
    }

    private function canViewWalletBalance(User $user): bool
    {
        return $user->hasPermission('wallet.balance');
    }

    private function totalAccounts(): int
    {
        return Account::query()->withoutGlobalScopes()->count();
    }

    private function totalUsers(): int
    {
        return User::query()->withoutGlobalScopes()->count();
    }

    private function totalShipments(): int
    {
        return Shipment::query()->withoutGlobalScopes()->count();
    }

    private function activeShipmentCount(?string $accountId = null): int
    {
        return $this->shipmentQuery($accountId)
            ->whereIn('status', self::ACTIVE_SHIPMENT_STATUSES)
            ->count();
    }

    private function exceptionShipmentCount(?string $accountId = null): int
    {
        if (Schema::hasTable('shipment_exceptions')) {
            $query = ShipmentException::query()->withoutGlobalScopes()->open();

            if ($accountId !== null && Schema::hasColumn('shipment_exceptions', 'account_id')) {
                $query->where('account_id', $accountId);
            }

            return $query->count();
        }

        return $this->shipmentQuery($accountId)
            ->whereIn('status', self::ATTENTION_SHIPMENT_STATUSES)
            ->count();
    }

    private function deliveredTodayCount(?string $accountId = null): int
    {
        $query = $this->shipmentQuery($accountId)->where('status', Shipment::STATUS_DELIVERED);

        if (Schema::hasColumn('shipments', 'delivered_at')) {
            return $query->whereDate('delivered_at', now()->toDateString())->count();
        }

        return $query->whereDate('updated_at', now()->toDateString())->count();
    }

    private function shipmentCountForAccount(Account $account): int
    {
        return $this->shipmentQuery((string) $account->id)->count();
    }

    private function userCountForAccount(Account $account): int
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $account->id)
            ->count();
    }

    private function roleCountForAccount(Account $account): int
    {
        if (! Schema::hasColumn('roles', 'account_id')) {
            return 0;
        }

        return Role::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $account->id)
            ->count();
    }

    private function openTicketCount(User $user): int
    {
        return (int) ($this->ticketReadService->stats($user)['open'] ?? 0);
    }

    private function urgentTicketCount(): int
    {
        return SupportTicket::query()
            ->withoutGlobalScopes()
            ->where('priority', 'urgent')
            ->whereIn('status', ['open', 'in_progress', 'waiting_customer', 'waiting_agent'])
            ->count();
    }

    private function pendingAndRejectedKycCount(): int
    {
        return $this->pendingKycCount() + $this->rejectedKycCount();
    }

    private function pendingKycCount(): int
    {
        return $this->countAccountsForVerificationStatus(KycVerification::STATUS_PENDING);
    }

    private function rejectedKycCount(): int
    {
        return $this->countAccountsForVerificationStatus(KycVerification::STATUS_REJECTED);
    }

    private function countAccountsForVerificationStatus(string $status): int
    {
        $accounts = Account::query()->withoutGlobalScopes();

        if (Schema::hasColumn('accounts', 'kyc_status')) {
            return $accounts
                ->where('kyc_status', AccountKycStatusMapper::fromVerificationStatus($status))
                ->count();
        }

        if (! Schema::hasTable('kyc_verifications')) {
            return 0;
        }

        return $accounts->whereHas('kycVerifications', static function (Builder $query) use ($status): void {
            $query->where('status', $status);
        })->count();
    }

    private function activeHoldCount(): int
    {
        if (! Schema::hasTable('wallet_holds')) {
            return 0;
        }

        return WalletHold::query()
            ->withoutGlobalScopes()
            ->where('status', WalletHold::STATUS_ACTIVE)
            ->count();
    }

    private function lowBalanceWalletCount(): int
    {
        if (! Schema::hasTable('billing_wallets')) {
            return 0;
        }

        return BillingWallet::query()
            ->withoutGlobalScopes()
            ->whereNotNull('low_balance_threshold')
            ->whereColumn('available_balance', '<', 'low_balance_threshold')
            ->count();
    }

    private function integrationAttentionCount(User $user): int
    {
        return (int) ($this->integrationReadService->stats($user)['attention'] ?? 0);
    }

    private function degradedHealthServiceCount(): int
    {
        return collect($this->latestIntegrationHealthLogs())
            ->whereIn('status', [IntegrationHealthLog::STATUS_DEGRADED, IntegrationHealthLog::STATUS_DOWN])
            ->count();
    }

    private function recentCarrierFailureCount(): int
    {
        if (! Schema::hasTable('carrier_errors')) {
            return 0;
        }

        return CarrierError::query()
            ->withoutGlobalScopes()
            ->where('was_resolved', false)
            ->where('created_at', '>=', now()->subDay())
            ->count();
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    private function shipmentTrendPoints(?string $accountId = null, int $days = 14): array
    {
        return $this->dailyTrend($this->shipmentQuery($accountId), 'created_at', $days);
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    private function carrierAlertTrendPoints(): array
    {
        if (Schema::hasTable('carrier_errors')) {
            return $this->dailyTrend(
                CarrierError::query()->withoutGlobalScopes()->where('was_resolved', false),
                'created_at',
                14
            );
        }

        if (! Schema::hasTable('integration_health_logs')) {
            return $this->emptyTrend(14);
        }

        return $this->dailyTrend(
            IntegrationHealthLog::query()
                ->whereIn('status', [IntegrationHealthLog::STATUS_DEGRADED, IntegrationHealthLog::STATUS_DOWN]),
            'checked_at',
            14
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function shipmentStatusItems(?string $accountId = null): array
    {
        $query = $this->shipmentQuery($accountId);

        return [
            $this->distributionItem('قيد التنفيذ', (clone $query)->whereIn('status', self::ACTIVE_SHIPMENT_STATUSES)->count(), null, 'success'),
            $this->distributionItem('تحتاج متابعة', (clone $query)->whereIn('status', self::ATTENTION_SHIPMENT_STATUSES)->count(), null, 'danger'),
            $this->distributionItem('تم التسليم', (clone $query)->where('status', Shipment::STATUS_DELIVERED)->count(), null, 'info'),
            $this->distributionItem('موقوفة بسبب KYC', (clone $query)->where('status', Shipment::STATUS_KYC_BLOCKED)->count(), null, 'warning'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function carrierMixItems(?string $accountId = null): array
    {
        return $this->shipmentQuery($accountId)
            ->get()
            ->groupBy(fn (Shipment $shipment): string => $this->carrierLabel($shipment))
            ->map(function (Collection $group, string $label): array {
                $count = $group->count();
                $delivered = $group->where('status', Shipment::STATUS_DELIVERED)->count();
                $rate = $count > 0 ? round(($delivered / $count) * 100) : 0;

                return $this->distributionItem(
                    $label,
                    $count,
                    sprintf('معدل التسليم %s%%', number_format($rate)),
                    $rate >= 80 ? 'success' : ($rate >= 50 ? 'warning' : 'danger')
                );
            })
            ->sortByDesc('value')
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $dashboard
     * @return array<int, array<string, mixed>>
     */
    private function distributionItemsFromDashboard(?array $dashboard, int $breakdownIndex): array
    {
        $items = data_get($dashboard, 'breakdowns.'.$breakdownIndex.'.items', []);

        if (! is_array($items)) {
            return [];
        }

        return collect($items)->map(function (array $item): array {
            return $this->distributionItem(
                (string) ($item['label'] ?? 'غير معروف'),
                (int) ($item['value'] ?? 0),
                (string) ($item['detail'] ?? ''),
                'info',
                $item['display'] ?? null
            );
        })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function integrationHealthItems(): array
    {
        return collect($this->latestIntegrationHealthLogs())
            ->whereIn('status', [IntegrationHealthLog::STATUS_DEGRADED, IntegrationHealthLog::STATUS_DOWN, IntegrationHealthLog::STATUS_HEALTHY])
            ->take(6)
            ->map(function (IntegrationHealthLog $log): array {
                $detail = trim(implode(' • ', array_filter([
                    $log->response_time_ms !== null ? number_format((int) $log->response_time_ms).' ms' : null,
                    $log->failed_requests !== null ? number_format((int) $log->failed_requests).' طلب فاشل' : null,
                ])));

                return $this->distributionItem(
                    $this->integrationServiceLabel((string) $log->service),
                    (int) ($log->total_requests ?? 0),
                    $detail !== '' ? $detail : 'لا توجد تفاصيل إضافية متاحة.',
                    $this->healthTone((string) $log->status),
                    $this->healthLabel((string) $log->status)
                );
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function integrationStateItems(Collection $rows): array
    {
        return [
            $this->distributionItem('سليمة', $rows->where('health_status', IntegrationHealthLog::STATUS_HEALTHY)->count(), null, 'success'),
            $this->distributionItem('متدهورة', $rows->where('health_status', IntegrationHealthLog::STATUS_DEGRADED)->count(), null, 'warning'),
            $this->distributionItem('متوقفة', $rows->where('health_status', IntegrationHealthLog::STATUS_DOWN)->count(), null, 'danger'),
            $this->distributionItem('غير مصنفة', $rows->where('health_status', 'unknown')->count(), null, 'neutral'),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function integrationKindItems(Collection $rows): array
    {
        return [
            $this->distributionItem('واجهات الناقلين', $rows->where('kind', 'carrier')->count(), null, 'info'),
            $this->distributionItem('موصلات المتاجر', $rows->where('kind', 'store')->count(), null, 'success'),
            $this->distributionItem('بوابات الدفع', $rows->where('kind', 'gateway')->count(), null, 'neutral'),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function integrationAttentionItems(Collection $rows): array
    {
        return $rows
            ->where('needs_attention', true)
            ->groupBy('provider_name')
            ->map(fn (Collection $group, string $provider): array => $this->distributionItem(
                $provider !== '' ? $provider : 'غير معروف',
                $group->count(),
                'تكاملات ضمن قائمة المتابعة لهذا المزود.',
                'warning'
            ))
            ->sortByDesc('value')
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentShipmentRows(?string $accountId = null, int $limit = 6): array
    {
        return $this->shipmentQuery($accountId)
            ->with('account')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function (Shipment $shipment) use ($accountId): array {
                $meta = trim(implode(' • ', array_filter([
                    $accountId === null ? (string) ($shipment->account?->name ?? '') : null,
                    $this->carrierLabel($shipment),
                    $this->displayDateTime($shipment->created_at),
                ])));

                return [
                    'title' => (string) ($shipment->reference_number ?: $shipment->tracking_number ?: $shipment->id),
                    'meta' => $meta,
                    'support' => $this->shipmentStatusLabel((string) $shipment->status),
                    'value' => $this->shipmentStatusLabel((string) $shipment->status),
                    'tone' => $this->shipmentStatusTone((string) $shipment->status),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentExceptionRows(?string $accountId = null, int $limit = 6): array
    {
        if (! Schema::hasTable('shipment_exceptions')) {
            return [];
        }

        $query = ShipmentException::query()
            ->withoutGlobalScopes()
            ->with(['shipment.account'])
            ->open()
            ->orderByDesc('created_at');

        if ($accountId !== null && Schema::hasColumn('shipment_exceptions', 'account_id')) {
            $query->where('account_id', $accountId);
        }

        return $query->limit($limit)->get()->map(function (ShipmentException $exception): array {
            $shipment = $exception->shipment;
            $meta = trim(implode(' • ', array_filter([
                $shipment?->account?->name,
                $this->displayDateTime($exception->created_at),
            ])));

            return [
                'title' => $shipment instanceof Shipment
                    ? (string) ($shipment->reference_number ?: $shipment->tracking_number ?: $shipment->id)
                    : 'شحنة غير معروفة',
                'meta' => $meta,
                'support' => Str::limit((string) ($exception->reason ?: $exception->carrier_reason ?: 'استثناء تشغيلي'), 100),
                'value' => $this->exceptionPriorityLabel((string) $exception->priority),
                'tone' => $this->exceptionTone((string) $exception->priority),
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pendingKycRows(int $limit = 6): array
    {
        if (! Schema::hasTable('kyc_verifications')) {
            return [];
        }

        return KycVerification::query()
            ->withoutGlobalScopes()
            ->with('account')
            ->whereIn('status', [KycVerification::STATUS_PENDING, KycVerification::STATUS_REJECTED])
            ->orderByDesc('submitted_at')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(function (KycVerification $verification): array {
                return [
                    'title' => (string) ($verification->account?->name ?? 'حساب غير معروف'),
                    'meta' => trim(implode(' • ', array_filter([
                        $verification->account?->isOrganization() ? 'منظمة' : 'فردي',
                        $this->displayDateTime($verification->submitted_at ?: $verification->updated_at),
                    ]))),
                    'support' => (string) ($verification->rejection_reason ?: $verification->review_notes ?: 'طلب تحقق ينتظر الحسم.'),
                    'value' => (string) data_get($verification->statusDisplay(), 'label', $verification->status),
                    'tone' => $verification->status === KycVerification::STATUS_REJECTED ? 'danger' : 'warning',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentFailureRows(int $limit = 6): array
    {
        return collect()
            ->merge($this->recentCarrierFailureRows((int) ceil($limit / 2)))
            ->merge($this->recentHealthAlertRows((int) ceil($limit / 2)))
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentCarrierFailureRows(int $limit = 6): array
    {
        if (! Schema::hasTable('carrier_errors')) {
            return [];
        }

        return CarrierError::query()
            ->withoutGlobalScopes()
            ->where('was_resolved', false)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function (CarrierError $error): array {
                return [
                    'title' => Str::upper((string) $error->carrier_code),
                    'meta' => trim(implode(' • ', array_filter([
                        $this->headline((string) $error->operation),
                        $this->displayDateTime($error->created_at),
                    ]))),
                    'support' => Str::limit((string) ($error->internal_message ?: $error->carrier_error_message ?: 'خطأ ناقل غير مصنف'), 100),
                    'value' => (string) ($error->carrier_error_code ?: 'ERR'),
                    'tone' => 'danger',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentHealthAlertRows(int $limit = 6): array
    {
        return collect($this->latestIntegrationHealthLogs())
            ->whereIn('status', [IntegrationHealthLog::STATUS_DEGRADED, IntegrationHealthLog::STATUS_DOWN])
            ->sortByDesc(fn (IntegrationHealthLog $log): int => $log->checked_at?->getTimestamp() ?? 0)
            ->take($limit)
            ->map(function (IntegrationHealthLog $log): array {
                return [
                    'title' => $this->integrationServiceLabel((string) $log->service),
                    'meta' => trim(implode(' • ', array_filter([
                        $this->displayDateTime($log->checked_at),
                        $log->response_time_ms !== null ? number_format((int) $log->response_time_ms).' ms' : null,
                    ]))),
                    'support' => (string) ($log->error_message ?: 'تم تسجيل حالة متدهورة أو متوقفة لهذه الخدمة.'),
                    'value' => $this->healthLabel((string) $log->status),
                    'tone' => $this->healthTone((string) $log->status),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, IntegrationHealthLog>
     */
    private function latestIntegrationHealthLogs(): Collection
    {
        if (! Schema::hasTable('integration_health_logs')) {
            return collect();
        }

        return IntegrationHealthLog::query()
            ->orderByDesc('checked_at')
            ->get()
            ->unique('service')
            ->values();
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    private function dailyTrend(Builder $query, string $column, int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $rows = (clone $query)
            ->where($column, '>=', $start)
            ->get([$column]);

        $counts = $rows
            ->map(function (object $row) use ($column): ?string {
                $value = data_get($row, $column);

                if ($value === null) {
                    return null;
                }

                return Carbon::parse($value)->toDateString();
            })
            ->filter()
            ->countBy();

        return collect(range(0, $days - 1))
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
    private function emptyTrend(int $days): array
    {
        return collect(range(0, $days - 1))
            ->map(function (int $offset) use ($days): array {
                $date = now()->subDays(($days - 1) - $offset);

                return [
                    'label' => $date->format('d/m'),
                    'value' => 0,
                ];
            })
            ->all();
    }

    private function shipmentQuery(?string $accountId = null): Builder
    {
        $query = Shipment::query()->withoutGlobalScopes();

        if ($accountId !== null) {
            $query->where('account_id', $accountId);
        }

        return $query;
    }

    private function accountWalletBalance(Account $account): string
    {
        $balance = null;
        $currency = null;

        if (Schema::hasTable('billing_wallets') && $account->billingWallet) {
            $balance = (float) $account->billingWallet->available_balance;
            $currency = (string) ($account->billingWallet->currency ?? '');
        } elseif (Schema::hasTable('wallets') && $account->wallet) {
            $balance = (float) $account->wallet->available_balance;
            $currency = (string) ($account->wallet->currency ?? '');
        } else {
            $wallet = BillingWallet::query()->withoutGlobalScopes()->where('account_id', (string) $account->id)->first();

            if ($wallet instanceof BillingWallet) {
                $balance = (float) $wallet->available_balance;
                $currency = (string) ($wallet->currency ?? '');
            } else {
                $legacyWallet = Wallet::query()->withoutGlobalScopes()->where('account_id', (string) $account->id)->first();

                if ($legacyWallet instanceof Wallet) {
                    $balance = (float) $legacyWallet->available_balance;
                    $currency = (string) ($legacyWallet->currency ?? '');
                }
            }
        }

        if ($balance === null) {
            return 'غير متاح';
        }

        return trim(number_format($balance, 2).($currency !== '' ? ' '.Str::upper($currency) : ''));
    }

    private function accountKycLabel(Account $account): string
    {
        if ($account->kycVerification instanceof KycVerification) {
            return (string) data_get($account->kycVerification->statusDisplay(), 'label', 'غير معروف');
        }

        if (Schema::hasColumn('accounts', 'kyc_status')) {
            return match ((string) $account->kyc_status) {
                AccountKycStatusMapper::STATUS_VERIFIED => 'مقبول',
                AccountKycStatusMapper::STATUS_PENDING => 'قيد المراجعة',
                AccountKycStatusMapper::STATUS_REJECTED => 'مرفوض',
                default => 'غير موثق',
            };
        }

        return 'غير متاح';
    }

    private function carrierLabel(Shipment $shipment): string
    {
        $label = trim((string) ($shipment->carrier_name ?: $shipment->carrier_code));

        return $label !== '' ? $label : 'شركة شحن غير معروفة';
    }

    private function healthTone(string $status): string
    {
        return match ($status) {
            IntegrationHealthLog::STATUS_HEALTHY => 'success',
            IntegrationHealthLog::STATUS_DEGRADED => 'warning',
            IntegrationHealthLog::STATUS_DOWN => 'danger',
            default => 'neutral',
        };
    }

    private function healthLabel(string $status): string
    {
        return match ($status) {
            IntegrationHealthLog::STATUS_HEALTHY => 'سليمة',
            IntegrationHealthLog::STATUS_DEGRADED => 'متدهورة',
            IntegrationHealthLog::STATUS_DOWN => 'متوقفة',
            default => 'غير معروفة',
        };
    }

    private function exceptionTone(string $priority): string
    {
        return match ($priority) {
            ShipmentException::PRIORITY_CRITICAL,
            ShipmentException::PRIORITY_HIGH => 'danger',
            ShipmentException::PRIORITY_MEDIUM => 'warning',
            default => 'neutral',
        };
    }

    private function exceptionPriorityLabel(string $priority): string
    {
        return match ($priority) {
            ShipmentException::PRIORITY_CRITICAL => 'حرجة',
            ShipmentException::PRIORITY_HIGH => 'مرتفعة',
            ShipmentException::PRIORITY_MEDIUM => 'متوسطة',
            ShipmentException::PRIORITY_LOW => 'منخفضة',
            default => 'غير مصنفة',
        };
    }

    private function shipmentStatusTone(string $status): string
    {
        return match ($status) {
            Shipment::STATUS_DELIVERED => 'success',
            Shipment::STATUS_REQUIRES_ACTION,
            Shipment::STATUS_EXCEPTION,
            Shipment::STATUS_FAILED => 'danger',
            Shipment::STATUS_KYC_BLOCKED => 'warning',
            default => 'info',
        };
    }

    private function shipmentStatusLabel(string $status): string
    {
        return match ($status) {
            Shipment::STATUS_PURCHASED => 'تم الشراء',
            Shipment::STATUS_READY_FOR_PICKUP => 'جاهزة للاستلام',
            Shipment::STATUS_PICKED_UP => 'تم الاستلام',
            Shipment::STATUS_IN_TRANSIT => 'في الطريق',
            Shipment::STATUS_OUT_FOR_DELIVERY => 'خرجت للتسليم',
            Shipment::STATUS_DELIVERED => 'تم التسليم',
            Shipment::STATUS_REQUIRES_ACTION => 'تحتاج إجراء',
            Shipment::STATUS_EXCEPTION => 'استثناء',
            Shipment::STATUS_FAILED => 'فشلت',
            Shipment::STATUS_KYC_BLOCKED => 'محجوبة بسبب KYC',
            default => $this->headline($status),
        };
    }

    private function integrationServiceLabel(string $service): string
    {
        $service = trim($service);

        if ($service === '') {
            return 'خدمة غير معروفة';
        }

        $normalized = str_replace(['carrier:', 'store:', 'gateway:'], '', strtolower($service));

        if (str_starts_with($service, 'carrier:')) {
            return 'الناقل '.Str::headline($normalized);
        }

        if (str_starts_with($service, 'store:')) {
            return 'المتجر '.Str::headline($normalized);
        }

        if (str_starts_with($service, 'gateway:')) {
            return 'بوابة الدفع '.Str::headline($normalized);
        }

        return Str::headline($service);
    }

    /**
     * @return array<string, mixed>
     */
    private function trendCard(string $title, string $summary, array $points): array
    {
        return [
            'title' => $title,
            'summary' => $summary,
            'type' => 'trend',
            'points' => $points,
            'empty_title' => 'لا توجد بيانات كافية',
            'empty_body' => 'ستظهر المؤشرات الزمنية هنا فور توفر بيانات ضمن النطاق الحالي.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function distributionCard(string $title, string $summary, array $items, string $type = 'distribution'): array
    {
        return [
            'title' => $title,
            'summary' => $summary,
            'type' => $type,
            'items' => $items,
            'empty_title' => 'لا توجد عناصر معروضة',
            'empty_body' => 'سيمتلئ هذا القسم تلقائيًا عند توفر بيانات للنطاق الحالي.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function streamCard(string $title, string $summary, array $rows): array
    {
        return [
            'title' => $title,
            'summary' => $summary,
            'rows' => $rows,
            'empty_title' => 'لا توجد عناصر حالية',
            'empty_body' => 'عند ظهور نشاط جديد سيظهر هنا ضمن هذا القسم.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function actionsCard(string $title, string $summary, array $actions): array
    {
        return [
            'type' => 'actions',
            'title' => $title,
            'summary' => $summary,
            'items' => $actions,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function summaryCard(string $title, array $items): array
    {
        return [
            'type' => 'summary',
            'title' => $title,
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function noteCard(string $title, string $body): array
    {
        return [
            'type' => 'note',
            'title' => $title,
            'body' => $body,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function warningCard(string $title, string $body): array
    {
        return [
            'type' => 'warning',
            'title' => $title,
            'body' => $body,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function kpi(string $label, int|float $value, string $hint, string $tone = 'info'): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'display' => is_float($value) ? number_format($value, 2) : number_format((int) $value),
            'hint' => $hint,
            'tone' => self::TONE_MAP[$tone] ?? 'info',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function pill(string $label, string $tone): array
    {
        return [
            'label' => $label,
            'tone' => self::TONE_MAP[$tone] ?? 'neutral',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function detailMetric(string $label, ?string $value): array
    {
        return [
            'label' => $label,
            'value' => $value ?? 'غير متاح',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function summaryMetric(string $label, int $value, string $tone): array
    {
        return [
            'label' => $label,
            'value' => number_format($value),
            'tone' => self::TONE_MAP[$tone] ?? 'neutral',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function distributionItem(string $label, int|float $value, ?string $detail = null, string $tone = 'info', string|int|float|null $display = null): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'display' => $display ?? (is_float($value) ? number_format($value, 2) : number_format((int) $value)),
            'detail' => $detail,
            'tone' => self::TONE_MAP[$tone] ?? 'info',
        ];
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @param  array{name: string|null, label: string, description: string, landing_route: string}  $roleProfile
     * @return array<string, mixed>
     */
    private function withLegacyShellRoleContext(
        array $dashboard,
        array $roleProfile,
        bool $hasDeprecatedRoleAssignments,
        ?Account $selectedAccount,
    ): array {
        $roleTags = array_values(array_filter([
            (string) ($roleProfile['label'] ?? 'Internal'),
            $selectedAccount instanceof Account ? 'Account: '.$selectedAccount->name : 'Platform-wide context',
            $hasDeprecatedRoleAssignments ? 'Legacy internal role hidden from active shell' : null,
        ]));

        return $dashboard + [
            'role_badge' => (string) ($roleProfile['label'] ?? 'Internal'),
            'role_description' => (string) ($roleProfile['description'] ?? ''),
            'role_tags' => $roleTags,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function routeActionIf(
        bool $allowed,
        string $routeName,
        string $label,
        string $description,
        string $variant = 'secondary',
    ): ?array {
        if (! $allowed || ! Route::has($routeName)) {
            return null;
        }

        return $this->action($label, route($routeName), $description, $variant);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function selectedAccountRouteAction(
        ?Account $selectedAccount,
        bool $allowed,
        string $routeName,
        string $label,
        string $description,
    ): ?array {
        if (! $allowed) {
            return null;
        }

        if (! $selectedAccount instanceof Account) {
            return [
                'label' => $label,
                'description' => 'يتطلب هذا الإجراء اختيار حساب أولًا.',
                'variant' => 'disabled',
                'href' => null,
            ];
        }

        if (! Route::has($routeName)) {
            return null;
        }

        return $this->action($label, route($routeName), $description);
    }

    /**
     * @return array<string, mixed>
     */
    private function action(string $label, string $href, string $description, string $variant = 'secondary'): array
    {
        return [
            'label' => $label,
            'href' => $href,
            'description' => $description,
            'variant' => $variant,
        ];
    }

    private function healthyIntegrationCount(Collection $rows): int
    {
        return $rows->where('health_status', IntegrationHealthLog::STATUS_HEALTHY)->count();
    }

    private function displayDateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i');
        } catch (\Throwable) {
            return is_string($value) && trim($value) !== '' ? trim($value) : null;
        }
    }

    private function headline(string $value): string
    {
        $value = trim($value);

        return $value !== '' ? Str::headline($value) : 'غير معروف';
    }
}
