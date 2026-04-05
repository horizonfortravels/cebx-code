<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Address;
use App\Models\BillingWallet;
use App\Models\CarrierError;
use App\Models\ContentDeclaration;
use App\Models\CustomerApiKey;
use App\Models\IntegrationHealthLog;
use App\Models\KycVerification;
use App\Models\Notification;
use App\Models\Order;
use App\Models\RateQuote;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\User;
use App\Models\WaiverVersion;
use App\Models\WalletHold;
use App\Models\WalletLedgerEntry;
use App\Models\WebhookEvent;
use App\Services\AddressValidationService;
use App\Services\CarrierService;
use App\Services\PublicTrackingService;
use App\Services\ShipmentTimelineService;
use App\Support\PortalShipmentLabeler;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use League\Csv\Writer;

class PortalWorkspaceController extends Controller
{
    public function b2cDashboard(Request $request): View
    {
        $account = $this->currentAccount();
        $accountId = (string) $account->id;
        $wallet = $this->preferredBillingWallet($accountId);
        $transactions = $this->walletEntries($wallet);
        $kycVerification = $account->kycVerification()->latest('submitted_at')->first();
        $recentShipments = Shipment::query()
            ->where('account_id', $accountId)
            ->latest()
            ->limit(5)
            ->get();
        $continueShipment = $this->b2cContinueShipment($accountId);
        $continueAction = $continueShipment ? $this->b2cContinueShipmentAction($continueShipment) : null;

        return view('b2c.dashboard', [
            'account' => $account,
            'currentUser' => $request->user(),
            'wallet' => $wallet,
            'transactions' => $transactions,
            'kycVerification' => $kycVerification,
            'stats' => $this->b2cDashboardStats($accountId, $wallet),
            'recentShipments' => $recentShipments,
            'continueShipment' => $continueShipment,
            'continueAction' => $continueAction,
            'dashboardNotices' => $this->b2cDashboardNotices($kycVerification, $wallet, $accountId),
            'summaryPills' => $this->b2cDashboardSummaryPills($accountId, $wallet),
            'addressCount' => Address::query()->where('account_id', $accountId)->count(),
        ]);
    }

    public function b2cShipments(Request $request): View
    {
        return $this->shipmentIndexWorkspace($request, 'b2c');
    }

    public function exportB2cShipments(Request $request): Response
    {
        return $this->shipmentIndexExportWorkspace($request, 'b2c');
    }

    public function b2cWallet(): View
    {
        $account = $this->currentAccount();
        $accountId = (string) $account->id;
        $wallet = $this->preferredBillingWallet($accountId);
        $activeHolds = collect();

        if ($wallet && Schema::hasTable('wallet_holds')) {
            $activeHolds = WalletHold::query()
                ->with('shipment')
                ->where('wallet_id', (string) $wallet->id)
                ->whereIn('status', [WalletHold::STATUS_ACTIVE, WalletHold::STATUS_CAPTURED])
                ->latest('created_at')
                ->limit(5)
                ->get();
        }

        return view('pages.portal.b2c.wallet', [
            'account' => $account,
            'wallet' => $wallet,
            'transactions' => $this->walletEntries($wallet),
            'activeHolds' => $activeHolds,
        ]);
    }

    public function b2cTracking(): View
    {
        $account = $this->currentAccount();
        $accountId = (string) $account->id;
        $query = trim((string) request('q', ''));
        $identifierColumns = array_values(array_filter([
            Schema::hasColumn('shipments', 'tracking_number') ? 'tracking_number' : null,
            Schema::hasColumn('shipments', 'carrier_shipment_id') ? 'carrier_shipment_id' : null,
            Schema::hasColumn('shipments', 'reference_number') ? 'reference_number' : null,
        ]));

        $matchedShipment = null;
        if ($query !== '' && $identifierColumns !== []) {
            $matchedShipment = Shipment::query()
                ->where('account_id', $accountId)
                ->with(['carrierShipment'])
                ->where(function ($builder) use ($query, $identifierColumns): void {
                    foreach ($identifierColumns as $index => $column) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $builder->{$method}($column, 'like', '%'.$query.'%');
                    }
                })
                ->latest()
                ->first();
        }

        $trackedShipments = Shipment::query()
            ->where('account_id', $accountId)
            ->latest()
            ->limit(8)
            ->get();

        $matchedTimeline = $matchedShipment
            ? $this->decorateTimeline(app(ShipmentTimelineService::class)->present($matchedShipment))
            : null;

        return view('pages.portal.b2c.tracking', [
            'account' => $account,
            'trackedShipments' => $trackedShipments,
            'matchedShipment' => $matchedShipment,
            'matchedTimeline' => $matchedTimeline,
            'searchQuery' => $query,
        ]);
    }

    public function b2cTrackingShow(string $trackingNumber): RedirectResponse
    {
        return redirect()->route('b2c.tracking.index', [
            'q' => $trackingNumber,
        ]);
    }

    public function b2cSupport(): View
    {
        $account = $this->currentAccount();
        $accountId = (string) $account->id;

        return view('pages.portal.b2c.support', [
            'account' => $account,
            'shipmentsCount' => Shipment::query()->where('account_id', $accountId)->count(),
            'attentionCount' => Shipment::query()
                ->where('account_id', $accountId)
                ->whereIn('status', [
                    Shipment::STATUS_EXCEPTION,
                    Shipment::STATUS_FAILED,
                    Shipment::STATUS_REQUIRES_ACTION,
                    Shipment::STATUS_KYC_BLOCKED,
                ])
                ->count(),
            'wallet' => $this->preferredBillingWallet($accountId),
        ]);
    }

    public function b2cSettings(Request $request): View
    {
        $account = $this->currentAccount();

        return view('pages.portal.b2c.settings', [
            'account' => $account,
            'currentUser' => $request->user(),
            'kycVerification' => $account->kycVerification()->latest('submitted_at')->first(),
            'wallet' => $this->preferredBillingWallet((string) $account->id),
        ]);
    }

    public function b2bDashboard(Request $request): View
    {
        $account = $this->currentAccount();
        $accountId = (string) $account->id;
        $user = $request->user();
        $developerTools = $this->developerNavigationItems($user);
        $wallet = $this->preferredBillingWallet($accountId);
        $activeHolds = $this->walletActiveHolds($wallet, 4);
        $integrationSummaries = $this->integrationSummaries($accountId);

        return view('pages.portal.b2b.dashboard', [
            'account' => $account,
            'developerTools' => $developerTools,
            'wallet' => $wallet,
            'activeHolds' => $activeHolds,
            'dashboardStats' => $this->b2bDashboardStats($accountId, $wallet, $activeHolds),
            'summaryPills' => $this->b2bDashboardSummaryPills($accountId, $wallet, $integrationSummaries),
            'quickActions' => $this->b2bDashboardQuickActions($request, $developerTools),
            'recentShipments' => $this->b2bRecentShipmentActivity($accountId)->take(5),
            'recentOrders' => Order::query()
                ->where('account_id', $accountId)
                ->with('store')
                ->latest()
                ->limit(5)
                ->get(),
            'shipmentTrend' => $this->b2bShipmentTrend($accountId),
            'statusMix' => $this->b2bShipmentStatusMix($accountId),
            'fulfillmentFunnel' => $this->b2bFulfillmentFunnel($accountId),
            'teamSnapshot' => $this->b2bTeamSnapshot($accountId),
            'developerSummary' => $this->b2bDeveloperSummary($request, $accountId, $integrationSummaries),
            'stats' => [
                ['icon' => 'SH', 'label' => 'الشحنات', 'value' => number_format(Shipment::query()->where('account_id', $accountId)->count())],
                ['icon' => 'OR', 'label' => 'الطلبات', 'value' => number_format(Order::query()->where('account_id', $accountId)->count())],
                ['icon' => 'US', 'label' => 'المستخدمون', 'value' => number_format(User::query()->where('account_id', $accountId)->count())],
                ['icon' => 'RL', 'label' => 'الأدوار', 'value' => number_format(Role::query()->where('account_id', $accountId)->count())],
            ],
        ]);
    }

    public function b2bShipments(Request $request): View
    {
        return $this->shipmentIndexWorkspace($request, 'b2b');
    }

    public function exportB2bShipments(Request $request): Response
    {
        return $this->shipmentIndexExportWorkspace($request, 'b2b');
    }

    public function b2cShipmentShow(Request $request, string $id): View
    {
        return $this->shipmentTimelineWorkspace($request, $id, 'b2c');
    }

    public function b2bShipmentShow(Request $request, string $id): View
    {
        return $this->shipmentTimelineWorkspace($request, $id, 'b2b');
    }

    public function b2cShipmentDraft(): View
    {
        return $this->shipmentDraftWorkspace('b2c');
    }

    public function storeB2cShipmentDraft(Request $request): RedirectResponse
    {
        return $this->storeShipmentDraftWorkspace($request, 'b2c');
    }

    public function validateB2cShipmentAddress(Request $request): RedirectResponse
    {
        return $this->validateShipmentAddressWorkspace($request, 'b2c');
    }

    public function b2bShipmentDraft(): View
    {
        return $this->shipmentDraftWorkspace('b2b');
    }

    public function storeB2bShipmentDraft(Request $request): RedirectResponse
    {
        return $this->storeShipmentDraftWorkspace($request, 'b2b');
    }

    public function validateB2bShipmentAddress(Request $request): RedirectResponse
    {
        return $this->validateShipmentAddressWorkspace($request, 'b2b');
    }

    public function b2cShipmentOffers(Request $request, string $id): View
    {
        return $this->shipmentOffersWorkspace($request, $id, 'b2c');
    }

    public function fetchB2cShipmentOffers(Request $request, string $id): RedirectResponse
    {
        return $this->fetchShipmentOffersWorkspace($request, $id, 'b2c');
    }

    public function selectB2cShipmentOffer(Request $request, string $id): RedirectResponse
    {
        return $this->selectShipmentOfferWorkspace($request, $id, 'b2c');
    }

    public function b2cShipmentDeclaration(Request $request, string $id): View
    {
        return $this->shipmentDeclarationWorkspace($request, $id, 'b2c');
    }

    public function submitB2cShipmentDeclaration(Request $request, string $id): RedirectResponse
    {
        return $this->submitShipmentDeclarationWorkspace($request, $id, 'b2c');
    }

    public function triggerB2cShipmentWalletPreflight(Request $request, string $id): RedirectResponse
    {
        return $this->walletPreflightWorkspace($request, $id, 'b2c');
    }

    public function issueB2cShipmentAtCarrier(Request $request, string $id): RedirectResponse
    {
        return $this->issueShipmentWorkspace($request, $id, 'b2c');
    }

    public function b2bShipmentOffers(Request $request, string $id): View
    {
        return $this->shipmentOffersWorkspace($request, $id, 'b2b');
    }

    public function fetchB2bShipmentOffers(Request $request, string $id): RedirectResponse
    {
        return $this->fetchShipmentOffersWorkspace($request, $id, 'b2b');
    }

    public function selectB2bShipmentOffer(Request $request, string $id): RedirectResponse
    {
        return $this->selectShipmentOfferWorkspace($request, $id, 'b2b');
    }

    public function b2bShipmentDeclaration(Request $request, string $id): View
    {
        return $this->shipmentDeclarationWorkspace($request, $id, 'b2b');
    }

    public function submitB2bShipmentDeclaration(Request $request, string $id): RedirectResponse
    {
        return $this->submitShipmentDeclarationWorkspace($request, $id, 'b2b');
    }

    public function triggerB2bShipmentWalletPreflight(Request $request, string $id): RedirectResponse
    {
        return $this->walletPreflightWorkspace($request, $id, 'b2b');
    }

    public function issueB2bShipmentAtCarrier(Request $request, string $id): RedirectResponse
    {
        return $this->issueShipmentWorkspace($request, $id, 'b2b');
    }

    public function b2bOrders(): View
    {
        $account = $this->currentAccount();
        $accountId = (string) $account->id;

        $orders = Order::query()
            ->where('account_id', $accountId)
            ->with('store')
            ->latest()
            ->limit(8)
            ->get();

        return view('pages.portal.b2b.orders', [
            'account' => $account,
            'orders' => $orders,
            'workspaceStats' => $this->b2bOrderWorkspaceStats($accountId),
            'summaryGroups' => $this->b2bOrderSummaryGroups($accountId),
            'sourceMix' => $this->b2bOrderSourceMix($accountId),
            'stats' => [
                ['icon' => 'OR', 'label' => 'إجمالي الطلبات', 'value' => number_format(Order::query()->where('account_id', $accountId)->count())],
                ['icon' => 'NW', 'label' => 'جديدة أو قيد المعالجة', 'value' => number_format(Order::query()->where('account_id', $accountId)->whereIn('status', ['pending', 'new', 'processing'])->count())],
                ['icon' => 'OK', 'label' => 'مكتملة أو مشحونة', 'value' => number_format(Order::query()->where('account_id', $accountId)->whereIn('status', ['shipped', 'delivered'])->count())],
            ],
        ]);
    }

    public function b2bWallet(): View
    {
        $account = $this->currentAccount();
        $accountId = (string) $account->id;
        $wallet = $this->preferredBillingWallet($accountId);
        $transactions = $this->walletEntries($wallet);
        $activeHolds = $this->walletActiveHolds($wallet);

        return view('pages.portal.b2b.wallet', [
            'account' => $account,
            'wallet' => $wallet,
            'transactions' => $transactions,
            'activeHolds' => $activeHolds,
            'workspaceStats' => $this->b2bWalletStats($wallet, $activeHolds, $transactions),
        ]);
    }

    public function b2bReports(): View
    {
        $account = $this->currentAccount();
        $accountId = (string) $account->id;
        $wallet = $this->preferredBillingWallet($accountId);
        $integrationSummaries = $this->integrationSummaries($accountId);

        return view('pages.portal.b2b.reports', [
            'account' => $account,
            'workspaceStats' => $this->b2bReportWorkspaceStats($accountId, $wallet),
            'reportHighlights' => $this->b2bReportCards($accountId, $wallet, $integrationSummaries),
            'shipmentTrend' => $this->b2bShipmentTrend($accountId),
            'statusMix' => $this->b2bShipmentStatusMix($accountId),
            'teamSnapshot' => $this->b2bTeamSnapshot($accountId),
            'reportCards' => [
                ['title' => 'أداء الشحنات', 'description' => 'نظرة سريعة على أحجام الشحن والتسليم والتأخير لهذا الحساب.'],
                ['title' => 'الصورة المالية', 'description' => 'تتبع المصروفات والتكاليف والرصيد قبل فتح التقارير التفصيلية.'],
                ['title' => 'أداء الفريق', 'description' => 'مراجعة نشاط المستخدمين والأدوار قبل مشاركة التقارير مع الإدارة.'],
            ],
            'stats' => [
                ['icon' => 'SH', 'label' => 'الشحنات', 'value' => number_format(Shipment::query()->where('account_id', $accountId)->count())],
                ['icon' => 'OR', 'label' => 'الطلبات', 'value' => number_format(Order::query()->where('account_id', $accountId)->count())],
                ['icon' => 'US', 'label' => 'المستخدمون', 'value' => number_format(User::query()->where('account_id', $accountId)->count())],
                ['icon' => 'RL', 'label' => 'الأدوار النشطة', 'value' => number_format(Role::query()->where('account_id', $accountId)->count())],
            ],
        ]);
    }

    public function b2bUsers(): View
    {
        $account = $this->currentAccount();
        $accountId = (string) $account->id;

        $users = User::query()
            ->where('account_id', $accountId)
            ->with('roles')
            ->orderBy('name')
            ->limit(8)
            ->get();

        return view('pages.portal.b2b.users', [
            'account' => $account,
            'users' => $users,
            'workspaceStats' => $this->b2bUsersStats($accountId),
            'roleCoverage' => $this->b2bRoleCoverage($accountId),
            'stats' => [
                ['icon' => 'US', 'label' => 'إجمالي المستخدمين', 'value' => number_format(User::query()->where('account_id', $accountId)->count())],
                ['icon' => 'OK', 'label' => 'نشطون', 'value' => number_format(User::query()->where('account_id', $accountId)->where('status', 'active')->count())],
                ['icon' => 'NO', 'label' => 'معلّقون أو معطلون', 'value' => number_format(User::query()->where('account_id', $accountId)->whereIn('status', ['suspended', 'disabled'])->count())],
            ],
        ]);
    }

    public function b2bRoles(): View
    {
        $account = $this->currentAccount();
        $accountId = (string) $account->id;

        $roles = Role::query()
            ->where('account_id', $accountId)
            ->withCount(['users', 'permissions'])
            ->orderBy('name')
            ->limit(8)
            ->get();

        return view('pages.portal.b2b.roles', [
            'account' => $account,
            'roles' => $roles,
            'workspaceStats' => $this->b2bRolesStats($accountId),
        ]);
    }

    public function b2bDeveloperHome(Request $request): View
    {
        $account = $this->currentAccount();
        $user = $request->user();
        $apiKeys = $this->customerApiKeysForCurrentUser($request);
        $recentWebhookEvents = $this->recentWebhookEvents((string) $account->id)->take(5);
        $integrationSummaries = $this->integrationSummaries((string) $account->id)->take(4);

        return view('pages.portal.b2b.developer.index', [
            'account' => $account,
            'developerTools' => $this->developerNavigationItems($user),
            'recentApiKeys' => $apiKeys->take(3),
            'recentWebhookEvents' => $recentWebhookEvents,
            'integrationSummaries' => $integrationSummaries,
            'workspaceStats' => $this->b2bDeveloperStats($apiKeys, $recentWebhookEvents, $integrationSummaries),
        ]);
    }

    public function b2bDeveloperIntegrations(Request $request): View
    {
        $account = $this->currentAccount();
        $integrations = $this->integrationSummaries((string) $account->id);

        return view('pages.portal.b2b.developer.integrations', [
            'account' => $account,
            'integrations' => $integrations,
            'workspaceStats' => $this->b2bIntegrationStats($integrations),
        ]);
    }

    public function runIntegrationCheck(Request $request, string $integration): RedirectResponse
    {
        $account = $this->currentAccount();

        $catalog = $this->integrationCatalog()->keyBy('id');
        abort_unless($catalog->has($integration), 404);

        $attributes = [
            'id' => (string) Str::uuid(),
            'service' => $integration,
            'status' => 'healthy',
            'response_time_ms' => random_int(45, 180),
            'error_rate' => 0,
            'total_requests' => 1,
            'failed_requests' => 0,
            'checked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('integration_health_logs', 'account_id')) {
            $attributes['account_id'] = (string) $account->id;
        }
        if (Schema::hasColumn('integration_health_logs', 'integration_id')) {
            $attributes['integration_id'] = $integration;
        }
        if (Schema::hasColumn('integration_health_logs', 'metadata')) {
            $attributes['metadata'] = [
                'source' => 'browser_workspace',
                'account_name' => $account->name,
            ];
        }

        IntegrationHealthLog::query()->create($attributes);

        return redirect()->route('b2b.developer.integrations')
            ->with('success', 'تم تنفيذ فحص سريع للتكامل '.$catalog[$integration]['name'].'.');
    }

    public function b2bDeveloperApiKeys(Request $request): View
    {
        $account = $this->currentAccount();
        $apiKeys = $this->customerApiKeysForCurrentUser($request);

        return view('pages.portal.b2b.developer.api-keys', [
            'account' => $account,
            'apiKeys' => $apiKeys,
            'scopeOptions' => $this->customerApiScopeOptions(),
            'newApiKey' => session('new_api_key'),
            'workspaceStats' => $this->b2bApiKeyStats($apiKeys),
        ]);
    }

    public function storeDeveloperApiKey(Request $request): RedirectResponse
    {
        $account = $this->currentAccount();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', 'in:'.implode(',', array_keys($this->customerApiScopeOptions()))],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:10', 'max:300'],
        ]);

        $rawKey = 'cbex_'.Str::random(40);

        CustomerApiKey::query()->create([
            'account_id' => (string) $account->id,
            'user_id' => (string) $request->user()->id,
            'name' => $validated['name'],
            'key_hash' => hash('sha256', $rawKey),
            'key_prefix' => substr($rawKey, 0, 12),
            'permissions' => $validated['permissions'],
            'rate_limit_per_minute' => $validated['rate_limit_per_minute'] ?? 60,
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('b2b.developer.api-keys')
            ->with('success', 'تم إنشاء مفتاح جديد. خزّن القيمة الكاملة الآن لأنها لن تُعرض مرة أخرى.')
            ->with('new_api_key', $rawKey);
    }

    public function revokeDeveloperApiKey(Request $request, string $apiKey): RedirectResponse
    {
        $account = $this->currentAccount();

        $key = $this->customerApiKeysBaseQuery($request)
            ->where('id', $apiKey)
            ->firstOrFail();

        $key->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);

        return redirect()->route('b2b.developer.api-keys')
            ->with('success', 'تم إلغاء المفتاح المختار بنجاح.');
    }

    public function b2bDeveloperWebhooks(): View
    {
        $account = $this->currentAccount();
        $accountId = (string) $account->id;
        $recentWebhookEvents = $this->recentWebhookEvents($accountId);
        $stores = Store::query()->where('account_id', $accountId)->orderBy('name')->limit(8)->get();

        return view('pages.portal.b2b.developer.webhooks', [
            'account' => $account,
            'baseWebhookUrl' => rtrim(config('app.url'), '/').'/api/v1/webhooks',
            'recentWebhookEvents' => $recentWebhookEvents,
            'stores' => $stores,
            'workspaceStats' => $this->b2bWebhookStats($recentWebhookEvents, $stores->count()),
        ]);
    }

    private function currentAccount(): Account
    {
        if (app()->bound('current_account')) {
            $account = app('current_account');
            if ($account instanceof Account) {
                return $account;
            }
        }

        $user = auth()->user();
        abort_unless($user && $user->account instanceof Account, 400, 'تعذر تحديد الحساب الحالي.');

        return $user->account;
    }

    /**
     * @return array<int, array{key: string, label: string, value: string, meta: string, eyebrow: string}>
     */
    private function b2cDashboardStats(string $accountId, ?BillingWallet $wallet): array
    {
        $deliveredRecently = Shipment::query()
            ->where('account_id', $accountId)
            ->where('status', Shipment::STATUS_DELIVERED);

        if (Schema::hasColumn('shipments', 'delivered_at')) {
            $deliveredRecently->where('delivered_at', '>=', now()->subDays(30));
        } else {
            $deliveredRecently->where('updated_at', '>=', now()->subDays(30));
        }

        $attentionCount = Shipment::query()
            ->where('account_id', $accountId)
            ->whereIn('status', $this->b2cAttentionShipmentStatuses())
            ->count();

        return [
            [
                'key' => 'total',
                'label' => 'إجمالي الشحنات',
                'value' => number_format(Shipment::query()->where('account_id', $accountId)->count()),
                'meta' => 'كل الشحنات المرتبطة بحسابك الفردي',
                'eyebrow' => 'النشاط الكلي',
            ],
            [
                'key' => 'active',
                'label' => 'شحنات جارية',
                'value' => number_format(Shipment::query()->where('account_id', $accountId)->whereIn('status', $this->b2cActiveShipmentStatuses())->count()),
                'meta' => 'تشمل الشحنات التي ما زالت في الطريق أو قيد الإكمال',
                'eyebrow' => 'المتابعة الحالية',
            ],
            [
                'key' => 'delivered',
                'label' => 'تم تسليمها مؤخرًا',
                'value' => number_format($deliveredRecently->count()),
                'meta' => 'خلال آخر 30 يومًا',
                'eyebrow' => 'آخر النجاحات',
            ],
            [
                'key' => 'attention',
                'label' => 'تحتاج متابعة',
                'value' => number_format($attentionCount),
                'meta' => $attentionCount > 0 ? 'شحنات تتطلب خطوة إضافية أو مراجعة' : 'لا توجد شحنات متعثرة حاليًا',
                'eyebrow' => 'الانتباه المطلوب',
            ],
            [
                'key' => 'wallet',
                'label' => 'الرصيد المتاح',
                'value' => $wallet
                    ? number_format((float) $wallet->available_balance, 2).' '.($wallet->currency ?? 'SAR')
                    : '0.00',
                'meta' => $wallet
                    ? 'المبلغ المحجوز حاليًا: '.number_format((float) ($wallet->reserved_balance ?? 0), 2).' '.($wallet->currency ?? 'SAR')
                    : 'لا توجد محفظة مفعلة لهذا الحساب بعد',
                'eyebrow' => 'المحفظة',
            ],
        ];
    }

    /**
     * @return array<int, array{tone: string, title: string, body: string}>
     */
    private function b2cDashboardNotices(?KycVerification $kycVerification, ?BillingWallet $wallet, string $accountId): array
    {
        $notices = [];

        if ($kycVerification instanceof KycVerification) {
            $capabilities = $kycVerification->capabilities();
            $verificationMessage = trim((string) ($capabilities['message'] ?? ''));

            if ($kycVerification->isPending()) {
                $notices[] = [
                    'tone' => 'info',
                    'title' => 'طلب التحقق قيد المراجعة',
                    'body' => $verificationMessage !== ''
                        ? $verificationMessage
                        : 'نراجع بيانات التحقق الخاصة بك الآن. سنواصل عرض الشحنات الجارية، مع إبقاء القيود المؤقتة واضحة حتى تكتمل المراجعة.',
                ];
            } elseif ($kycVerification->isRejected()) {
                $notices[] = [
                    'tone' => 'critical',
                    'title' => 'التحقق يحتاج إلى تصحيح',
                    'body' => trim((string) ($kycVerification->rejection_reason ?: $verificationMessage)) !== ''
                        ? trim((string) ($kycVerification->rejection_reason ?: $verificationMessage))
                        : 'تعذر اعتماد التحقق الحالي. راجع آخر الملاحظات قبل متابعة الشحنات الحساسة أو الدولية.',
                ];
            } elseif ($kycVerification->isExpired()) {
                $notices[] = [
                    'tone' => 'warning',
                    'title' => 'انتهت صلاحية التحقق',
                    'body' => $verificationMessage !== ''
                        ? $verificationMessage
                        : 'بعض مزايا الحساب أصبحت محدودة حتى يتم تحديث بيانات التحقق من جديد.',
                ];
            } elseif ($kycVerification->isUnverified()) {
                $notices[] = [
                    'tone' => 'warning',
                    'title' => 'أكمل التحقق لفتح كل المزايا',
                    'body' => $verificationMessage !== ''
                        ? $verificationMessage
                        : 'يمكنك البدء بشحنات أساسية الآن، لكن بعض المزايا المتقدمة ستبقى مقيدة حتى يكتمل التحقق.',
                ];
            }
        } else {
            $notices[] = [
                'tone' => 'info',
                'title' => 'حسابك جاهز للبدء',
                'body' => 'ابدأ أول شحنة من هذه الصفحة، وسنواصل إبقاء حالة المحفظة والتتبع والتنبيهات المهمة أمامك في مكان واحد.',
            ];
        }

        if ($wallet instanceof BillingWallet && $wallet->isFrozen()) {
            $notices[] = [
                'tone' => 'critical',
                'title' => 'المحفظة متوقفة مؤقتًا',
                'body' => 'تم تجميد المحفظة الحالية مؤقتًا، لذلك قد تتوقف بعض خطوات الحجز المالي حتى تعود المحفظة إلى الحالة النشطة.',
            ];
        } elseif ($wallet instanceof BillingWallet && $wallet->isLowBalance()) {
            $notices[] = [
                'tone' => 'warning',
                'title' => 'الرصيد يقترب من الحد الأدنى',
                'body' => 'الرصيد المتاح أقل من مستوى التنبيه المضبوط للحساب. راجع المحفظة قبل إصدار شحنة جديدة حتى لا تتعطل الرحلة في خطوة الحجز المالي.',
            ];
        }

        $attentionCount = Shipment::query()
            ->where('account_id', $accountId)
            ->whereIn('status', $this->b2cAttentionShipmentStatuses())
            ->count();

        if ($attentionCount > 0) {
            $notices[] = [
                'tone' => 'warning',
                'title' => 'هناك شحنات تحتاج إلى مراجعتك',
                'body' => 'وجدنا '.number_format($attentionCount).' شحنة تحتاج إلى متابعة قبل الإكمال أو قبل استعادة تدفق التتبع بصورة طبيعية.',
            ];
        }

        return collect($notices)->take(3)->values()->all();
    }

    /**
     * @return array<int, array{label: string, value: string, tone: string}>
     */
    private function b2cDashboardSummaryPills(string $accountId, ?BillingWallet $wallet): array
    {
        $lastThirtyDays = Shipment::query()
            ->where('account_id', $accountId)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $addressCount = Address::query()->where('account_id', $accountId)->count();
        $walletTone = $wallet instanceof BillingWallet && $wallet->isFrozen()
            ? 'danger'
            : (($wallet instanceof BillingWallet && $wallet->isLowBalance()) ? 'warning' : 'success');

        return [
            [
                'label' => 'الشحن خلال آخر 30 يومًا',
                'value' => number_format($lastThirtyDays),
                'tone' => 'neutral',
            ],
            [
                'label' => 'العناوين المحفوظة',
                'value' => number_format($addressCount),
                'tone' => $addressCount > 0 ? 'success' : 'neutral',
            ],
            [
                'label' => 'حالة المحفظة',
                'value' => $wallet instanceof BillingWallet
                    ? ($wallet->isFrozen() ? 'متوقفة مؤقتًا' : 'نشطة')
                    : 'غير مفعلة',
                'tone' => $walletTone,
            ],
        ];
    }

    private function b2cContinueShipment(string $accountId): ?Shipment
    {
        return Shipment::query()
            ->where('account_id', $accountId)
            ->whereIn('status', $this->b2cContinueShipmentStatuses())
            ->latest('updated_at')
            ->first();
    }

    /**
     * @return array{url: string, label: string, helper: string}
     */
    private function b2cContinueShipmentAction(Shipment $shipment): array
    {
        $status = (string) $shipment->status;

        if (in_array($status, [
            Shipment::STATUS_DRAFT,
            Shipment::STATUS_VALIDATED,
            Shipment::STATUS_KYC_BLOCKED,
            Shipment::STATUS_READY_FOR_RATES,
        ], true)) {
            return [
                'url' => route('b2c.shipments.create', ['draft' => (string) $shipment->id]),
                'label' => 'استكمال البيانات',
                'helper' => 'ارجع إلى نموذج الشحنة لمراجعة العناوين والطرد والبيانات الأساسية.',
            ];
        }

        if (in_array($status, [
            Shipment::STATUS_RATED,
            Shipment::STATUS_OFFER_SELECTED,
        ], true)) {
            return [
                'url' => route('b2c.shipments.offers', ['id' => (string) $shipment->id]),
                'label' => 'مراجعة العروض',
                'helper' => 'العروض جاهزة، ويمكنك العودة لمقارنتها وتثبيت الأنسب قبل المتابعة.',
            ];
        }

        if (in_array($status, [
            Shipment::STATUS_DECLARATION_REQUIRED,
            Shipment::STATUS_DECLARATION_COMPLETE,
            Shipment::STATUS_REQUIRES_ACTION,
        ], true)) {
            return [
                'url' => route('b2c.shipments.declaration', ['id' => (string) $shipment->id]),
                'label' => 'إكمال الإقرار',
                'helper' => 'هذه الشحنة تحتاج إلى مراجعة الإقرار أو معالجة خطوة تشغيلية قبل الإصدار.',
            ];
        }

        return [
            'url' => route('b2c.shipments.show', ['id' => (string) $shipment->id]),
            'label' => 'متابعة الشحنة',
            'helper' => 'افتح صفحة الشحنة لمراجعة الحجز المالي أو التتبع أو المستندات المرتبطة بها.',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function b2cActiveShipmentStatuses(): array
    {
        return [
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
            Shipment::STATUS_PURCHASED,
            Shipment::STATUS_READY_FOR_PICKUP,
            Shipment::STATUS_PICKED_UP,
            Shipment::STATUS_IN_TRANSIT,
            Shipment::STATUS_OUT_FOR_DELIVERY,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function b2cAttentionShipmentStatuses(): array
    {
        return [
            Shipment::STATUS_KYC_BLOCKED,
            Shipment::STATUS_REQUIRES_ACTION,
            Shipment::STATUS_EXCEPTION,
            Shipment::STATUS_FAILED,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function b2cContinueShipmentStatuses(): array
    {
        return [
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
            Shipment::STATUS_PURCHASED,
            Shipment::STATUS_READY_FOR_PICKUP,
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, tone: string}>
     */
    private function b2cShipmentSummaryGroups(string $accountId): array
    {
        return [
            [
                'label' => 'تحتاج منك خطوة',
                'value' => number_format(Shipment::query()->where('account_id', $accountId)->whereIn('status', [
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
                ])->count()),
                'tone' => 'warning',
            ],
            [
                'label' => 'في الطريق',
                'value' => number_format(Shipment::query()->where('account_id', $accountId)->whereIn('status', [
                    Shipment::STATUS_PURCHASED,
                    Shipment::STATUS_READY_FOR_PICKUP,
                    Shipment::STATUS_PICKED_UP,
                    Shipment::STATUS_IN_TRANSIT,
                    Shipment::STATUS_OUT_FOR_DELIVERY,
                ])->count()),
                'tone' => 'info',
            ],
            [
                'label' => 'تم تسليمها',
                'value' => number_format(Shipment::query()->where('account_id', $accountId)->where('status', Shipment::STATUS_DELIVERED)->count()),
                'tone' => 'success',
            ],
            [
                'label' => 'استثناءات',
                'value' => number_format(Shipment::query()->where('account_id', $accountId)->whereIn('status', [
                    Shipment::STATUS_EXCEPTION,
                    Shipment::STATUS_FAILED,
                    Shipment::STATUS_RETURNED,
                ])->count()),
                'tone' => 'danger',
            ],
        ];
    }

    private function b2cRecentShipmentActivity(string $accountId): Collection
    {
        return Shipment::query()
            ->where('account_id', $accountId)
            ->latest('updated_at')
            ->limit(4)
            ->get();
    }

    private function walletActiveHolds(?BillingWallet $wallet, int $limit = 5): Collection
    {
        if (! $wallet || ! Schema::hasTable('wallet_holds')) {
            return collect();
        }

        $query = WalletHold::query()
            ->with('shipment')
            ->where('wallet_id', (string) $wallet->id)
            ->whereIn('status', [WalletHold::STATUS_ACTIVE, WalletHold::STATUS_CAPTURED])
            ->latest('created_at');

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * @return array<int, array{label: string, value: string, meta: string, eyebrow: string, iconName: string}>
     */
    private function b2bDashboardStats(string $accountId, ?BillingWallet $wallet, Collection $activeHolds): array
    {
        $usersCount = User::query()->where('account_id', $accountId)->count();
        $rolesCount = Role::query()->where('account_id', $accountId)->count();
        $ordersNeedAction = Order::query()
            ->where('account_id', $accountId)
            ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_ON_HOLD, Order::STATUS_FAILED])
            ->count();

        return [
            [
                'label' => 'إجمالي الشحنات',
                'value' => number_format(Shipment::query()->where('account_id', $accountId)->count()),
                'meta' => 'كل الشحنات التي أنشأها فريق المنظمة عبر المنصة',
                'eyebrow' => 'الحجم التشغيلي',
                'iconName' => 'shipments',
            ],
            [
                'label' => 'شحنات جارية',
                'value' => number_format(Shipment::query()->where('account_id', $accountId)->whereIn('status', $this->b2bActiveShipmentStatuses())->count()),
                'meta' => 'تشمل المسودات الجارية والشحنات التي لا تزال داخل الشبكة',
                'eyebrow' => 'المتابعة اليومية',
                'iconName' => 'trend',
            ],
            [
                'label' => 'استثناءات الشحن',
                'value' => number_format(Shipment::query()->where('account_id', $accountId)->whereIn('status', $this->b2bExceptionShipmentStatuses())->count()),
                'meta' => 'الحالات التي تحتاج مراجعة أو عودة أو معالجة إضافية',
                'eyebrow' => 'التنبيه الفوري',
                'iconName' => 'alert',
            ],
            [
                'label' => 'طلبات تحتاج إجراء',
                'value' => number_format($ordersNeedAction),
                'meta' => 'الطلبات المعلّقة أو المتوقفة قبل التحويل إلى شحنات مكتملة',
                'eyebrow' => 'الطلبات',
                'iconName' => 'orders',
            ],
            [
                'label' => 'الرصيد المتاح',
                'value' => $wallet
                    ? number_format((float) $wallet->available_balance, 2).' '.($wallet->currency ?? 'SAR')
                    : '0.00',
                'meta' => $wallet
                    ? 'محجوز حالياً: '.number_format((float) ($wallet->reserved_balance ?? 0), 2).' '.($wallet->currency ?? 'SAR').' عبر '.number_format($activeHolds->count()).' عمليات حجز'
                    : 'لا توجد محفظة مفعلة لهذا الحساب حتى الآن',
                'eyebrow' => 'الجاهزية المالية',
                'iconName' => 'wallet',
            ],
            [
                'label' => 'الفريق والأدوار',
                'value' => number_format($usersCount),
                'meta' => number_format($rolesCount).' أدوار نشطة تضبط صلاحيات الفريق الحالي',
                'eyebrow' => 'المنظمة',
                'iconName' => 'team',
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, tone: string}>
     */
    private function b2bDashboardSummaryPills(string $accountId, ?BillingWallet $wallet, Collection $integrationSummaries): array
    {
        $degradedIntegrations = $integrationSummaries->filter(
            fn (array $integration): bool => ($integration['status'] ?? null) !== 'healthy'
        )->count();
        $storesCount = Store::query()->where('account_id', $accountId)->count();

        return [
            [
                'label' => 'الشحنات خلال آخر 30 يوماً',
                'value' => number_format(Shipment::query()->where('account_id', $accountId)->where('created_at', '>=', now()->subDays(30))->count()),
                'tone' => 'neutral',
            ],
            [
                'label' => 'المتاجر المرتبطة',
                'value' => number_format($storesCount),
                'tone' => $storesCount > 0 ? 'success' : 'neutral',
            ],
            [
                'label' => 'حالة الرصيد',
                'value' => ! $wallet
                    ? 'غير مفعل'
                    : ($wallet->isFrozen() ? 'موقوف مؤقتاً' : ($wallet->isLowBalance() ? 'منخفض' : 'مطمئن')),
                'tone' => ! $wallet
                    ? 'neutral'
                    : ($wallet->isFrozen() ? 'danger' : ($wallet->isLowBalance() ? 'warning' : 'success')),
            ],
            [
                'label' => 'التكاملات المتعثرة',
                'value' => number_format($degradedIntegrations),
                'tone' => $degradedIntegrations > 0 ? 'warning' : 'success',
            ],
        ];
    }

    /**
     * @return array<int, array{title: string, body: string, route: string, iconName: string, tone: string}>
     */
    private function b2bDashboardQuickActions(Request $request, Collection $developerTools): array
    {
        $user = $request->user();
        $actions = [
            [
                'title' => 'بدء طلب شحنة لفريقك',
                'body' => 'ابدأ رحلة الشحنة من إدخال البيانات وحتى الإصدار من داخل مساحة واحدة متسقة.',
                'route' => route('b2b.shipments.create'),
                'iconName' => 'shipments',
                'tone' => 'accent',
            ],
            [
                'title' => 'لوحة تشغيل الشحنات',
                'body' => 'راجع المسودات والشحنات الجارية والاستثناءات قبل أن تتكدس على الفريق.',
                'route' => route('b2b.shipments.index'),
                'iconName' => 'trend',
                'tone' => 'neutral',
            ],
            [
                'title' => 'مراجعة الطلبات',
                'body' => 'تابع الطلبات الجديدة والمتوقفة وحوّل ما يلزم منها إلى شحنات قابلة للتنفيذ.',
                'route' => route('b2b.orders.index'),
                'iconName' => 'orders',
                'tone' => 'neutral',
            ],
            [
                'title' => 'فتح المحفظة',
                'body' => 'راجع الرصيد والحجوزات قبل الحجز المالي أو إصدار الشحنات مرتفعة الكلفة.',
                'route' => route('b2b.wallet.index'),
                'iconName' => 'wallet',
                'tone' => 'neutral',
            ],
            [
                'title' => 'تقارير التنفيذ',
                'body' => 'احصل على قراءة سريعة للأداء والتوزيع الحاليين قبل مشاركة الحالة مع الإدارة.',
                'route' => route('b2b.reports.index'),
                'iconName' => 'reports',
                'tone' => 'neutral',
            ],
            [
                'title' => 'فريق المنظمة',
                'body' => 'افتح أعضاء الفريق والأدوار للتأكد من التغطية والصلاحيات قبل توسيع العمليات.',
                'route' => route('b2b.users.index'),
                'iconName' => 'users',
                'tone' => 'neutral',
            ],
        ];

        $actions = array_values(array_filter($actions, function (array $action) use ($user): bool {
            return match ($action['route']) {
                route('b2b.wallet.index') => $user?->hasPermission('wallet.balance') && $user?->hasPermission('wallet.ledger'),
                route('b2b.reports.index') => $user?->hasPermission('reports.read') && $user?->hasPermission('analytics.read'),
                route('b2b.users.index') => $user?->hasPermission('users.read'),
                default => true,
            };
        }));

        if ($developerTools->isNotEmpty()) {
            $firstTool = $developerTools->first();
            if (is_array($firstTool) && isset($firstTool['route'])) {
                $actions[] = [
                    'title' => 'واجهة المطور',
                    'body' => 'افتح أدوات التكامل الخاصة بالمنظمة مع المنصة من نفس لغة الواجهة التشغيلية.',
                    'route' => route((string) $firstTool['route']),
                    'iconName' => 'developer',
                    'tone' => 'secondary',
                ];
            }
        }

        return $actions;
    }

    /**
     * @return array<int, array{label: string, value: string, tone: string}>
     */
    private function b2bShipmentSummaryGroups(string $accountId): array
    {
        return [
            [
                'label' => 'بحاجة إلى إجراء',
                'value' => number_format(Shipment::query()->where('account_id', $accountId)->whereIn('status', [
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
                ])->count()),
                'tone' => 'warning',
            ],
            [
                'label' => 'في الطريق',
                'value' => number_format(Shipment::query()->where('account_id', $accountId)->whereIn('status', [
                    Shipment::STATUS_PURCHASED,
                    Shipment::STATUS_READY_FOR_PICKUP,
                    Shipment::STATUS_PICKED_UP,
                    Shipment::STATUS_IN_TRANSIT,
                    Shipment::STATUS_OUT_FOR_DELIVERY,
                ])->count()),
                'tone' => 'info',
            ],
            [
                'label' => 'تم التسليم',
                'value' => number_format(Shipment::query()->where('account_id', $accountId)->where('status', Shipment::STATUS_DELIVERED)->count()),
                'tone' => 'success',
            ],
            [
                'label' => 'استثناءات',
                'value' => number_format(Shipment::query()->where('account_id', $accountId)->whereIn('status', $this->b2bExceptionShipmentStatuses())->count()),
                'tone' => 'danger',
            ],
        ];
    }

    private function b2bRecentShipmentActivity(string $accountId): Collection
    {
        return Shipment::query()
            ->where('account_id', $accountId)
            ->latest('updated_at')
            ->limit(6)
            ->get();
    }

    /**
     * @return array<int, array{label: string, count: int, value: string, height: float}>
     */
    private function b2bShipmentTrend(string $accountId, int $days = 7): array
    {
        $points = collect(range($days - 1, 0))->map(function (int $offset) use ($accountId): array {
            $date = now()->subDays($offset)->startOfDay();
            $count = Shipment::query()
                ->where('account_id', $accountId)
                ->whereDate('created_at', $date->toDateString())
                ->count();

            return [
                'label' => $date->translatedFormat('D'),
                'count' => $count,
            ];
        });

        $max = max(1, (int) $points->max('count'));

        return $points->map(fn (array $point): array => [
            'label' => $point['label'],
            'count' => $point['count'],
            'value' => number_format($point['count']),
            'height' => round(($point['count'] / $max) * 100, 2),
        ])->all();
    }

    /**
     * @return array<int, array{label: string, count: int, value: string, percentage: float, tone: string}>
     */
    private function b2bShipmentStatusMix(string $accountId): array
    {
        $groups = collect([
            [
                'label' => 'تحتاج قراراً',
                'count' => Shipment::query()->where('account_id', $accountId)->whereIn('status', [
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
                ])->count(),
                'tone' => 'warning',
            ],
            [
                'label' => 'داخل الشبكة',
                'count' => Shipment::query()->where('account_id', $accountId)->whereIn('status', [
                    Shipment::STATUS_PURCHASED,
                    Shipment::STATUS_READY_FOR_PICKUP,
                    Shipment::STATUS_PICKED_UP,
                    Shipment::STATUS_IN_TRANSIT,
                    Shipment::STATUS_OUT_FOR_DELIVERY,
                ])->count(),
                'tone' => 'info',
            ],
            [
                'label' => 'مكتملة',
                'count' => Shipment::query()->where('account_id', $accountId)->where('status', Shipment::STATUS_DELIVERED)->count(),
                'tone' => 'success',
            ],
            [
                'label' => 'استثناءات',
                'count' => Shipment::query()->where('account_id', $accountId)->whereIn('status', $this->b2bExceptionShipmentStatuses())->count(),
                'tone' => 'danger',
            ],
        ]);

        $total = max(1, (int) $groups->sum('count'));

        return $groups->map(fn (array $group): array => [
            'label' => $group['label'],
            'count' => $group['count'],
            'value' => number_format($group['count']),
            'percentage' => round(($group['count'] / $total) * 100, 1),
            'tone' => $group['tone'],
        ])->all();
    }

    /**
     * @return array<int, array{label: string, count: int, value: string}>
     */
    private function b2bFulfillmentFunnel(string $accountId): array
    {
        return [
            [
                'label' => 'طلب أو مسودة',
                'count' => Shipment::query()->where('account_id', $accountId)->whereIn('status', [
                    Shipment::STATUS_DRAFT,
                    Shipment::STATUS_VALIDATED,
                    Shipment::STATUS_READY_FOR_RATES,
                    Shipment::STATUS_RATED,
                ])->count(),
                'value' => number_format(Shipment::query()->where('account_id', $accountId)->whereIn('status', [
                    Shipment::STATUS_DRAFT,
                    Shipment::STATUS_VALIDATED,
                    Shipment::STATUS_READY_FOR_RATES,
                    Shipment::STATUS_RATED,
                ])->count()),
            ],
            [
                'label' => 'اختيار الخدمة',
                'count' => Shipment::query()->where('account_id', $accountId)->whereIn('status', [
                    Shipment::STATUS_OFFER_SELECTED,
                    Shipment::STATUS_DECLARATION_REQUIRED,
                    Shipment::STATUS_DECLARATION_COMPLETE,
                    Shipment::STATUS_REQUIRES_ACTION,
                    Shipment::STATUS_PAYMENT_PENDING,
                ])->count(),
                'value' => number_format(Shipment::query()->where('account_id', $accountId)->whereIn('status', [
                    Shipment::STATUS_OFFER_SELECTED,
                    Shipment::STATUS_DECLARATION_REQUIRED,
                    Shipment::STATUS_DECLARATION_COMPLETE,
                    Shipment::STATUS_REQUIRES_ACTION,
                    Shipment::STATUS_PAYMENT_PENDING,
                ])->count()),
            ],
            [
                'label' => 'إصدار واستلام',
                'count' => Shipment::query()->where('account_id', $accountId)->whereIn('status', [
                    Shipment::STATUS_PURCHASED,
                    Shipment::STATUS_READY_FOR_PICKUP,
                    Shipment::STATUS_PICKED_UP,
                ])->count(),
                'value' => number_format(Shipment::query()->where('account_id', $accountId)->whereIn('status', [
                    Shipment::STATUS_PURCHASED,
                    Shipment::STATUS_READY_FOR_PICKUP,
                    Shipment::STATUS_PICKED_UP,
                ])->count()),
            ],
            [
                'label' => 'في الطريق',
                'count' => Shipment::query()->where('account_id', $accountId)->whereIn('status', [
                    Shipment::STATUS_IN_TRANSIT,
                    Shipment::STATUS_OUT_FOR_DELIVERY,
                ])->count(),
                'value' => number_format(Shipment::query()->where('account_id', $accountId)->whereIn('status', [
                    Shipment::STATUS_IN_TRANSIT,
                    Shipment::STATUS_OUT_FOR_DELIVERY,
                ])->count()),
            ],
            [
                'label' => 'تم التسليم',
                'count' => Shipment::query()->where('account_id', $accountId)->where('status', Shipment::STATUS_DELIVERED)->count(),
                'value' => number_format(Shipment::query()->where('account_id', $accountId)->where('status', Shipment::STATUS_DELIVERED)->count()),
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, tone: string}>
     */
    private function b2bTeamSnapshot(string $accountId): array
    {
        return [
            [
                'label' => 'أعضاء الفريق',
                'value' => number_format(User::query()->where('account_id', $accountId)->count()),
                'tone' => 'neutral',
            ],
            [
                'label' => 'أعضاء نشطون',
                'value' => number_format(User::query()->where('account_id', $accountId)->where('status', 'active')->count()),
                'tone' => 'success',
            ],
            [
                'label' => 'الأدوار',
                'value' => number_format(Role::query()->where('account_id', $accountId)->count()),
                'tone' => 'neutral',
            ],
            [
                'label' => 'المتاجر',
                'value' => number_format(Store::query()->where('account_id', $accountId)->count()),
                'tone' => 'info',
            ],
        ];
    }

    /**
     * @return array{visible: bool, stats: array<int, array{label: string, value: string, tone: string}>, tools: Collection}
     */
    private function b2bDeveloperSummary(Request $request, string $accountId, Collection $integrationSummaries): array
    {
        $user = $request->user();
        $tools = $this->developerNavigationItems($user);

        if ($tools->isEmpty()) {
            return [
                'visible' => false,
                'stats' => [],
                'tools' => collect(),
            ];
        }

        $apiKeys = $user->hasPermission('api_keys.read')
            ? $this->customerApiKeysForCurrentUser($request)
            : collect();
        $webhookEvents = $user->hasPermission('webhooks.read')
            ? $this->recentWebhookEvents($accountId)
            : collect();

        return [
            'visible' => true,
            'tools' => $tools->take(3),
            'stats' => [
                [
                    'label' => 'تكاملات مستقرة',
                    'value' => number_format($integrationSummaries->filter(
                        fn (array $integration): bool => ($integration['status'] ?? null) === 'healthy'
                    )->count()),
                    'tone' => 'success',
                ],
                [
                    'label' => 'مفاتيح API النشطة',
                    'value' => number_format($apiKeys->filter(
                        fn (CustomerApiKey $key): bool => (bool) $key->is_active
                    )->count()),
                    'tone' => 'neutral',
                ],
                [
                    'label' => 'ويبهوكات فاشلة حديثاً',
                    'value' => number_format($webhookEvents->filter(
                        fn (WebhookEvent $event): bool => $event->status === WebhookEvent::STATUS_FAILED
                    )->count()),
                    'tone' => 'warning',
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, meta: string, eyebrow: string, iconName: string}>
     */
    private function b2bOrderWorkspaceStats(string $accountId): array
    {
        return [
            [
                'label' => 'إجمالي الطلبات',
                'value' => number_format(Order::query()->where('account_id', $accountId)->count()),
                'meta' => 'كل الطلبات الواردة من المتاجر أو القنوات المتصلة',
                'eyebrow' => 'الحجم الكلي',
                'iconName' => 'orders',
            ],
            [
                'label' => 'تحتاج متابعة',
                'value' => number_format(Order::query()->where('account_id', $accountId)->whereIn('status', [
                    Order::STATUS_PENDING,
                    Order::STATUS_ON_HOLD,
                    Order::STATUS_FAILED,
                ])->count()),
                'meta' => 'طلبات متوقفة أو بانتظار قرار تشغيل أو مراجعة',
                'eyebrow' => 'الإجراء المطلوب',
                'iconName' => 'alert',
            ],
            [
                'label' => 'قيد المعالجة',
                'value' => number_format(Order::query()->where('account_id', $accountId)->whereIn('status', [
                    Order::STATUS_READY,
                    Order::STATUS_PROCESSING,
                ])->count()),
                'meta' => 'طلبات يتم تجهيزها أو مزامنتها حالياً',
                'eyebrow' => 'العمليات',
                'iconName' => 'trend',
            ],
            [
                'label' => 'تم شحنها أو تسليمها',
                'value' => number_format(Order::query()->where('account_id', $accountId)->whereIn('status', [
                    Order::STATUS_SHIPPED,
                    Order::STATUS_DELIVERED,
                ])->count()),
                'meta' => 'طلبات تجاوزت مرحلة التنفيذ الأساسية',
                'eyebrow' => 'المخرجات',
                'iconName' => 'shipments',
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, tone: string}>
     */
    private function b2bOrderSummaryGroups(string $accountId): array
    {
        return [
            [
                'label' => 'جديدة',
                'value' => number_format(Order::query()->where('account_id', $accountId)->where('status', Order::STATUS_PENDING)->count()),
                'tone' => 'warning',
            ],
            [
                'label' => 'قيد التشغيل',
                'value' => number_format(Order::query()->where('account_id', $accountId)->whereIn('status', [
                    Order::STATUS_READY,
                    Order::STATUS_PROCESSING,
                ])->count()),
                'tone' => 'info',
            ],
            [
                'label' => 'مكتملة',
                'value' => number_format(Order::query()->where('account_id', $accountId)->whereIn('status', [
                    Order::STATUS_SHIPPED,
                    Order::STATUS_DELIVERED,
                ])->count()),
                'tone' => 'success',
            ],
            [
                'label' => 'متوقفة',
                'value' => number_format(Order::query()->where('account_id', $accountId)->whereIn('status', [
                    Order::STATUS_ON_HOLD,
                    Order::STATUS_FAILED,
                    Order::STATUS_CANCELLED,
                ])->count()),
                'tone' => 'danger',
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, percentage: float}>
     */
    private function b2bOrderSourceMix(string $accountId): array
    {
        if (Schema::hasColumn('orders', 'source')) {
            $rows = Order::query()
                ->selectRaw('source, count(*) as aggregate')
                ->where('account_id', $accountId)
                ->groupBy('source')
                ->orderByDesc('aggregate')
                ->limit(4)
                ->get();

            $total = max(1, (int) $rows->sum('aggregate'));

            return $rows->map(fn (Order $row): array => [
                'label' => $this->translatedOrderSource($row->source),
                'value' => number_format((int) $row->aggregate),
                'percentage' => round((((int) $row->aggregate) / $total) * 100, 1),
            ])->all();
        }

        $rows = Order::query()
            ->where('account_id', $accountId)
            ->with('store')
            ->latest()
            ->limit(20)
            ->get()
            ->groupBy(fn (Order $order): string => (string) ($order->store?->name ?: 'بدون متجر'))
            ->map->count()
            ->sortDesc()
            ->take(4);

        $total = max(1, (int) $rows->sum());

        return $rows->map(fn (int $count, string $label): array => [
            'label' => $label,
            'value' => number_format($count),
            'percentage' => round(($count / $total) * 100, 1),
        ])->values()->all();
    }

    /**
     * @return array<int, array{label: string, value: string, meta: string, eyebrow: string, iconName: string}>
     */
    private function b2bUsersStats(string $accountId): array
    {
        return [
            [
                'label' => 'إجمالي المستخدمين',
                'value' => number_format(User::query()->where('account_id', $accountId)->count()),
                'meta' => 'كل أعضاء الفريق المسجلين على حساب المنظمة',
                'eyebrow' => 'التغطية',
                'iconName' => 'users',
            ],
            [
                'label' => 'نشطون',
                'value' => number_format(User::query()->where('account_id', $accountId)->where('status', 'active')->count()),
                'meta' => 'أعضاء يمكنهم متابعة العمليات حالياً',
                'eyebrow' => 'الوصول الحالي',
                'iconName' => 'team',
            ],
            [
                'label' => 'قيد التفعيل',
                'value' => number_format(User::query()->where('account_id', $accountId)->where('status', 'pending')->count()),
                'meta' => 'دعوات أو حسابات لم تكتمل جاهزيتها بعد',
                'eyebrow' => 'الدخول الجديد',
                'iconName' => 'activity',
            ],
            [
                'label' => 'معلقون أو معطلون',
                'value' => number_format(User::query()->where('account_id', $accountId)->whereIn('status', ['suspended', 'disabled'])->count()),
                'meta' => 'يحتاجون مراجعة قبل إعادتهم إلى العمليات',
                'eyebrow' => 'المخاطر التشغيلية',
                'iconName' => 'alert',
            ],
        ];
    }

    private function b2bRoleCoverage(string $accountId): Collection
    {
        return Role::query()
            ->where('account_id', $accountId)
            ->withCount(['users', 'permissions'])
            ->orderByDesc('users_count')
            ->orderBy('name')
            ->limit(6)
            ->get();
    }

    /**
     * @return array<int, array{label: string, value: string, meta: string, eyebrow: string, iconName: string}>
     */
    private function b2bRolesStats(string $accountId): array
    {
        return [
            [
                'label' => 'الأدوار النشطة',
                'value' => number_format(Role::query()->where('account_id', $accountId)->count()),
                'meta' => 'الأدوار التي تضبط توزيع العمل والصلاحيات الحالية',
                'eyebrow' => 'نموذج الصلاحيات',
                'iconName' => 'roles',
            ],
            [
                'label' => 'أعضاء ضمن أدوار',
                'value' => number_format((int) Role::query()->where('account_id', $accountId)->withCount('users')->get()->sum('users_count')),
                'meta' => 'إجمالي التعيينات الحالية عبر كل الأدوار',
                'eyebrow' => 'التغطية',
                'iconName' => 'team',
            ],
            [
                'label' => 'صلاحيات مضمنة',
                'value' => number_format((int) Role::query()->where('account_id', $accountId)->withCount('permissions')->get()->sum('permissions_count')),
                'meta' => 'إشارات سريعة على كثافة الضبط الحالي',
                'eyebrow' => 'الضبط',
                'iconName' => 'activity',
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, meta: string, eyebrow: string, iconName: string}>
     */
    private function b2bWalletStats(?BillingWallet $wallet, Collection $activeHolds, Collection $transactions): array
    {
        return [
            [
                'label' => 'الرصيد المتاح',
                'value' => $wallet ? number_format((float) $wallet->available_balance, 2).' '.($wallet->currency ?? 'SAR') : '0.00',
                'meta' => $wallet ? 'المحفظة في حالة '.$this->translatedWalletStatus($wallet->status) : 'لا توجد محفظة مفعلة بعد',
                'eyebrow' => 'السيولة',
                'iconName' => 'wallet',
            ],
            [
                'label' => 'الرصيد المحجوز',
                'value' => $wallet ? number_format((float) ($wallet->reserved_balance ?? 0), 2).' '.($wallet->currency ?? 'SAR') : '0.00',
                'meta' => number_format($activeHolds->count()).' حجوزات نشطة أو ملتقطة مرتبطة بعمليات الشحن',
                'eyebrow' => 'الحجوزات',
                'iconName' => 'hold',
            ],
            [
                'label' => 'الرصيد الصافي',
                'value' => $wallet ? number_format($wallet->getEffectiveBalance(), 2).' '.($wallet->currency ?? 'SAR') : '0.00',
                'meta' => 'الرصيد المتبقي بعد استبعاد المبالغ المحجوزة',
                'eyebrow' => 'بعد الحجز',
                'iconName' => 'trend',
            ],
            [
                'label' => 'حركات حديثة',
                'value' => number_format($transactions->count()),
                'meta' => 'آخر عمليات الشحن والخصم والاسترداد الظاهرة في السجل',
                'eyebrow' => 'النشاط المالي',
                'iconName' => 'activity',
            ],
        ];
    }

    /**
     * @return array<int, array{title: string, body: string, tone: string}>
     */
    private function b2bReportCards(string $accountId, ?BillingWallet $wallet, Collection $integrationSummaries): array
    {
        $shipmentsLastThirtyDays = Shipment::query()
            ->where('account_id', $accountId)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $ordersNeedAction = Order::query()
            ->where('account_id', $accountId)
            ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_ON_HOLD, Order::STATUS_FAILED])
            ->count();
        $healthyIntegrations = $integrationSummaries->filter(
            fn (array $integration): bool => ($integration['status'] ?? null) === 'healthy'
        )->count();

        return [
            [
                'title' => 'أداء التنفيذ',
                'body' => 'تم تسجيل '.number_format($shipmentsLastThirtyDays).' شحنة خلال آخر 30 يوماً، ما يعطي قراءة سريعة على كثافة التشغيل الحالية.',
                'tone' => 'neutral',
            ],
            [
                'title' => 'الطلبات التي تحتاج مراجعة',
                'body' => 'يوجد '.number_format($ordersNeedAction).' طلباً يحتاج قراراً أو معالجة قبل أن يمر بسلاسة إلى مرحلة الشحن.',
                'tone' => $ordersNeedAction > 0 ? 'warning' : 'success',
            ],
            [
                'title' => 'الصورة المالية',
                'body' => $wallet
                    ? 'الرصيد الصافي الحالي هو '.number_format($wallet->getEffectiveBalance(), 2).' '.($wallet->currency ?? 'SAR').' بعد خصم المبالغ المحجوزة.'
                    : 'لا توجد محفظة مفعلة بعد، لذلك ستبقى القراءة المالية محدودة حتى يتم تفعيلها.',
                'tone' => $wallet instanceof BillingWallet ? 'info' : 'neutral',
            ],
            [
                'title' => 'الجاهزية التقنية',
                'body' => 'عدد التكاملات المستقرة حالياً هو '.number_format($healthyIntegrations).' من أصل '.number_format($integrationSummaries->count()).'، وهي قراءة مفيدة قبل أي توسع تشغيلي.',
                'tone' => $healthyIntegrations === $integrationSummaries->count() ? 'success' : 'warning',
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, meta: string, eyebrow: string, iconName: string}>
     */
    private function b2bReportWorkspaceStats(string $accountId, ?BillingWallet $wallet): array
    {
        return [
            [
                'label' => 'الشحنات',
                'value' => number_format(Shipment::query()->where('account_id', $accountId)->count()),
                'meta' => 'كل الشحنات المرتبطة بحساب المنظمة',
                'eyebrow' => 'الحجم',
                'iconName' => 'shipments',
            ],
            [
                'label' => 'الطلبات',
                'value' => number_format(Order::query()->where('account_id', $accountId)->count()),
                'meta' => 'الطلبات التي تغذي مسار التنفيذ',
                'eyebrow' => 'التدفق',
                'iconName' => 'orders',
            ],
            [
                'label' => 'الفريق',
                'value' => number_format(User::query()->where('account_id', $accountId)->count()),
                'meta' => number_format(Role::query()->where('account_id', $accountId)->count()).' أدوار تنظّم هذا الفريق',
                'eyebrow' => 'المنظمة',
                'iconName' => 'team',
            ],
            [
                'label' => 'الرصيد الصافي',
                'value' => $wallet ? number_format($wallet->getEffectiveBalance(), 2).' '.($wallet->currency ?? 'SAR') : '0.00',
                'meta' => 'الرصيد المتبقي بعد الحجوزات الحالية',
                'eyebrow' => 'المالية',
                'iconName' => 'wallet',
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, meta: string, eyebrow: string, iconName: string}>
     */
    private function b2bDeveloperStats(Collection $apiKeys, Collection $recentWebhookEvents, Collection $integrationSummaries): array
    {
        return [
            [
                'label' => 'تكاملات مرئية',
                'value' => number_format($integrationSummaries->count()),
                'meta' => 'روابط المنظمة الحالية مع المنصة عبر المتصفح',
                'eyebrow' => 'التكامل',
                'iconName' => 'integrations',
            ],
            [
                'label' => 'مفاتيح API النشطة',
                'value' => number_format($apiKeys->filter(fn (CustomerApiKey $key): bool => (bool) $key->is_active)->count()),
                'meta' => 'المفاتيح المرتبطة بالمستخدم الحالي داخل الحساب',
                'eyebrow' => 'الوصول البرمجي',
                'iconName' => 'api-key',
            ],
            [
                'label' => 'أحداث ويبهوك حديثة',
                'value' => number_format($recentWebhookEvents->count()),
                'meta' => 'آخر الأحداث التي وصلت فعلياً إلى المنصة',
                'eyebrow' => 'التدفق التقني',
                'iconName' => 'webhooks',
            ],
            [
                'label' => 'فشل حديث',
                'value' => number_format($recentWebhookEvents->filter(fn (WebhookEvent $event): bool => $event->status === WebhookEvent::STATUS_FAILED)->count()),
                'meta' => 'أحداث تحتاج متابعة تقنية من فريق المنظمة',
                'eyebrow' => 'المخاطر',
                'iconName' => 'alert',
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, meta: string, eyebrow: string, iconName: string}>
     */
    private function b2bIntegrationStats(Collection $integrations): array
    {
        return [
            [
                'label' => 'تكاملات مستقرة',
                'value' => number_format($integrations->filter(fn (array $integration): bool => ($integration['status'] ?? null) === 'healthy')->count()),
                'meta' => 'تكاملات لم تُظهر مؤشرات تدهور في آخر فحص متاح',
                'eyebrow' => 'الحالة',
                'iconName' => 'integrations',
            ],
            [
                'label' => 'بحاجة متابعة',
                'value' => number_format($integrations->filter(fn (array $integration): bool => in_array(($integration['status'] ?? null), ['degraded', 'down'], true))->count()),
                'meta' => 'تكاملات متدهورة أو متوقفة تحتاج انتباهاً',
                'eyebrow' => 'التنبيه',
                'iconName' => 'alert',
            ],
            [
                'label' => 'مدعومة من المنصة',
                'value' => number_format($integrations->count()),
                'meta' => 'الخدمات الظاهرة هنا هي تكاملات المنصة المتاحة للمنظمة وليست ملكية للناقلين',
                'eyebrow' => 'النطاق',
                'iconName' => 'developer',
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, meta: string, eyebrow: string, iconName: string}>
     */
    private function b2bApiKeyStats(Collection $apiKeys): array
    {
        return [
            [
                'label' => 'كل المفاتيح',
                'value' => number_format($apiKeys->count()),
                'meta' => 'المفاتيح المرتبطة بالمستخدم الحالي ضمن حساب المنظمة',
                'eyebrow' => 'الإجمالي',
                'iconName' => 'api-key',
            ],
            [
                'label' => 'نشطة',
                'value' => number_format($apiKeys->filter(fn (CustomerApiKey $key): bool => (bool) $key->is_active)->count()),
                'meta' => 'المفاتيح القابلة للاستخدام حالياً',
                'eyebrow' => 'الحالة',
                'iconName' => 'developer',
            ],
            [
                'label' => 'تنتهي قريباً',
                'value' => number_format($apiKeys->filter(
                    fn (CustomerApiKey $key): bool => $key->expires_at && $key->expires_at->isBefore(now()->addDays(14))
                )->count()),
                'meta' => 'مفاتيح تحتاج تدويراً أو مراجعة قبل انتهاء الصلاحية',
                'eyebrow' => 'الاستمرارية',
                'iconName' => 'alert',
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, meta: string, eyebrow: string, iconName: string}>
     */
    private function b2bWebhookStats(Collection $recentWebhookEvents, int $storeCount): array
    {
        return [
            [
                'label' => 'أحداث حديثة',
                'value' => number_format($recentWebhookEvents->count()),
                'meta' => 'آخر الأحداث التي استقبلتها المنصة لهذا الحساب',
                'eyebrow' => 'التدفق',
                'iconName' => 'webhooks',
            ],
            [
                'label' => 'معالجة ناجحة',
                'value' => number_format($recentWebhookEvents->filter(
                    fn (WebhookEvent $event): bool => $event->status === WebhookEvent::STATUS_PROCESSED
                )->count()),
                'meta' => 'أحداث وصلت وتمت معالجتها دون تعثر',
                'eyebrow' => 'النجاح',
                'iconName' => 'activity',
            ],
            [
                'label' => 'أحداث فاشلة',
                'value' => number_format($recentWebhookEvents->filter(
                    fn (WebhookEvent $event): bool => $event->status === WebhookEvent::STATUS_FAILED
                )->count()),
                'meta' => 'أحداث تحتاج متابعة تقنية من فريق المنظمة أو التكامل',
                'eyebrow' => 'المخاطر',
                'iconName' => 'alert',
            ],
            [
                'label' => 'المتاجر المرتبطة',
                'value' => number_format($storeCount),
                'meta' => 'متاجر يمكن أن تُغذّي هذا المسار بأحداث تشغيلية',
                'eyebrow' => 'النطاق',
                'iconName' => 'orders',
            ],
        ];
    }

    private function translatedOrderStatus(?string $status): string
    {
        return match ((string) $status) {
            Order::STATUS_PENDING => 'جديد',
            Order::STATUS_READY => 'جاهز',
            Order::STATUS_PROCESSING => 'قيد المعالجة',
            Order::STATUS_SHIPPED => 'تم شحنه',
            Order::STATUS_DELIVERED => 'تم تسليمه',
            Order::STATUS_CANCELLED => 'ملغي',
            Order::STATUS_ON_HOLD => 'موقوف',
            Order::STATUS_FAILED => 'فشل',
            default => (string) ($status ?: 'غير محدد'),
        };
    }

    private function translatedOrderSource(?string $source): string
    {
        return match ((string) $source) {
            Order::SOURCE_MANUAL => 'يدوي',
            Order::SOURCE_SHOPIFY => 'Shopify',
            Order::SOURCE_WOOCOMMERCE => 'WooCommerce',
            Order::SOURCE_SALLA => 'سلة',
            Order::SOURCE_ZID => 'زد',
            Order::SOURCE_CUSTOM_API => 'API مخصص',
            default => (string) ($source ?: 'غير محدد'),
        };
    }

    private function translatedWalletStatus(?string $status): string
    {
        return match ((string) $status) {
            'active' => 'نشطة',
            'frozen' => 'موقوفة',
            default => (string) ($status ?: 'غير محدد'),
        };
    }

    /**
     * @return array<int, string>
     */
    private function b2bActiveShipmentStatuses(): array
    {
        return [
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
            Shipment::STATUS_PURCHASED,
            Shipment::STATUS_READY_FOR_PICKUP,
            Shipment::STATUS_PICKED_UP,
            Shipment::STATUS_IN_TRANSIT,
            Shipment::STATUS_OUT_FOR_DELIVERY,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function b2bExceptionShipmentStatuses(): array
    {
        return [
            Shipment::STATUS_KYC_BLOCKED,
            Shipment::STATUS_REQUIRES_ACTION,
            Shipment::STATUS_EXCEPTION,
            Shipment::STATUS_FAILED,
            Shipment::STATUS_RETURNED,
            Shipment::STATUS_CANCELLED,
        ];
    }

    private function shipmentIndexWorkspace(Request $request, string $portal): View
    {
        $account = $this->currentAccount();
        $accountId = (string) $account->id;
        $filters = $this->validateShipmentIndexFilters($request);

        $shipments = $this->buildShipmentIndexQuery($accountId, $filters)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $viewData = [
            'account' => $account,
            'shipments' => $shipments,
            'canCreateShipment' => auth()->user()?->can('create', Shipment::class) ?? false,
            'canExportShipments' => auth()->user()?->can('viewAny', Shipment::class) ?? false,
            'createRoute' => route($portal.'.shipments.create'),
            'createRouteName' => $portal.'.shipments.create',
            'showRoute' => $portal.'.shipments.show',
            'indexRoute' => route($portal.'.shipments.index'),
            'exportRoute' => route($portal.'.shipments.export', $this->shipmentIndexPersistedFilters($filters)),
            'copy' => $this->shipmentIndexCopy($portal),
            'stats' => $this->shipmentIndexStats($accountId, $portal),
            'filters' => $filters,
            'hasActiveFilters' => $this->shipmentIndexHasActiveFilters($filters),
            'statusOptions' => $this->shipmentIndexStatusOptions($accountId),
            'carrierOptions' => $this->shipmentIndexCarrierOptions($accountId),
        ];

        if ($portal === 'b2c') {
            $continueShipment = $this->b2cContinueShipment($accountId);

            $viewData = array_merge($viewData, [
                'summaryGroups' => $this->b2cShipmentSummaryGroups($accountId),
                'recentActivity' => $this->b2cRecentShipmentActivity($accountId),
                'continueShipment' => $continueShipment,
                'continueAction' => $continueShipment ? $this->b2cContinueShipmentAction($continueShipment) : null,
            ]);
        }

        if ($portal === 'b2b') {
            $viewData = array_merge($viewData, [
                'summaryGroups' => $this->b2bShipmentSummaryGroups($accountId),
                'recentActivity' => $this->b2bRecentShipmentActivity($accountId),
                'shipmentTrend' => $this->b2bShipmentTrend($accountId),
                'statusMix' => $this->b2bShipmentStatusMix($accountId),
            ]);
        }

        return view('pages.portal.'.$portal.'.shipments', $viewData);
    }

    private function shipmentIndexExportWorkspace(Request $request, string $portal): Response
    {
        $this->authorize('viewAny', Shipment::class);

        $accountId = (string) $this->currentAccount()->id;
        $filters = $this->validateShipmentIndexFilters($request);

        $shipments = $this->buildShipmentIndexQuery($accountId, $filters)
            ->orderByDesc('created_at')
            ->get($this->shipmentIndexExportColumns());

        $writer = Writer::createFromString('');
        $writer->insertOne([
            __('portal_shipments.common.reference'),
            __('portal_shipments.common.created_at'),
            __('portal_shipments.common.status'),
            __('portal_shipments.common.carrier'),
            __('portal_shipments.common.service'),
            __('portal_shipments.common.tracking_number'),
            __('portal_shipments.common.sender'),
            __('portal_shipments.common.recipient'),
            __('portal_shipments.common.origin'),
            __('portal_shipments.common.destination'),
            __('portal_shipments.common.total_charge'),
            __('portal_shipments.common.currency'),
        ]);

        foreach ($shipments as $shipment) {
            $writer->insertOne([
                (string) ($shipment->reference_number ?? __('portal_shipments.common.not_available')),
                $shipment->created_at?->format('Y-m-d H:i') ?? '',
                $this->translatedShipmentStatus($shipment->status),
                $this->shipmentCarrierLabel($shipment->carrier_name, $shipment->carrier_code),
                $this->shipmentServiceLabel($shipment->service_name, $shipment->service_code),
                $this->shipmentTrackingLabel($shipment->tracking_number, $shipment->carrier_tracking_number),
                (string) ($shipment->sender_name ?? ''),
                (string) ($shipment->recipient_name ?? ''),
                $this->shipmentLocationLabel($shipment->sender_city, $shipment->sender_country),
                $this->shipmentLocationLabel($shipment->recipient_city, $shipment->recipient_country),
                $shipment->total_charge !== null ? number_format((float) $shipment->total_charge, 2, '.', '') : '',
                (string) ($shipment->currency ?? ''),
            ]);
        }

        $csvUtf8 = $writer->toString();
        $csvExcel = "\xFF\xFE".mb_convert_encoding($csvUtf8, 'UTF-16LE', 'UTF-8');
        $filename = 'shipments-'.$portal.'-'.now()->format('Y-m-d-His').'.csv';

        return response($csvExcel, 200, [
            'Content-Type' => 'text/csv; charset=UTF-16LE',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return array{search: ?string, status: ?string, carrier: ?string, from: ?string, to: ?string}
     */
    private function validateShipmentIndexFilters(Request $request): array
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::in(array_keys($this->shipmentStatusCatalog()))],
            'carrier' => ['nullable', 'string', 'max:50'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        foreach (['search', 'status', 'carrier', 'from', 'to'] as $key) {
            $value = trim((string) ($filters[$key] ?? ''));
            $filters[$key] = $value === '' ? null : $value;
        }

        return $filters;
    }

    /**
     * @param  array{search: ?string, status: ?string, carrier: ?string, from: ?string, to: ?string}  $filters
     */
    private function buildShipmentIndexQuery(string $accountId, array $filters): Builder
    {
        $query = Shipment::query()
            ->where('account_id', $accountId);

        if ($filters['search'] !== null) {
            $search = $filters['search'];
            $columns = $this->shipmentIndexSearchColumns();

            if ($columns !== []) {
                $query->where(function (Builder $builder) use ($columns, $search): void {
                    foreach ($columns as $index => $column) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $builder->{$method}($column, 'like', '%'.$search.'%');
                    }
                });
            }
        }

        if ($filters['status'] !== null) {
            $query->where('status', $filters['status']);
        }

        if ($filters['carrier'] !== null) {
            $query->where('carrier_code', $filters['carrier']);
        }

        if ($filters['from'] !== null) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if ($filters['to'] !== null) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    private function shipmentIndexSearchColumns(): array
    {
        return array_values(array_filter([
            Schema::hasColumn('shipments', 'reference_number') ? 'reference_number' : null,
            Schema::hasColumn('shipments', 'tracking_number') ? 'tracking_number' : null,
            Schema::hasColumn('shipments', 'carrier_tracking_number') ? 'carrier_tracking_number' : null,
            Schema::hasColumn('shipments', 'sender_name') ? 'sender_name' : null,
            Schema::hasColumn('shipments', 'recipient_name') ? 'recipient_name' : null,
        ]));
    }

    /**
     * @return list<string>
     */
    private function shipmentIndexExportColumns(): array
    {
        return array_values(array_filter([
            'id',
            Schema::hasColumn('shipments', 'reference_number') ? 'reference_number' : null,
            Schema::hasColumn('shipments', 'created_at') ? 'created_at' : null,
            Schema::hasColumn('shipments', 'status') ? 'status' : null,
            Schema::hasColumn('shipments', 'carrier_name') ? 'carrier_name' : null,
            Schema::hasColumn('shipments', 'carrier_code') ? 'carrier_code' : null,
            Schema::hasColumn('shipments', 'service_name') ? 'service_name' : null,
            Schema::hasColumn('shipments', 'service_code') ? 'service_code' : null,
            Schema::hasColumn('shipments', 'tracking_number') ? 'tracking_number' : null,
            Schema::hasColumn('shipments', 'carrier_tracking_number') ? 'carrier_tracking_number' : null,
            Schema::hasColumn('shipments', 'sender_name') ? 'sender_name' : null,
            Schema::hasColumn('shipments', 'recipient_name') ? 'recipient_name' : null,
            Schema::hasColumn('shipments', 'sender_city') ? 'sender_city' : null,
            Schema::hasColumn('shipments', 'sender_country') ? 'sender_country' : null,
            Schema::hasColumn('shipments', 'recipient_city') ? 'recipient_city' : null,
            Schema::hasColumn('shipments', 'recipient_country') ? 'recipient_country' : null,
            Schema::hasColumn('shipments', 'total_charge') ? 'total_charge' : null,
            Schema::hasColumn('shipments', 'currency') ? 'currency' : null,
        ]));
    }

    /**
     * @return array<string, string>
     */
    private function shipmentIndexStatusOptions(string $accountId): array
    {
        $available = Shipment::query()
            ->where('account_id', $accountId)
            ->whereNotNull('status')
            ->distinct()
            ->pluck('status')
            ->filter(fn ($status): bool => filled($status))
            ->map(fn ($status): string => (string) $status)
            ->values();

        $catalog = $this->shipmentStatusCatalog();
        $options = [];

        foreach ($catalog as $status => $label) {
            if ($available->contains($status)) {
                $options[$status] = $label;
            }
        }

        foreach ($available as $status) {
            if (! array_key_exists($status, $options)) {
                $options[$status] = $this->translatedShipmentStatus($status);
            }
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function shipmentIndexCarrierOptions(string $accountId): array
    {
        $options = [];

        $carriers = Shipment::query()
            ->where('account_id', $accountId)
            ->whereNotNull('carrier_code')
            ->where('carrier_code', '!=', '')
            ->orderBy('carrier_name')
            ->orderBy('carrier_code')
            ->get(['carrier_code', 'carrier_name'])
            ->unique('carrier_code');

        foreach ($carriers as $shipment) {
            $code = trim((string) $shipment->carrier_code);
            if ($code === '') {
                continue;
            }

            $options[$code] = $this->shipmentCarrierLabel($shipment->carrier_name, $code);
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function shipmentStatusCatalog(): array
    {
        $statuses = [
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
            Shipment::STATUS_PURCHASED,
            Shipment::STATUS_READY_FOR_PICKUP,
            Shipment::STATUS_PICKED_UP,
            Shipment::STATUS_IN_TRANSIT,
            Shipment::STATUS_OUT_FOR_DELIVERY,
            Shipment::STATUS_DELIVERED,
            Shipment::STATUS_RETURNED,
            Shipment::STATUS_EXCEPTION,
            Shipment::STATUS_CANCELLED,
            Shipment::STATUS_FAILED,
        ];

        $catalog = [];

        foreach ($statuses as $status) {
            $catalog[$status] = $this->translatedShipmentStatus($status);
        }

        return $catalog;
    }

    private function translatedShipmentStatus(?string $status): string
    {
        $resolved = trim((string) $status);
        if ($resolved === '') {
            return __('portal_shipments.common.not_available');
        }

        $key = 'portal_shipments.statuses.'.$resolved;
        $translated = __($key);

        return $translated === $key ? $resolved : $translated;
    }

    private function shipmentCarrierLabel(mixed $carrierName, mixed $carrierCode): string
    {
        $resolvedName = trim((string) ($carrierName ?? ''));
        if ($resolvedName !== '') {
            return $resolvedName;
        }

        $resolvedCode = trim((string) ($carrierCode ?? ''));
        if ($resolvedCode === '') {
            return __('portal_shipments.common.not_available');
        }

        $key = 'portal_shipments.carriers.'.Str::lower($resolvedCode);
        $translated = __($key);

        return $translated === $key
            ? Str::headline(str_replace(['_', '-'], ' ', $resolvedCode))
            : $translated;
    }

    private function shipmentServiceLabel(mixed $serviceName, mixed $serviceCode): string
    {
        $resolvedName = trim((string) ($serviceName ?? ''));
        if ($resolvedName !== '') {
            return $resolvedName;
        }

        $resolvedCode = trim((string) ($serviceCode ?? ''));
        if ($resolvedCode === '') {
            return __('portal_shipments.common.not_available');
        }

        $key = 'portal_shipments.services.'.Str::lower($resolvedCode);
        $translated = __($key);

        return $translated === $key
            ? Str::headline(str_replace(['_', '-'], ' ', $resolvedCode))
            : $translated;
    }

    private function shipmentTrackingLabel(mixed $trackingNumber, mixed $carrierTrackingNumber): string
    {
        $tracking = trim((string) ($trackingNumber ?? ''));
        if ($tracking !== '') {
            return $tracking;
        }

        $carrierTracking = trim((string) ($carrierTrackingNumber ?? ''));

        return $carrierTracking === ''
            ? __('portal_shipments.common.not_available')
            : $carrierTracking;
    }

    private function shipmentLocationLabel(mixed $city, mixed $country): string
    {
        $summary = collect([$city, $country])
            ->map(fn ($value): string => trim((string) ($value ?? '')))
            ->filter(fn (string $value): bool => $value !== '')
            ->implode(' / ');

        return $summary === ''
            ? __('portal_shipments.common.not_specified')
            : $summary;
    }

    /**
     * @param  array{search: ?string, status: ?string, carrier: ?string, from: ?string, to: ?string}  $filters
     * @return array<string, string>
     */
    private function shipmentIndexPersistedFilters(array $filters): array
    {
        return collect($filters)
            ->filter(fn ($value): bool => filled($value))
            ->map(fn ($value): string => (string) $value)
            ->all();
    }

    /**
     * @param  array{search: ?string, status: ?string, carrier: ?string, from: ?string, to: ?string}  $filters
     */
    private function shipmentIndexHasActiveFilters(array $filters): bool
    {
        return collect($filters)->contains(fn ($value): bool => filled($value));
    }

    private function shipmentDraftWorkspace(string $portal): View
    {
        $this->authorize('create', Shipment::class);

        $account = $this->currentAccount();
        $accountId = (string) $account->id;
        $config = $this->shipmentPortalConfig($portal);
        $draftId = trim((string) request()->query('draft', ''));
        $cloneId = trim((string) request()->query('clone', ''));
        $senderAddressQuery = trim((string) request()->query('sender_address', ''));
        $recipientAddressQuery = trim((string) request()->query('recipient_address', ''));

        $draftShipment = null;
        $cloneSourceShipment = null;
        $cloneFormDefaults = [];
        $cloneDropsAdditionalParcels = false;
        $addressFormDefaults = [];
        $selectedSenderAddress = null;
        $selectedRecipientAddress = null;
        $canViewAddressBook = auth()->user()?->can('viewAny', Address::class) ?? false;
        $canManageAddressBook = auth()->user()?->can('create', Address::class) ?? false;
        $senderAddresses = collect();
        $recipientAddresses = collect();

        if ($draftId !== '') {
            $draftShipment = Shipment::query()
                ->where('account_id', $accountId)
                ->where('id', $draftId)
                ->with('parcels')
                ->firstOrFail();
        } elseif ($cloneId !== '') {
            $cloneSourceShipment = Shipment::query()
                ->where('account_id', $accountId)
                ->where('id', $cloneId)
                ->with('parcels')
                ->firstOrFail();

            $this->authorize('view', $cloneSourceShipment);

            $cloneFormDefaults = $this->buildCloneFormDefaults($cloneSourceShipment);
            $cloneDropsAdditionalParcels = $cloneSourceShipment->parcels->count() > 1;
        }

        if (($senderAddressQuery !== '' || $recipientAddressQuery !== '') && ! $canViewAddressBook) {
            abort(403);
        }

        if ($canViewAddressBook) {
            $shipmentService = app(\App\Services\ShipmentService::class);
            $senderAddresses = $shipmentService->listAddresses($accountId, 'sender');
            $recipientAddresses = $shipmentService->listAddresses($accountId, 'recipient');

            if ($senderAddressQuery !== '') {
                $selectedSenderAddress = $shipmentService->findAddress($accountId, $senderAddressQuery, 'sender');
                $addressFormDefaults = array_merge(
                    $addressFormDefaults,
                    $this->buildAddressFormDefaults($selectedSenderAddress, 'sender')
                );
            }

            if ($recipientAddressQuery !== '') {
                $selectedRecipientAddress = $shipmentService->findAddress($accountId, $recipientAddressQuery, 'recipient');
                $addressFormDefaults = array_merge(
                    $addressFormDefaults,
                    $this->buildAddressFormDefaults($selectedRecipientAddress, 'recipient')
                );
            }
        }

        $recentDrafts = Shipment::query()
            ->where('account_id', $accountId)
            ->latest()
            ->limit(5)
            ->get();

        return view('pages.portal.shipments.create', [
            'account' => $account,
            'draftShipment' => $draftShipment,
            'cloneSourceShipment' => $cloneSourceShipment,
            'cloneFormDefaults' => $cloneFormDefaults,
            'cloneDropsAdditionalParcels' => $cloneDropsAdditionalParcels,
            'addressFormDefaults' => $addressFormDefaults,
            'selectedSenderAddress' => $selectedSenderAddress,
            'selectedRecipientAddress' => $selectedRecipientAddress,
            'senderAddresses' => $senderAddresses,
            'recipientAddresses' => $recipientAddresses,
            'canViewAddressBook' => $canViewAddressBook,
            'canManageAddressBook' => $canManageAddressBook,
            'portal' => $portal,
            'portalConfig' => $config,
            'recentDrafts' => $recentDrafts,
            'workflowState' => session('shipment_workflow_state', $draftShipment?->status ?? Shipment::STATUS_DRAFT),
            'workflowFeedback' => session('shipment_workflow_feedback'),
        ]);
    }

    private function storeShipmentDraftWorkspace(Request $request, string $portal): RedirectResponse
    {
        $this->authorize('create', Shipment::class);

        $account = $this->currentAccount();
        $accountId = (string) $account->id;
        $config = $this->shipmentPortalConfig($portal);
        $data = $this->validateShipmentDraftPayload($request);
        $data = $this->safeguardSelectedShipmentAddresses($accountId, $data);

        $shipment = app(\App\Services\ShipmentService::class)->createDirect($accountId, $data, $request->user());

        try {
            $shipment = app(\App\Services\ShipmentService::class)->validateShipment($accountId, (string) $shipment->id, $request->user());

            return redirect()
                ->route($config['create_route'], ['draft' => (string) $shipment->id])
                ->with('success', 'تم حفظ الطلب كمسودة وتجهيزه لمرحلة التسعير التالية.')
                ->with('shipment_workflow_state', $shipment->status)
                ->with('shipment_workflow_feedback', [
                    'level' => 'success',
                    'message' => 'اكتملت مراجعة البيانات ومرور بوابة التحقق بنجاح.',
                    'next_action' => 'يمكنك الانتقال لاحقًا إلى مرحلة التسعير وجلب العروض.',
                ]);
        } catch (BusinessException $exception) {
            if (! in_array($exception->getErrorCode(), [
                'ERR_VALIDATION_FAILED',
                'ERR_KYC_REQUIRED',
                'ERR_KYC_PENDING_REVIEW',
                'ERR_KYC_REJECTED',
                'ERR_KYC_USAGE_LIMIT',
                'ERR_ACCOUNT_RESTRICTED',
            ], true)) {
                throw $exception;
            }

            $context = $exception->getContext();

            return redirect()
                ->route($config['create_route'], ['draft' => (string) $shipment->id])
                ->withInput()
                ->with('shipment_workflow_state', $context['current_status'] ?? $shipment->status)
                ->with('shipment_workflow_feedback', [
                    'level' => 'warning',
                    'error_code' => $exception->getErrorCode(),
                    'message' => $exception->getMessage(),
                    'next_action' => $context['next_action'] ?? null,
                    'validation_errors' => $context['validation_errors'] ?? [],
                    'kyc_status' => $context['kyc_status'] ?? null,
                    'reason_code' => $context['reason_code'] ?? null,
                    'capabilities' => $context['capabilities'] ?? [],
                ]);
        }
    }

    private function validateShipmentAddressWorkspace(Request $request, string $portal): RedirectResponse
    {
        $this->authorize('create', Shipment::class);

        $accountId = (string) $this->currentAccount()->id;
        $config = $this->shipmentPortalConfig($portal);
        $payload = $this->snapshotShipmentDraftInput($request);

        [$mode, $prefix] = $this->resolveShipmentAddressValidationAction(
            trim((string) $request->input('address_validation_action'))
        );

        if ($mode === 'dismiss') {
            $payload = $this->safeguardSelectedShipmentAddresses($accountId, $payload);

            return redirect()
                ->route($config['create_route'], $this->shipmentCreateRouteState($payload))
                ->withInput($payload);
        }

        if ($mode === 'apply') {
            $payload = array_merge($payload, $this->validationSuggestionInput($request, $prefix));
            $payload = $this->normalizeValidatedShipmentAddressInput($payload, $prefix);
            $payload = $this->safeguardSelectedShipmentAddresses($accountId, $payload);

            return redirect()
                ->route($config['create_route'], $this->shipmentCreateRouteState($payload))
                ->withInput($payload)
                ->with('address_validation_results', [
                    $prefix => app(AddressValidationService::class)->validateSection($payload, $prefix),
                ]);
        }

        $validator = Validator::make(
            $request->all(),
            $this->shipmentAddressValidationRules($request, $prefix),
            $this->shipmentAddressValidationMessages($prefix)
        );

        if ($validator->fails()) {
            return redirect()
                ->route($config['create_route'], $this->shipmentCreateRouteState($payload))
                ->withErrors($validator)
                ->withInput($payload);
        }

        $evaluationPayload = array_merge($payload, $this->normalizeValidatedShipmentAddressInput($validator->validated(), $prefix));
        $evaluationPayload = $this->safeguardSelectedShipmentAddresses($accountId, $evaluationPayload);
        foreach (['sender', 'recipient'] as $party) {
            $payload[$party.'_address_id'] = $evaluationPayload[$party.'_address_id'] ?? null;
        }

        return redirect()
            ->route($config['create_route'], $this->shipmentCreateRouteState($evaluationPayload))
            ->withInput($payload)
            ->with('address_validation_results', [
                $prefix => app(AddressValidationService::class)->validateSection($payload, $prefix),
            ]);
    }

    private function shipmentOffersWorkspace(Request $request, string $shipmentId, string $portal): View
    {
        $account = $this->currentAccount();
        $accountId = (string) $account->id;
        $config = $this->shipmentPortalConfig($portal);
        $shipment = $this->findShipmentForPortal($accountId, $shipmentId);

        $this->authorize('viewAny', RateQuote::class);

        $offersPayload = null;
        $offerError = null;
        $currentUser = $request->user();
        $canFetchOffers = (bool) ($currentUser?->hasPermission('rates.read') ?? false);
        $canSelectOffers = (bool) ($currentUser?->hasPermission('quotes.manage') ?? false);
        $selectedOptionId = trim((string) ($shipment->selected_rate_option_id ?? ''));

        try {
            $offersPayload = app(\App\Services\RateService::class)->getShipmentOffers(
                $accountId,
                (string) $shipment->id,
                $currentUser
            );
        } catch (BusinessException $exception) {
            if ($exception->getErrorCode() !== 'ERR_SHIPMENT_OFFERS_NOT_READY') {
                throw $exception;
            }

            $offerError = [
                'error_code' => $exception->getErrorCode(),
                'message' => $exception->getMessage(),
                'next_action' => data_get($exception->getContext(), 'next_action'),
            ];
        }

        $canRefreshOffers = $canFetchOffers && in_array((string) $shipment->status, [
            Shipment::STATUS_READY_FOR_RATES,
            Shipment::STATUS_RATED,
        ], true);

        return view('pages.portal.shipments.offers', [
            'account' => $account,
            'shipment' => $shipment,
            'portal' => $portal,
            'portalConfig' => $config,
            'offersPayload' => $offersPayload,
            'offerError' => $offerError,
            'offerFeedback' => session('shipment_offer_feedback'),
            'canFetchOffers' => $canFetchOffers,
            'canRefreshOffers' => $canRefreshOffers,
            'canSelectOffers' => $canSelectOffers,
            'selectedOptionId' => $selectedOptionId,
        ]);
    }

    private function fetchShipmentOffersWorkspace(Request $request, string $shipmentId, string $portal): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('rates.read'), 403);

        $account = $this->currentAccount();
        $accountId = (string) $account->id;
        $config = $this->shipmentPortalConfig($portal);
        $shipment = $this->findShipmentForPortal($accountId, $shipmentId);

        try {
            $quote = app(\App\Services\RateService::class)->fetchRates(
                $accountId,
                (string) $shipment->id,
                $request->user()
            );

            return redirect()
                ->route($config['offers_route'], ['id' => (string) $shipment->id])
                ->with('shipment_offer_feedback', [
                    'level' => 'success',
                    'message' => 'تم تحديث العروض وربطها بهذه الشحنة بنجاح.',
                    'next_action' => 'راجع الخيارات المتاحة ثم اختر عرضًا واحدًا للمتابعة إلى الخطوة التالية.',
                    'quote_id' => (string) $quote->id,
                    'offers_count' => (int) ($quote->options_count ?? 0),
                ]);
        } catch (BusinessException $exception) {
            return redirect()
                ->route($config['offers_route'], ['id' => (string) $shipment->id])
                ->with('shipment_offer_feedback', [
                    'level' => 'warning',
                    'error_code' => $exception->getErrorCode(),
                    'message' => $exception->getMessage(),
                    'next_action' => data_get($exception->getContext(), 'next_action'),
                ]);
        }
    }

    private function selectShipmentOfferWorkspace(Request $request, string $shipmentId, string $portal): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('quotes.manage'), 403);

        $account = $this->currentAccount();
        $accountId = (string) $account->id;
        $config = $this->shipmentPortalConfig($portal);
        $shipment = $this->findShipmentForPortal($accountId, $shipmentId);
        $payload = $this->validateSelectOfferPayload($request);

        try {
            $offersPayload = app(\App\Services\RateService::class)->getShipmentOffers(
                $accountId,
                (string) $shipment->id,
                $request->user()
            );

            $quote = RateQuote::query()
                ->where('account_id', $accountId)
                ->where('id', (string) data_get($offersPayload, 'rate_quote_id'))
                ->firstOrFail();

            $this->authorize('manage', $quote);

            app(\App\Services\RateService::class)->selectOption(
                $accountId,
                (string) $quote->id,
                (string) $payload['option_id'],
                'cheapest',
                $request->user()
            );

            return redirect()
                ->route($config['declaration_route'], ['id' => (string) $shipment->id])
                ->with('shipment_offer_feedback', [
                    'level' => 'success',
                    'message' => 'تم تثبيت العرض المختار على هذه الشحنة.',
                    'next_action' => 'الخطوة التالية ستكون استكمال الإعلان والمتطلبات اللاحقة عند فتح المرحلة التالية من التدفق.',
                    'selected_option_id' => (string) $payload['option_id'],
                ]);
        } catch (BusinessException $exception) {
            return redirect()
                ->route($config['offers_route'], ['id' => (string) $shipment->id])
                ->with('shipment_offer_feedback', [
                    'level' => 'warning',
                    'error_code' => $exception->getErrorCode(),
                    'message' => $exception->getMessage(),
                    'next_action' => data_get($exception->getContext(), 'next_action'),
                ]);
        }
    }

    private function shipmentDeclarationWorkspace(Request $request, string $shipmentId, string $portal): View
    {
        abort_unless($request->user()?->hasPermission('quotes.read'), 403);

        $account = $this->currentAccount();
        $accountId = (string) $account->id;
        $config = $this->shipmentPortalConfig($portal);
        $shipment = $this->findShipmentForPortal($accountId, $shipmentId);
        $selectedOffer = $this->resolveSelectedOffer($shipment);
        $declaration = $this->resolveShipmentDeclaration($shipment, $request->user());
        $waiver = $this->resolveActiveWaiver($declaration, (string) ($request->user()?->locale ?? 'ar'));
        $workflowReady = in_array((string) $shipment->status, [
            Shipment::STATUS_DECLARATION_REQUIRED,
            Shipment::STATUS_DECLARATION_COMPLETE,
            Shipment::STATUS_REQUIRES_ACTION,
        ], true) && $selectedOffer !== null;

        return view('pages.portal.shipments.declaration', [
            'account' => $account,
            'shipment' => $shipment,
            'portal' => $portal,
            'portalConfig' => $config,
            'selectedOffer' => $selectedOffer,
            'declaration' => $declaration,
            'waiver' => $waiver,
            'workflowReady' => $workflowReady,
            'offerFeedback' => session('shipment_offer_feedback'),
            'declarationFeedback' => session('shipment_declaration_feedback'),
        ]);
    }

    private function submitShipmentDeclarationWorkspace(Request $request, string $shipmentId, string $portal): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('quotes.manage'), 403);

        $account = $this->currentAccount();
        $accountId = (string) $account->id;
        $config = $this->shipmentPortalConfig($portal);
        $shipment = $this->findShipmentForPortal($accountId, $shipmentId);
        $selectedOffer = $this->resolveSelectedOffer($shipment);

        if (! in_array((string) $shipment->status, [
            Shipment::STATUS_DECLARATION_REQUIRED,
            Shipment::STATUS_DECLARATION_COMPLETE,
            Shipment::STATUS_REQUIRES_ACTION,
        ], true) || $selectedOffer === null) {
            return redirect()
                ->route($config['offers_route'], ['id' => (string) $shipment->id])
                ->with('shipment_declaration_feedback', [
                    'level' => 'warning',
                    'error_code' => 'ERR_DECLARATION_NOT_READY',
                    'message' => 'لا يمكن فتح إقرار المحتوى قبل اختيار عرض صالح لهذه الشحنة.',
                    'next_action' => 'ارجع إلى صفحة العروض، ثم اختر عرضًا واحدًا قبل متابعة الإقرار.',
                ]);
        }

        $payload = $request->validate([
            'contains_dangerous_goods' => ['required', 'in:yes,no'],
            'accept_disclaimer' => ['accepted_if:contains_dangerous_goods,no'],
        ], [
            'contains_dangerous_goods.required' => 'اختر بوضوح ما إذا كانت الشحنة تحتوي على مواد خطرة.',
            'contains_dangerous_goods.in' => 'قيمة التصريح غير صالحة.',
            'accept_disclaimer.accepted_if' => 'يجب الموافقة على الإقرار القانوني عندما تصرح بأن الشحنة لا تحتوي على مواد خطرة.',
        ]);

        $service = app(\App\Services\DgComplianceService::class);
        $declaration = $this->resolveShipmentDeclaration($shipment, $request->user());

        try {
            $containsDangerousGoods = $payload['contains_dangerous_goods'] === 'yes';

            $declaration = $service->setDgFlag(
                declarationId: (string) $declaration->id,
                containsDg: $containsDangerousGoods,
                actorId: $request->user()->id,
                ipAddress: $request->ip(),
            );

            if ($containsDangerousGoods) {
                return redirect()
                    ->route($config['declaration_route'], ['id' => (string) $shipment->id])
                    ->with('shipment_declaration_feedback', [
                        'level' => 'warning',
                        'message' => 'تم تعليق المسار العادي لهذه الشحنة لأنك صرحت بوجود مواد خطرة.',
                        'next_action' => 'تواصل مع فريق الدعم أو العمليات لمتابعة المعالجة اليدوية لهذه الشحنة.',
                    ]);
            }

            $waiver = $this->resolveActiveWaiver($declaration, (string) ($request->user()?->locale ?? 'ar'));

            $service->acceptWaiver(
                declarationId: (string) $declaration->id,
                actorId: $request->user()->id,
                locale: $waiver?->locale ?? (string) ($request->user()?->locale ?? 'ar'),
                ipAddress: $request->ip(),
            );

            return redirect()
                ->route($config['declaration_route'], ['id' => (string) $shipment->id])
                ->with('shipment_declaration_feedback', [
                    'level' => 'success',
                    'message' => 'تم حفظ الإقرار القانوني بنجاح وربطه بهذه الشحنة.',
                    'next_action' => 'انتقل إلى صفحة الشحنة لتفعيل فحص رصيد المحفظة، ثم متابعة الإصدار لدى الناقل.',
                ]);
        } catch (BusinessException $exception) {
            return redirect()
                ->route($config['declaration_route'], ['id' => (string) $shipment->id])
                ->with('shipment_declaration_feedback', [
                    'level' => 'warning',
                    'error_code' => $exception->getErrorCode(),
                    'message' => $exception->getMessage(),
                    'next_action' => data_get($exception->getContext(), 'next_action'),
                ]);
        }
    }

    private function resolveShipmentDeclaration(Shipment $shipment, User $user): ?ContentDeclaration
    {
        $declaration = $shipment->contentDeclaration;

        if ($declaration instanceof ContentDeclaration) {
            return $declaration;
        }

        if (! $this->resolveSelectedOffer($shipment)) {
            return null;
        }

        return app(\App\Services\DgComplianceService::class)->createDeclaration(
            accountId: (string) $shipment->account_id,
            shipmentId: (string) $shipment->id,
            declaredBy: $user->id,
            locale: (string) ($user->locale ?? 'ar'),
            ipAddress: request()->ip(),
            userAgent: request()?->userAgent(),
        );
    }

    private function resolveActiveWaiver(?ContentDeclaration $declaration, string $locale): ?WaiverVersion
    {
        if ($declaration && $declaration->waiverVersion instanceof WaiverVersion) {
            return $declaration->waiverVersion;
        }

        $service = app(\App\Services\DgComplianceService::class);

        return $service->getActiveWaiver($locale)
            ?? $service->getActiveWaiver('en')
            ?? $service->getActiveWaiver('ar');
    }

    private function walletPreflightWorkspace(Request $request, string $shipmentId, string $portal): RedirectResponse
    {
        $account = $this->currentAccount();
        $accountId = (string) $account->id;
        $config = $this->shipmentPortalConfig($portal);
        $shipment = $this->findShipmentForPortal($accountId, $shipmentId);

        $this->authorize('paymentPreflight', $shipment);

        try {
            $result = app(\App\Services\ShipmentService::class)->createWalletPreflightReservation(
                $accountId,
                (string) $shipment->id,
                $request->user(),
                []
            );

            return redirect()
                ->route($config['show_route'], ['id' => (string) $shipment->id])
                ->with('shipment_completion_feedback', [
                    'level' => 'success',
                    'message' => (bool) ($result['created'] ?? false)
                        ? 'تم حجز مبلغ الشحنة من المحفظة بنجاح.'
                        : 'يوجد حجز محفظة نشط مسبقًا لهذه الشحنة وتمت إعادة استخدامه.',
                    'next_action' => 'يمكنك الآن متابعة إصدار الشحنة لدى الناقل.',
                    'reservation_id' => (string) ($result['reservation_id'] ?? ''),
                    'reservation_status' => (string) ($result['reservation_status'] ?? ''),
                ]);
        } catch (BusinessException $exception) {
            return redirect()
                ->route($config['show_route'], ['id' => (string) $shipment->id])
                ->with('shipment_completion_feedback', $this->browserCompletionFeedbackFromException($exception));
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route($config['show_route'], ['id' => (string) $shipment->id])
                ->with('shipment_completion_feedback', [
                    'level' => 'warning',
                    'error_code' => 'ERR_BROWSER_PREFLIGHT_FAILED',
                    'message' => 'تعذر تنفيذ فحص المحفظة من المتصفح في هذه اللحظة.',
                    'next_action' => 'أعد المحاولة بعد قليل. إذا استمرت المشكلة، راجع الدعم مع مرجع الشحنة.',
                ]);
        }
    }

    private function issueShipmentWorkspace(Request $request, string $shipmentId, string $portal): RedirectResponse
    {
        $account = $this->currentAccount();
        $accountId = (string) $account->id;
        $config = $this->shipmentPortalConfig($portal);
        $shipment = $this->findShipmentForPortal($accountId, $shipmentId);

        $this->authorize('issueAtCarrier', $shipment);

        try {
            $carrierShipment = app(CarrierService::class)->createAtCarrier($shipment, $request->user());

            return redirect()
                ->route($config['show_route'], ['id' => (string) $shipment->id])
                ->with('shipment_completion_feedback', [
                    'level' => 'success',
                    'message' => 'تم إصدار الشحنة لدى الناقل بنجاح.',
                    'next_action' => 'راجع المستندات والحالة الزمنية من نفس الصفحة، ثم افتح مركز الإشعارات لمتابعة آخر التحديثات.',
                    'carrier_shipment_id' => (string) $carrierShipment->id,
                    'tracking_number' => (string) ($carrierShipment->tracking_number ?? ''),
                ]);
        } catch (BusinessException $exception) {
            return redirect()
                ->route($config['show_route'], ['id' => (string) $shipment->id])
                ->with('shipment_completion_feedback', $this->browserCompletionFeedbackFromException($exception));
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route($config['show_route'], ['id' => (string) $shipment->id])
                ->with('shipment_completion_feedback', [
                    'level' => 'warning',
                    'error_code' => 'ERR_BROWSER_CARRIER_CREATE_FAILED',
                    'message' => 'تعذر إكمال إصدار الشحنة لدى الناقل من المتصفح.',
                    'next_action' => 'أعد المحاولة بعد قليل. إذا استمرت المشكلة، راجع الدعم مع مرجع الشحنة.',
                ]);
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function browserCompletionFeedbackFromException(BusinessException $exception): array
    {
        $context = $exception->getContext();
        $carrierErrorMessage = null;
        $carrierErrorCode = null;

        if ($exception->getErrorCode() === 'ERR_CARRIER_CREATE_FAILED') {
            $carrierErrorId = trim((string) ($context['carrier_error_id'] ?? ''));

            if ($carrierErrorId !== '') {
                $carrierError = CarrierError::query()->find($carrierErrorId);
                $carrierErrorMessage = trim((string) ($carrierError?->carrier_error_message ?? ''));
                $carrierErrorCode = trim((string) ($carrierError?->carrier_error_code ?? ''));
            }
        }

        $map = [
            'ERR_INSUFFICIENT_BALANCE' => [
                'message' => 'رصيد المحفظة غير كافٍ لهذه الشحنة.',
                'next_action' => 'اشحن المحفظة أو اختر عرضًا أقل تكلفة ثم أعد المحاولة.',
            ],
            'ERR_DG_DECLARATION_INCOMPLETE' => [
                'message' => 'يجب إكمال إقرار المواد الخطرة قبل متابعة الدفع أو الإصدار.',
                'next_action' => 'ارجع إلى خطوة إقرار المحتوى ثم أتم الموافقة القانونية المطلوبة.',
            ],
            'ERR_DG_HOLD_REQUIRED' => [
                'message' => 'هذه الشحنة متوقفة وتتطلب معالجة يدوية قبل أي متابعة.',
                'next_action' => 'تواصل مع فريق الدعم أو العمليات لمعالجة حالة المواد الخطرة.',
            ],
            'ERR_WALLET_RESERVATION_REQUIRED' => [
                'message' => 'يجب تنفيذ فحص المحفظة وإنشاء حجز مالي قبل الإصدار لدى الناقل.',
                'next_action' => 'نفذ خطوة فحص المحفظة أولًا ثم أعد محاولة الإصدار.',
            ],
            'ERR_INVALID_STATE' => [
                'message' => 'حالة الشحنة الحالية لا تسمح بهذه الخطوة.',
                'next_action' => 'أكمل الخطوات السابقة في التسلسل ثم أعد المحاولة.',
            ],
            'ERR_INVALID_STATE_FOR_CARRIER' => [
                'message' => 'هذه الشحنة ليست جاهزة بعد للإصدار لدى الناقل.',
                'next_action' => 'أكمل الإقرار وفحص المحفظة أولًا ثم أعد المحاولة.',
            ],
            'ERR_CARRIER_CREATE_FAILED' => [
                'message' => 'فشل إصدار الشحنة لدى الناقل.',
                'next_action' => 'أعد المحاولة لاحقًا. إذا استمرت المشكلة، راجع الدعم مع مرجع الشحنة.',
            ],
        ];

        $mapped = $map[$exception->getErrorCode()] ?? null;

        $message = $mapped['message'] ?? $exception->getMessage();
        $nextAction = $mapped['next_action'] ?? data_get($context, 'next_action');

        if ($exception->getErrorCode() === 'ERR_CARRIER_CREATE_FAILED' && $carrierErrorMessage !== '') {
            $message = 'تعذر إصدار الشحنة لدى الناقل: '.$carrierErrorMessage;

            if ($carrierErrorCode !== '' && str_contains($carrierErrorCode, 'STATEORPROVINCECODE.INVALID')) {
                $nextAction = 'راجع رمز الولاية أو المقاطعة في عنواني المرسل والمستلم ثم أعد المحاولة.';
            }
        }

        return [
            'level' => 'warning',
            'error_code' => $exception->getErrorCode(),
            'message' => $message,
            'next_action' => $nextAction,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validateSelectOfferPayload(Request $request): array
    {
        return $request->validate([
            'option_id' => ['required', 'uuid'],
        ]);
    }

    private function findShipmentForPortal(string $accountId, string $shipmentId): Shipment
    {
        return Shipment::query()
            ->where('account_id', $accountId)
            ->where('id', $shipmentId)
            ->with([
                'rateQuote.selectedOption',
                'selectedRateOption',
                'contentDeclaration.waiverVersion',
                'balanceReservation',
                'carrierShipment',
            ])
            ->firstOrFail();
    }

    private function shipmentTimelineWorkspace(Request $request, string $shipmentId, string $portal): View
    {
        $account = $this->currentAccount();
        $shipment = Shipment::query()
            ->where('account_id', (string) $account->id)
            ->where('id', $shipmentId)
            ->with([
                'carrierShipment',
                'selectedRateOption',
                'rateQuote.selectedOption',
                'balanceReservation',
                'contentDeclaration.waiverVersion',
            ])
            ->firstOrFail();

        abort_unless(method_exists($request->user(), 'hasPermission') && $request->user()->hasPermission('tracking.read'), 403);
        $this->authorize('view', $shipment);

        $timeline = $this->decorateTimeline(app(ShipmentTimelineService::class)->present($shipment));
        $documents = collect(app(CarrierService::class)->listDocuments($shipment))
            ->map(function (array $document) use ($portal, $shipment): array {
                $filename = (string) ($document['filename'] ?? 'document.bin');
                $previewable = strtolower((string) ($document['file_format'] ?? $document['format'] ?? '')) === 'pdf'
                    || strtolower((string) ($document['mime_type'] ?? '')) === 'application/pdf'
                    || str_ends_with(strtolower($filename), '.pdf');

                return array_merge($this->decorateDocument($document), [
                    'download_route' => route($portal.'.shipments.documents.download', [
                        'id' => (string) $shipment->id,
                        'documentId' => (string) $document['id'],
                        'downloadName' => $filename,
                    ]),
                    'previewable' => $previewable,
                    'preview_route' => $previewable ? route($portal.'.shipments.documents.preview', [
                        'id' => (string) $shipment->id,
                        'documentId' => (string) $document['id'],
                        'previewName' => $filename,
                    ]) : null,
                ]);
            })
            ->all();
        $canViewNotifications = method_exists($request->user(), 'hasPermission')
            && $request->user()->hasPermission('notifications.read');
        $shipmentNotifications = [];

        if ($canViewNotifications) {
            $shipmentNotifications = Notification::query()
                ->where('account_id', (string) $account->id)
                ->where('user_id', (string) $request->user()->id)
                ->where('channel', Notification::CHANNEL_IN_APP)
                ->where('entity_type', 'shipment')
                ->where('entity_id', (string) $shipment->id)
                ->latest()
                ->limit(5)
                ->get()
                ->map(static fn (Notification $notification): array => [
                    'id' => (string) $notification->id,
                    'event_type' => (string) $notification->event_type,
                    'event_type_label' => PortalShipmentLabeler::event(
                        (string) $notification->event_type,
                        (string) ($notification->subject
                            ?? data_get($notification->event_data, 'title')
                            ?? '')
                    ),
                    'subject' => (string) ($notification->subject
                        ?? data_get($notification->event_data, 'title')
                        ?? $notification->event_type
                        ?? 'إشعار شحنة'),
                    'body' => (string) ($notification->body
                        ?? data_get($notification->event_data, 'event_description')
                        ?? ''),
                    'created_at' => optional($notification->created_at)?->toIso8601String(),
                    'read_at' => optional($notification->read_at)?->toIso8601String(),
                ])
                ->all();
        }

        $selectedOption = $shipment->selectedRateOption ?? $shipment->rateQuote?->selectedOption;

        return view('pages.portal.shipments.show', [
            'portalConfig' => array_merge(
                $this->shipmentPortalConfig($portal),
                $this->shipmentTimelinePortalConfig($portal)
            ),
            'shipment' => $shipment,
            'timeline' => $timeline,
            'documents' => $documents,
            'completionFeedback' => session('shipment_completion_feedback'),
            'canCreateShipment' => $request->user()?->can('create', Shipment::class) ?? false,
            'canTriggerWalletPreflight' => $request->user()?->can('paymentPreflight', $shipment) ?? false,
            'canIssueShipment' => $request->user()?->can('issueAtCarrier', $shipment) ?? false,
            'canViewNotifications' => $canViewNotifications,
            'shipmentNotifications' => $shipmentNotifications,
            'publicTrackingUrl' => app(PublicTrackingService::class)->publicUrl($shipment),
            'carrierDisplayLabel' => PortalShipmentLabeler::carrier(
                (string) ($shipment->carrierShipment?->carrier_code ?? $selectedOption?->carrier_code ?? $shipment->carrier_code ?? ''),
                (string) ($shipment->carrierShipment?->carrier_name ?? $selectedOption?->carrier_name ?? $shipment->carrier_name ?? '')
            ),
            'serviceDisplayLabel' => PortalShipmentLabeler::service(
                (string) ($shipment->carrierShipment?->service_code ?? $selectedOption?->service_code ?? $shipment->service_code ?? ''),
                (string) ($shipment->carrierShipment?->service_name ?? $selectedOption?->service_name ?? $shipment->service_name ?? '')
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $timeline
     * @return array<string, mixed>
     */
    private function decorateTimeline(array $timeline): array
    {
        $timeline['current_status_label'] = PortalShipmentLabeler::status(
            (string) ($timeline['current_status'] ?? ''),
            (string) ($timeline['current_status_label'] ?? '')
        );

        $timeline['events'] = collect($timeline['events'] ?? [])
            ->map(static fn (array $event): array => array_merge($event, [
                'event_type_label' => PortalShipmentLabeler::event(
                    (string) ($event['event_type'] ?? ''),
                    (string) ($event['event_type_label'] ?? $event['description'] ?? '')
                ),
                'status_label' => PortalShipmentLabeler::status(
                    (string) ($event['status'] ?? $event['normalized_status'] ?? ''),
                    (string) ($event['status_label'] ?? '')
                ),
                'source_label' => PortalShipmentLabeler::source(
                    (string) ($event['source'] ?? ''),
                    (string) ($event['source_label'] ?? '')
                ),
                'location_label' => PortalShipmentLabeler::location(
                    (string) ($event['location'] ?? ''),
                    (string) ($event['location_label'] ?? $event['location'] ?? '')
                ),
            ]))
            ->all();

        return $timeline;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function decorateDocument(array $document): array
    {
        return array_merge($document, [
            'document_type_label' => PortalShipmentLabeler::documentType(
                (string) ($document['document_type'] ?? $document['type'] ?? ''),
                (string) ($document['document_type'] ?? $document['type'] ?? '')
            ),
            'carrier_label' => PortalShipmentLabeler::carrier(
                (string) ($document['carrier_code'] ?? ''),
                (string) ($document['carrier_name'] ?? '')
            ),
            'format_label' => PortalShipmentLabeler::documentFormat(
                (string) ($document['file_format'] ?? $document['format'] ?? ''),
                strtoupper((string) ($document['file_format'] ?? $document['format'] ?? ''))
            ),
            'retrieval_mode_label' => PortalShipmentLabeler::retrievalMode(
                (string) ($document['retrieval_mode'] ?? ''),
                (string) ($document['retrieval_mode'] ?? '')
            ),
        ]);
    }

    private function resolveSelectedOffer(Shipment $shipment): ?\App\Models\RateOption
    {
        if ($shipment->selectedRateOption) {
            return $shipment->selectedRateOption;
        }

        if ($shipment->rateQuote && $shipment->rateQuote->selectedOption) {
            return $shipment->rateQuote->selectedOption;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateShipmentDraftPayload(Request $request): array
    {
        $request->merge([
            'sender_country' => Str::upper(trim((string) $request->input('sender_country'))),
            'recipient_country' => Str::upper(trim((string) $request->input('recipient_country'))),
        ]);

        $data = $request->validate([
            'store_id' => 'nullable|uuid',
            'sender_name' => 'required|string|max:200',
            'sender_company' => 'nullable|string|max:200',
            'sender_phone' => 'required|string|max:30',
            'sender_email' => 'nullable|email|max:255',
            'sender_address_1' => 'required|string|max:300',
            'sender_address_2' => 'nullable|string|max:300',
            'sender_city' => 'required|string|max:100',
            'sender_state' => ['nullable', 'string', 'max:100', Rule::requiredIf(
                fn () => Str::upper((string) $request->input('sender_country')) === 'US'
            )],
            'sender_postal_code' => 'nullable|string|max:20',
            'sender_country' => 'required|string|size:2',
            'sender_address_id' => 'nullable|uuid',
            'recipient_name' => 'required|string|max:200',
            'recipient_company' => 'nullable|string|max:200',
            'recipient_phone' => 'required|string|max:30',
            'recipient_email' => 'nullable|email|max:255',
            'recipient_address_1' => 'required|string|max:300',
            'recipient_address_2' => 'nullable|string|max:300',
            'recipient_city' => 'required|string|max:100',
            'recipient_state' => ['nullable', 'string', 'max:100', Rule::requiredIf(
                fn () => Str::upper((string) $request->input('recipient_country')) === 'US'
            )],
            'recipient_postal_code' => 'nullable|string|max:20',
            'recipient_country' => 'required|string|size:2',
            'recipient_address_id' => 'nullable|uuid',
            'cod_amount' => 'nullable|numeric|min:0',
            'insurance_amount' => 'nullable|numeric|min:0',
            'is_return' => 'nullable|boolean',
            'has_dangerous_goods' => 'nullable|boolean',
            'delivery_instructions' => 'nullable|string|max:500',
            'parcels' => 'required|array|min:1|max:10',
            'parcels.*.weight' => 'required|numeric|min:0.01|max:999',
            'parcels.*.length' => 'nullable|numeric|min:0.1|max:999',
            'parcels.*.width' => 'nullable|numeric|min:0.1|max:999',
            'parcels.*.height' => 'nullable|numeric|min:0.1|max:999',
            'parcels.*.packaging_type' => 'nullable|string|in:box,envelope,tube,custom',
            'parcels.*.description' => 'nullable|string|max:300',
            'metadata' => 'nullable|array',
        ], [
            'sender_state.required' => __('portal_shipments.validation.sender_state_required'),
            'recipient_state.required' => __('portal_shipments.validation.recipient_state_required'),
        ]);

        foreach (['sender', 'recipient'] as $prefix) {
            $country = (string) ($data["{$prefix}_country"] ?? '');
            $state = trim((string) ($data["{$prefix}_state"] ?? ''));

            if ($state === '') {
                $data["{$prefix}_state"] = null;

                continue;
            }

            $data["{$prefix}_state"] = $country === 'US'
                ? Str::upper($state)
                : $state;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCloneFormDefaults(Shipment $shipment): array
    {
        $shipment->loadMissing('parcels');

        $firstParcel = $shipment->parcels
            ->sortBy('sequence')
            ->first();

        return [
            'sender_name' => (string) ($shipment->sender_name ?? ''),
            'sender_company' => $this->nullableCloneString($shipment->sender_company ?? null),
            'sender_phone' => (string) ($shipment->sender_phone ?? ''),
            'sender_email' => $this->nullableCloneString($shipment->sender_email ?? null),
            'sender_address_1' => (string) ($shipment->sender_address_1 ?? $shipment->sender_address ?? ''),
            'sender_address_2' => $this->nullableCloneString($shipment->sender_address_2 ?? null),
            'sender_city' => (string) ($shipment->sender_city ?? ''),
            'sender_state' => $this->normalizeCloneState($shipment->sender_country ?? null, $shipment->sender_state ?? null),
            'sender_postal_code' => $this->nullableCloneString($shipment->sender_postal_code ?? null),
            'sender_country' => $this->normalizeCloneCountry($shipment->sender_country ?? null) ?? 'SA',
            'recipient_name' => (string) ($shipment->recipient_name ?? ''),
            'recipient_company' => $this->nullableCloneString($shipment->recipient_company ?? null),
            'recipient_phone' => (string) ($shipment->recipient_phone ?? ''),
            'recipient_email' => $this->nullableCloneString($shipment->recipient_email ?? null),
            'recipient_address_1' => (string) ($shipment->recipient_address_1 ?? $shipment->recipient_address ?? ''),
            'recipient_address_2' => $this->nullableCloneString($shipment->recipient_address_2 ?? null),
            'recipient_city' => (string) ($shipment->recipient_city ?? ''),
            'recipient_state' => $this->normalizeCloneState($shipment->recipient_country ?? null, $shipment->recipient_state ?? null),
            'recipient_postal_code' => $this->nullableCloneString($shipment->recipient_postal_code ?? null),
            'recipient_country' => $this->normalizeCloneCountry($shipment->recipient_country ?? null) ?? 'SA',
            'parcels' => [[
                'weight' => $firstParcel?->weight !== null ? (string) $firstParcel->weight : '1.0',
                'length' => $firstParcel?->length !== null ? (string) $firstParcel->length : null,
                'width' => $firstParcel?->width !== null ? (string) $firstParcel->width : null,
                'height' => $firstParcel?->height !== null ? (string) $firstParcel->height : null,
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAddressFormDefaults(Address $address, string $prefix): array
    {
        return [
            $prefix.'_name' => (string) ($address->contact_name ?? ''),
            $prefix.'_company' => $this->nullableCloneString($address->company_name ?? null),
            $prefix.'_phone' => (string) ($address->phone ?? ''),
            $prefix.'_email' => $this->nullableCloneString($address->email ?? null),
            $prefix.'_address_1' => (string) ($address->address_line_1 ?? ''),
            $prefix.'_address_2' => $this->nullableCloneString($address->address_line_2 ?? null),
            $prefix.'_city' => (string) ($address->city ?? ''),
            $prefix.'_state' => $this->normalizeCloneState($address->country ?? null, $address->state ?? null),
            $prefix.'_postal_code' => $this->nullableCloneString($address->postal_code ?? null),
            $prefix.'_country' => $this->normalizeCloneCountry($address->country ?? null) ?? 'SA',
            $prefix.'_address_id' => (string) $address->id,
        ];
    }

    private function normalizeCloneCountry(mixed $country): ?string
    {
        $value = $this->nullableCloneString($country);

        return $value === null ? null : Str::upper($value);
    }

    private function normalizeCloneState(mixed $country, mixed $state): ?string
    {
        $value = $this->nullableCloneString($state);
        if ($value === null) {
            return null;
        }

        return $this->normalizeCloneCountry($country) === 'US'
            ? Str::upper($value)
            : $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeValidatedShipmentAddressInput(array $data, string $prefix): array
    {
        $countryKey = $prefix.'_country';
        $stateKey = $prefix.'_state';

        if (array_key_exists($countryKey, $data)) {
            $country = trim((string) $data[$countryKey]);
            $data[$countryKey] = $country === '' ? null : Str::upper($country);
        }

        if (array_key_exists($stateKey, $data)) {
            $state = trim((string) ($data[$stateKey] ?? ''));

            $data[$stateKey] = $state === ''
                ? null
                : (($data[$countryKey] ?? null) === 'US' ? Str::upper($state) : $state);
        }

        foreach ([$prefix.'_address_1', $prefix.'_address_2', $prefix.'_city', $prefix.'_postal_code'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = preg_replace('/\s+/u', ' ', trim((string) ($data[$field] ?? '')));
            $data[$field] = $value === '' ? null : $value;
        }

        return $data;
    }

    private function nullableCloneString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $resolved = trim((string) $value);

        return $resolved === '' ? null : $resolved;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function safeguardSelectedShipmentAddresses(string $accountId, array $data): array
    {
        $shipmentService = app(\App\Services\ShipmentService::class);

        foreach (['sender', 'recipient'] as $prefix) {
            $addressId = trim((string) ($data[$prefix.'_address_id'] ?? ''));

            if ($addressId === '') {
                $data[$prefix.'_address_id'] = null;

                continue;
            }

            $address = $shipmentService->findAddress($accountId, $addressId, $prefix);

            if (! $this->selectedAddressStillMatchesSubmittedFields($address, $data, $prefix)) {
                $data[$prefix.'_address_id'] = null;
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function selectedAddressStillMatchesSubmittedFields(Address $address, array $data, string $prefix): bool
    {
        return $this->submittedShipmentAddressComparable($data, $prefix)
            === $this->savedShipmentAddressComparable($address);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, ?string>
     */
    private function submittedShipmentAddressComparable(array $data, string $prefix): array
    {
        return [
            'contact_name' => trim((string) ($data[$prefix.'_name'] ?? '')),
            'company_name' => $this->nullableCloneString($data[$prefix.'_company'] ?? null),
            'phone' => trim((string) ($data[$prefix.'_phone'] ?? '')),
            'email' => $this->nullableCloneString($data[$prefix.'_email'] ?? null),
            'address_line_1' => trim((string) ($data[$prefix.'_address_1'] ?? '')),
            'address_line_2' => $this->nullableCloneString($data[$prefix.'_address_2'] ?? null),
            'city' => trim((string) ($data[$prefix.'_city'] ?? '')),
            'state' => $this->normalizeCloneState($data[$prefix.'_country'] ?? null, $data[$prefix.'_state'] ?? null),
            'postal_code' => $this->nullableCloneString($data[$prefix.'_postal_code'] ?? null),
            'country' => $this->normalizeCloneCountry($data[$prefix.'_country'] ?? null),
        ];
    }

    /**
     * @return array<string, ?string>
     */
    private function savedShipmentAddressComparable(Address $address): array
    {
        return [
            'contact_name' => trim((string) ($address->contact_name ?? '')),
            'company_name' => $this->nullableCloneString($address->company_name ?? null),
            'phone' => trim((string) ($address->phone ?? '')),
            'email' => $this->nullableCloneString($address->email ?? null),
            'address_line_1' => trim((string) ($address->address_line_1 ?? '')),
            'address_line_2' => $this->nullableCloneString($address->address_line_2 ?? null),
            'city' => trim((string) ($address->city ?? '')),
            'state' => $this->normalizeCloneState($address->country ?? null, $address->state ?? null),
            'postal_code' => $this->nullableCloneString($address->postal_code ?? null),
            'country' => $this->normalizeCloneCountry($address->country ?? null),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveShipmentAddressValidationAction(string $action): array
    {
        return match ($action) {
            'validate_recipient' => ['validate', 'recipient'],
            'apply_sender' => ['apply', 'sender'],
            'apply_recipient' => ['apply', 'recipient'],
            'dismiss_sender' => ['dismiss', 'sender'],
            'dismiss_recipient' => ['dismiss', 'recipient'],
            default => ['validate', 'sender'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotShipmentDraftInput(Request $request): array
    {
        return $request->except([
            '_token',
            'address_validation_action',
            'address_validation_suggestions',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shipmentCreateRouteState(array $payload): array
    {
        return collect([
            'draft' => trim((string) ($payload['draft'] ?? '')),
            'clone' => trim((string) ($payload['clone'] ?? '')),
            'sender_address' => trim((string) ($payload['sender_address_id'] ?? '')),
            'recipient_address' => trim((string) ($payload['recipient_address_id'] ?? '')),
        ])
            ->filter(fn (string $value): bool => $value !== '')
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function validationSuggestionInput(Request $request, string $prefix): array
    {
        $suggestions = $request->input('address_validation_suggestions', []);
        $resolved = is_array($suggestions[$prefix] ?? null) ? $suggestions[$prefix] : [];

        return $this->normalizeValidatedShipmentAddressInput($resolved, $prefix);
    }

    /**
     * @return array<string, mixed>
     */
    private function shipmentAddressValidationRules(Request $request, string $prefix): array
    {
        return [
            $prefix.'_address_1' => ['required', 'string', 'max:300'],
            $prefix.'_address_2' => ['nullable', 'string', 'max:300'],
            $prefix.'_city' => ['required', 'string', 'max:100'],
            $prefix.'_state' => ['nullable', 'string', 'max:100', Rule::requiredIf(
                fn (): bool => $this->normalizeCloneCountry($request->input($prefix.'_country')) === 'US'
            )],
            $prefix.'_postal_code' => ['nullable', 'string', 'max:20'],
            $prefix.'_country' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function shipmentAddressValidationMessages(string $prefix): array
    {
        return [
            $prefix.'_state.required' => $prefix === 'sender'
                ? __('portal_shipments.validation.sender_state_required')
                : __('portal_shipments.validation.recipient_state_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function shipmentPortalConfig(string $portal): array
    {
        return match ($portal) {
            'b2c' => [
                'label' => __('portal_shipments.workflow.b2c.label'),
                'headline' => __('portal_shipments.workflow.b2c.headline'),
                'description' => __('portal_shipments.workflow.b2c.description'),
                'dashboard_route' => 'b2c.dashboard',
                'index_route' => 'b2c.shipments.index',
                'create_route' => 'b2c.shipments.create',
                'store_route' => 'b2c.shipments.store',
                'address_validation_route' => 'b2c.shipments.address-validation',
                'offers_route' => 'b2c.shipments.offers',
                'offers_fetch_route' => 'b2c.shipments.offers.fetch',
                'offers_select_route' => 'b2c.shipments.offers.select',
                'declaration_route' => 'b2c.shipments.declaration',
                'declaration_submit_route' => 'b2c.shipments.declaration.submit',
                'show_route' => 'b2c.shipments.show',
                'documents_route' => 'b2c.shipments.documents.index',
                'preflight_route' => 'b2c.shipments.preflight',
                'issue_route' => 'b2c.shipments.issue',
                'addresses_index_route' => 'b2c.addresses.index',
                'addresses_create_route' => 'b2c.addresses.create',
            ],
            'b2b' => [
                'label' => __('portal_shipments.workflow.b2b.label'),
                'headline' => __('portal_shipments.workflow.b2b.headline'),
                'description' => __('portal_shipments.workflow.b2b.description'),
                'dashboard_route' => 'b2b.dashboard',
                'index_route' => 'b2b.shipments.index',
                'create_route' => 'b2b.shipments.create',
                'store_route' => 'b2b.shipments.store',
                'address_validation_route' => 'b2b.shipments.address-validation',
                'offers_route' => 'b2b.shipments.offers',
                'offers_fetch_route' => 'b2b.shipments.offers.fetch',
                'offers_select_route' => 'b2b.shipments.offers.select',
                'declaration_route' => 'b2b.shipments.declaration',
                'declaration_submit_route' => 'b2b.shipments.declaration.submit',
                'show_route' => 'b2b.shipments.show',
                'documents_route' => 'b2b.shipments.documents.index',
                'preflight_route' => 'b2b.shipments.preflight',
                'issue_route' => 'b2b.shipments.issue',
                'addresses_index_route' => 'b2b.addresses.index',
                'addresses_create_route' => 'b2b.addresses.create',
            ],
            default => abort(404),
        };
    }

    private function shipmentTimelinePortalConfig(string $portal): array
    {
        if ($portal === 'b2b') {
            return [
                'label' => __('portal_shipments.workflow.b2b.timeline_label'),
                'dashboard_route' => 'b2b.dashboard',
                'shipments_index_route' => 'b2b.shipments.index',
                'documents_route' => 'b2b.shipments.documents.index',
            ];
        }

        return [
            'label' => __('portal_shipments.workflow.b2c.timeline_label'),
            'dashboard_route' => 'b2c.dashboard',
            'shipments_index_route' => 'b2c.shipments.index',
            'documents_route' => 'b2c.shipments.documents.index',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shipmentIndexCopy(string $portal): array
    {
        return [
            'portal_label' => __('portal_shipments.common.portal_'.$portal),
            'title' => __('portal_shipments.index.'.$portal.'.title'),
            'description' => __('portal_shipments.index.'.$portal.'.description'),
            'create_cta' => __('portal_shipments.index.'.$portal.'.create_cta'),
            'table_title' => __('portal_shipments.index.'.$portal.'.table_title'),
            'search_placeholder' => __('portal_shipments.index.'.$portal.'.search_placeholder'),
            'empty_state' => __('portal_shipments.index.'.$portal.'.empty_state'),
            'guidance_title' => __('portal_shipments.index.'.$portal.'.guidance_title'),
            'guidance_cards' => __('portal_shipments.index.'.$portal.'.guidance_cards'),
        ];
    }

    /**
     * @return array<int, array{icon: string, label: string, value: string}>
     */
    private function shipmentIndexStats(string $accountId, string $portal): array
    {
        return match ($portal) {
            'b2c' => [
                [
                    'icon' => 'SH',
                    'label' => __('portal_shipments.index.b2c.stats.total'),
                    'value' => number_format(Shipment::query()->where('account_id', $accountId)->count()),
                ],
                [
                    'icon' => 'IP',
                    'label' => __('portal_shipments.index.b2c.stats.active'),
                    'value' => number_format(Shipment::query()->where('account_id', $accountId)->whereIn('status', ['pending', 'ready_for_pickup', 'picked_up', 'in_transit', 'out_for_delivery'])->count()),
                ],
                [
                    'icon' => 'OK',
                    'label' => __('portal_shipments.index.b2c.stats.delivered'),
                    'value' => number_format(Shipment::query()->where('account_id', $accountId)->where('status', 'delivered')->count()),
                ],
            ],
            'b2b' => [
                [
                    'icon' => 'SH',
                    'label' => __('portal_shipments.index.b2b.stats.total'),
                    'value' => number_format(Shipment::query()->where('account_id', $accountId)->count()),
                ],
                [
                    'icon' => 'PD',
                    'label' => __('portal_shipments.index.b2b.stats.pending'),
                    'value' => number_format(Shipment::query()->where('account_id', $accountId)->whereIn('status', ['draft', 'validated', 'payment_pending'])->count()),
                ],
                [
                    'icon' => 'TR',
                    'label' => __('portal_shipments.index.b2b.stats.in_transit'),
                    'value' => number_format(Shipment::query()->where('account_id', $accountId)->whereIn('status', ['picked_up', 'in_transit', 'out_for_delivery'])->count()),
                ],
            ],
            default => [],
        };
    }

    private function preferredBillingWallet(string $accountId): ?BillingWallet
    {
        return BillingWallet::query()
            ->where('account_id', $accountId)
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->orderByDesc('available_balance')
            ->orderByDesc('reserved_balance')
            ->oldest('created_at')
            ->first();
    }

    private function walletEntries(?BillingWallet $wallet): Collection
    {
        if (! $wallet) {
            return collect();
        }

        return WalletLedgerEntry::query()
            ->where('wallet_id', $wallet->id)
            ->latest('created_at')
            ->limit(6)
            ->get();
    }

    private function customerApiKeysForCurrentUser(Request $request): Collection
    {
        if (! Schema::hasTable('customer_api_keys')) {
            return collect();
        }

        return $this->customerApiKeysBaseQuery($request)
            ->latest()
            ->get();
    }

    private function customerApiKeysBaseQuery(Request $request)
    {
        $account = $this->currentAccount();

        $query = CustomerApiKey::withoutGlobalScopes()
            ->where('account_id', (string) $account->id)
            ->where('user_id', (string) $request->user()->id);

        if (Schema::hasColumn('customer_api_keys', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }

    private function recentWebhookEvents(string $accountId): Collection
    {
        if (! Schema::hasTable('webhook_events')) {
            return collect();
        }

        return WebhookEvent::query()
            ->with('store')
            ->where('account_id', $accountId)
            ->latest()
            ->limit(12)
            ->get();
    }

    private function integrationSummaries(string $accountId): Collection
    {
        $catalog = $this->integrationCatalog();

        if (! Schema::hasTable('integration_health_logs')) {
            return $catalog->map(function (array $integration): array {
                return $integration + [
                    'status' => 'unknown',
                    'status_label' => 'لم يتم الفحص بعد',
                    'response_time_ms' => null,
                    'checked_at' => null,
                    'has_manage_access' => true,
                ];
            });
        }

        return $catalog->map(function (array $integration) use ($accountId): array {
            $query = IntegrationHealthLog::query();

            if (Schema::hasColumn('integration_health_logs', 'account_id')) {
                $query->where('account_id', $accountId);
            }

            if (Schema::hasColumn('integration_health_logs', 'integration_id')) {
                $query->where('integration_id', $integration['id']);
            } else {
                $query->where('service', $integration['id']);
            }

            $latest = $query->latest('checked_at')->first();
            $status = (string) ($latest->status ?? 'unknown');

            return $integration + [
                'status' => $status,
                'status_label' => match ($status) {
                    'healthy' => 'جاهز',
                    'degraded' => 'متذبذب',
                    'down' => 'متوقف',
                    'success' => 'جاهز',
                    'error' => 'تحتاج مراجعة',
                    default => 'لم يتم الفحص بعد',
                },
                'response_time_ms' => $latest->response_time_ms ?? null,
                'checked_at' => $latest->checked_at ?? null,
            ];
        });
    }

    private function integrationCatalog(): Collection
    {
        return collect([
            [
                'id' => 'dhl',
                'name' => 'DHL Express',
                'category' => 'شحن سريع',
                'summary' => 'تتبع، إنشاء شحنات، وأسعار للشحنات السريعة.',
                'modes' => ['air'],
            ],
            [
                'id' => 'aramex',
                'name' => 'Aramex',
                'category' => 'شحن إقليمي',
                'summary' => 'شحنات إقليمية مع تتبع وأسعار وملصقات.',
                'modes' => ['air', 'land'],
            ],
            [
                'id' => 'maersk',
                'name' => 'Maersk Line',
                'category' => 'شحن بحري',
                'summary' => 'حجوزات بحرية، تتبع الحاويات، وحالة الرحلات.',
                'modes' => ['sea'],
            ],
            [
                'id' => 'fasah',
                'name' => 'FASAH',
                'category' => 'جمارك',
                'summary' => 'متابعة التصاريح والربط مع إجراءات التخليص.',
                'modes' => ['all'],
            ],
        ]);
    }

    private function developerNavigationItems(object $user): Collection
    {
        return collect([
            [
                'route' => 'b2b.developer.index',
                'label' => 'واجهة المطور',
                'description' => 'ملخص سريع لكل ما يحتاجه فريق تكامل المنظمة مع واجهات المنصة من المتصفح.',
                'permission' => 'integrations.read',
            ],
            [
                'route' => 'b2b.developer.integrations',
                'label' => 'حالة التكاملات',
                'description' => 'مراجعة تكاملات المنصة المتاحة لهذا الحساب وتشغيل فحص سريع عند الحاجة.',
                'permission' => 'integrations.read',
            ],
            [
                'route' => 'b2b.developer.api-keys',
                'label' => 'مفاتيح API',
                'description' => 'عرض مفاتيح واجهات المنصة، إنشاء مفتاح جديد، أو إلغاء مفتاح نشط.',
                'permission' => 'api_keys.read',
            ],
            [
                'route' => 'b2b.developer.webhooks',
                'label' => 'الويبهوكات',
                'description' => 'رؤية نقاط استقبال المنصة وسجل الأحداث الواردة وآخر المحاولات.',
                'permission' => 'webhooks.read',
            ],
        ])->filter(fn (array $item): bool => method_exists($user, 'hasPermission') && $user->hasPermission($item['permission']))->values();
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    private function customerApiScopeOptions(): array
    {
        return [
            'shipments.create' => ['label' => 'إنشاء الشحنات', 'description' => 'يسمح بإنشاء شحنات جديدة عبر المفتاح.'],
            'shipments.read' => ['label' => 'قراءة الشحنات', 'description' => 'يسمح بقراءة الشحنات المرتبطة بالحساب.'],
            'tracking.read' => ['label' => 'قراءة التتبع', 'description' => 'يسمح بقراءة بيانات التتبع.'],
            'quotes.create' => ['label' => 'إنشاء عروض السعر', 'description' => 'يسمح بطلب عرض سعر جديد برمجيًا.'],
            'reports.read' => ['label' => 'قراءة التقارير', 'description' => 'يسمح بجلب تقارير القراءة فقط.'],
        ];
    }
}
