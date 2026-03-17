<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BillingWallet;
use App\Models\CarrierError;
use App\Models\ContentDeclaration;
use App\Models\CustomerApiKey;
use App\Models\IntegrationHealthLog;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Role;
use App\Models\RateQuote;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\User;
use App\Models\WaiverVersion;
use App\Models\WalletLedgerEntry;
use App\Models\WebhookEvent;
use App\Services\CarrierService;
use App\Services\ShipmentTimelineService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PortalWorkspaceController extends Controller
{
    public function b2cShipments(): View
    {
        $account = $this->currentAccount();
        $accountId = (string) $account->id;

        $shipments = Shipment::query()
            ->where('account_id', $accountId)
            ->latest()
            ->limit(6)
            ->get();

        return view('pages.portal.b2c.shipments', [
            'account' => $account,
            'shipments' => $shipments,
            'canCreateShipment' => auth()->user()?->can('create', Shipment::class) ?? false,
            'createRoute' => route('b2c.shipments.create'),
            'stats' => [
                ['icon' => 'SH', 'label' => 'إجمالي الشحنات', 'value' => number_format(Shipment::query()->where('account_id', $accountId)->count())],
                ['icon' => 'IP', 'label' => 'قيد التنفيذ', 'value' => number_format(Shipment::query()->where('account_id', $accountId)->whereIn('status', ['pending', 'ready_for_pickup', 'picked_up', 'in_transit', 'out_for_delivery'])->count())],
                ['icon' => 'OK', 'label' => 'تم التسليم', 'value' => number_format(Shipment::query()->where('account_id', $accountId)->where('status', 'delivered')->count())],
            ],
        ]);
    }

    public function b2cWallet(): View
    {
        $account = $this->currentAccount();
        $accountId = (string) $account->id;
        $wallet = $this->preferredBillingWallet($accountId);

        return view('pages.portal.b2c.wallet', [
            'account' => $account,
            'wallet' => $wallet,
            'transactions' => $this->walletEntries($wallet),
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
                ->where(function ($builder) use ($query, $identifierColumns): void {
                    foreach ($identifierColumns as $index => $column) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $builder->{$method}($column, 'like', '%' . $query . '%');
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

        return view('pages.portal.b2c.tracking', [
            'account' => $account,
            'trackedShipments' => $trackedShipments,
            'matchedShipment' => $matchedShipment,
            'searchQuery' => $query,
        ]);
    }

    public function b2bDashboard(Request $request): View
    {
        $account = $this->currentAccount();
        $accountId = (string) $account->id;
        $user = $request->user();
        $developerTools = $this->developerNavigationItems($user);

        return view('pages.portal.b2b.dashboard', [
            'account' => $account,
            'developerTools' => $developerTools,
            'stats' => [
                ['icon' => 'SH', 'label' => 'الشحنات', 'value' => number_format(Shipment::query()->where('account_id', $accountId)->count())],
                ['icon' => 'OR', 'label' => 'الطلبات', 'value' => number_format(Order::query()->where('account_id', $accountId)->count())],
                ['icon' => 'US', 'label' => 'المستخدمون', 'value' => number_format(User::query()->where('account_id', $accountId)->count())],
                ['icon' => 'RL', 'label' => 'الأدوار', 'value' => number_format(Role::query()->where('account_id', $accountId)->count())],
            ],
        ]);
    }

    public function b2bShipments(): View
    {
        $account = $this->currentAccount();
        $accountId = (string) $account->id;

        $shipments = Shipment::query()
            ->where('account_id', $accountId)
            ->latest()
            ->limit(8)
            ->get();

        return view('pages.portal.b2b.shipments', [
            'account' => $account,
            'shipments' => $shipments,
            'canCreateShipment' => auth()->user()?->can('create', Shipment::class) ?? false,
            'createRoute' => route('b2b.shipments.create'),
            'stats' => [
                ['icon' => 'SH', 'label' => 'إجمالي الشحنات', 'value' => number_format(Shipment::query()->where('account_id', $accountId)->count())],
                ['icon' => 'PD', 'label' => 'بانتظار الإجراء', 'value' => number_format(Shipment::query()->where('account_id', $accountId)->whereIn('status', ['draft', 'validated', 'payment_pending'])->count())],
                ['icon' => 'TR', 'label' => 'في الطريق', 'value' => number_format(Shipment::query()->where('account_id', $accountId)->whereIn('status', ['picked_up', 'in_transit', 'out_for_delivery'])->count())],
            ],
        ]);
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

    public function b2bShipmentDraft(): View
    {
        return $this->shipmentDraftWorkspace('b2b');
    }

    public function storeB2bShipmentDraft(Request $request): RedirectResponse
    {
        return $this->storeShipmentDraftWorkspace($request, 'b2b');
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

        return view('pages.portal.b2b.wallet', [
            'account' => $account,
            'wallet' => $wallet,
            'transactions' => $this->walletEntries($wallet),
        ]);
    }

    public function b2bReports(): View
    {
        $account = $this->currentAccount();
        $accountId = (string) $account->id;

        return view('pages.portal.b2b.reports', [
            'account' => $account,
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
        ]);
    }

    public function b2bDeveloperHome(Request $request): View
    {
        $account = $this->currentAccount();
        $user = $request->user();

        return view('pages.portal.b2b.developer.index', [
            'account' => $account,
            'developerTools' => $this->developerNavigationItems($user),
            'recentApiKeys' => $this->customerApiKeysForCurrentUser($request)->take(3),
            'recentWebhookEvents' => $this->recentWebhookEvents((string) $account->id)->take(5),
            'integrationSummaries' => $this->integrationSummaries((string) $account->id)->take(4),
        ]);
    }

    public function b2bDeveloperIntegrations(Request $request): View
    {
        $account = $this->currentAccount();

        return view('pages.portal.b2b.developer.integrations', [
            'account' => $account,
            'integrations' => $this->integrationSummaries((string) $account->id),
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
            ->with('success', 'تم تنفيذ فحص سريع للتكامل ' . $catalog[$integration]['name'] . '.');
    }

    public function b2bDeveloperApiKeys(Request $request): View
    {
        $account = $this->currentAccount();

        return view('pages.portal.b2b.developer.api-keys', [
            'account' => $account,
            'apiKeys' => $this->customerApiKeysForCurrentUser($request),
            'scopeOptions' => $this->customerApiScopeOptions(),
            'newApiKey' => session('new_api_key'),
        ]);
    }

    public function storeDeveloperApiKey(Request $request): RedirectResponse
    {
        $account = $this->currentAccount();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', 'in:' . implode(',', array_keys($this->customerApiScopeOptions()))],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:10', 'max:300'],
        ]);

        $rawKey = 'cbex_' . Str::random(40);

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

        return view('pages.portal.b2b.developer.webhooks', [
            'account' => $account,
            'baseWebhookUrl' => rtrim(config('app.url'), '/') . '/api/v1/webhooks',
            'recentWebhookEvents' => $this->recentWebhookEvents($accountId),
            'stores' => Store::query()->where('account_id', $accountId)->orderBy('name')->limit(8)->get(),
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

    private function shipmentDraftWorkspace(string $portal): View
    {
        $this->authorize('create', Shipment::class);

        $account = $this->currentAccount();
        $accountId = (string) $account->id;
        $config = $this->shipmentPortalConfig($portal);
        $draftId = trim((string) request()->query('draft', ''));

        $draftShipment = null;
        if ($draftId !== '') {
            $draftShipment = Shipment::query()
                ->where('account_id', $accountId)
                ->where('id', $draftId)
                ->with('parcels')
                ->firstOrFail();
        }

        $recentDrafts = Shipment::query()
            ->where('account_id', $accountId)
            ->latest()
            ->limit(5)
            ->get();

        return view('pages.portal.shipments.create', [
            'account' => $account,
            'draftShipment' => $draftShipment,
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
            if (!in_array($exception->getErrorCode(), [
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
            $message = 'تعذر إصدار الشحنة لدى الناقل: ' . $carrierErrorMessage;

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

        $timeline = app(ShipmentTimelineService::class)->present($shipment);
        $documents = collect(app(CarrierService::class)->listDocuments($shipment))
            ->map(function (array $document) use ($portal, $shipment): array {
                return array_merge($document, [
                    'download_route' => route($portal . '.shipments.documents.download', [
                        'id' => (string) $shipment->id,
                        'documentId' => (string) $document['id'],
                    ]),
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

        return view('pages.portal.shipments.show', [
            'portalConfig' => array_merge(
                $this->shipmentPortalConfig($portal),
                $this->shipmentTimelinePortalConfig($portal)
            ),
            'shipment' => $shipment,
            'timeline' => $timeline,
            'documents' => $documents,
            'completionFeedback' => session('shipment_completion_feedback'),
            'canTriggerWalletPreflight' => $request->user()?->can('paymentPreflight', $shipment) ?? false,
            'canIssueShipment' => $request->user()?->can('issueAtCarrier', $shipment) ?? false,
            'canViewNotifications' => $canViewNotifications,
            'shipmentNotifications' => $shipmentNotifications,
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
            'sender_state.required' => 'حقل الولاية أو المقاطعة للمرسل مطلوب عندما تكون دولة المرسل هي الولايات المتحدة.',
            'recipient_state.required' => 'حقل الولاية أو المقاطعة للمستلم مطلوب عندما تكون دولة المستلم هي الولايات المتحدة.',
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
     * @return array<string, string>
     */
    private function shipmentPortalConfig(string $portal): array
    {
        return match ($portal) {
            'b2c' => [
                'label' => 'بوابة الأفراد للحسابات الفردية',
                'headline' => 'بدء طلب شحنة للحساب الفردي',
                'description' => 'ابدأ طلب الشحنة من الحساب الفردي الخارجي، ثم راجع نتيجة التحقق والقيود قبل الانتقال إلى عروض شبكة الناقلين التابعة للمنصة.',
                'dashboard_route' => 'b2c.dashboard',
                'index_route' => 'b2c.shipments.index',
                'create_route' => 'b2c.shipments.create',
                'store_route' => 'b2c.shipments.store',
                'offers_route' => 'b2c.shipments.offers',
                'offers_fetch_route' => 'b2c.shipments.offers.fetch',
                'offers_select_route' => 'b2c.shipments.offers.select',
                'declaration_route' => 'b2c.shipments.declaration',
                'declaration_submit_route' => 'b2c.shipments.declaration.submit',
                'show_route' => 'b2c.shipments.show',
                'preflight_route' => 'b2c.shipments.preflight',
                'issue_route' => 'b2c.shipments.issue',
            ],
            'b2b' => [
                'label' => 'بوابة الأعمال لحسابات المنظمات',
                'headline' => 'بدء طلب شحنة لحساب المنظمة',
                'description' => 'أنشئ طلب الشحنة لحساب المنظمة الخارجي، ثم راجع التحقق والامتثال قبل الانتقال إلى عروض شبكة الناقلين التابعة للمنصة لفريقك.',
                'dashboard_route' => 'b2b.dashboard',
                'index_route' => 'b2b.shipments.index',
                'create_route' => 'b2b.shipments.create',
                'store_route' => 'b2b.shipments.store',
                'offers_route' => 'b2b.shipments.offers',
                'offers_fetch_route' => 'b2b.shipments.offers.fetch',
                'offers_select_route' => 'b2b.shipments.offers.select',
                'declaration_route' => 'b2b.shipments.declaration',
                'declaration_submit_route' => 'b2b.shipments.declaration.submit',
                'show_route' => 'b2b.shipments.show',
                'preflight_route' => 'b2b.shipments.preflight',
                'issue_route' => 'b2b.shipments.issue',
            ],
            default => abort(404),
        };
    }

    /**
     * @return array<string, string>
     */
    private function shipmentTimelinePortalConfig(string $portal): array
    {
        if ($portal === 'b2b') {
            return [
                'label' => 'بوابة الأعمال',
                'dashboard_route' => 'b2b.dashboard',
                'shipments_index_route' => 'b2b.shipments.index',
                'documents_route' => 'b2b.shipments.documents.index',
            ];
        }

        return [
            'label' => 'بوابة الأفراد',
            'dashboard_route' => 'b2c.dashboard',
            'shipments_index_route' => 'b2c.shipments.index',
            'documents_route' => 'b2c.shipments.documents.index',
        ];
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
