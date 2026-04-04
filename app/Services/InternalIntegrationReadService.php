<?php

namespace App\Services;

use App\Models\Account;
use App\Models\FeatureFlag;
use App\Models\IntegrationHealthLog;
use App\Models\PaymentGateway;
use App\Models\Store;
use App\Models\StoreSyncLog;
use App\Models\TrackingWebhook;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Support\Internal\InternalControlPlane;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InternalIntegrationReadService
{
    /**
     * @var array<string, array{label: string, type_label: string, feature_flag: string|null, health_service: string, config_path: string}>
     */
    private const CARRIERS = [
        'aramex' => ['label' => 'Aramex', 'type_label' => 'Carrier API', 'feature_flag' => 'carrier_aramex', 'health_service' => 'carrier:aramex', 'config_path' => 'services.aramex'],
        'dhl' => ['label' => 'DHL Express', 'type_label' => 'Carrier API', 'feature_flag' => 'carrier_dhl', 'health_service' => 'carrier:dhl', 'config_path' => 'services.dhl'],
        'fedex' => ['label' => 'FedEx', 'type_label' => 'Carrier API', 'feature_flag' => 'carrier_fedex', 'health_service' => 'carrier:fedex', 'config_path' => 'services.fedex'],
        'smsa' => ['label' => 'SMSA', 'type_label' => 'Carrier API', 'feature_flag' => 'carrier_smsa', 'health_service' => 'carrier:smsa', 'config_path' => 'services.smsa'],
    ];

    /**
     * @var array<string, string|null>
     */
    private const STORE_FLAG_KEYS = [
        'custom_api' => null,
        'manual' => null,
        'salla' => 'ecommerce_salla',
        'shopify' => 'ecommerce_shopify',
        'woocommerce' => null,
        'zid' => 'ecommerce_zid',
    ];

    /**
     * @var array<string, string|null>
     */
    private const GATEWAY_FLAG_KEYS = [
        'applepay' => 'payment_applepay',
        'moyasar' => 'payment_moyasar',
        'stcpay' => 'payment_stcpay',
        'stripe' => 'payment_stripe',
    ];

    public function __construct(
        private readonly InternalControlPlane $controlPlane,
    ) {}

    /**
     * @param array{q: string, type: string, state: string, health: string} $filters
     */
    public function paginate(?User $user, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $rows = $this->filteredRows($user, $filters);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $rows->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * @param array{q: string, type: string, state: string, health: string} $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function filteredRows(?User $user, array $filters): Collection
    {
        return $this->visibleRows($user)
            ->filter(function (array $row) use ($filters): bool {
                if ($filters['q'] !== '') {
                    $haystack = Str::lower(implode(' ', array_filter([
                        (string) $row['name'],
                        (string) $row['provider_name'],
                        (string) $row['provider_key'],
                        (string) $row['type_label'],
                        (string) ($row['account_summary']['name'] ?? ''),
                        (string) ($row['account_summary']['slug'] ?? ''),
                    ])));

                    if (! str_contains($haystack, Str::lower($filters['q']))) {
                        return false;
                    }
                }

                if ($filters['type'] !== '' && $row['kind'] !== $filters['type']) {
                    return false;
                }

                if ($filters['health'] !== '' && $row['health_status'] !== $filters['health']) {
                    return false;
                }

                if ($filters['state'] === 'enabled' && ! $row['is_enabled']) {
                    return false;
                }

                if ($filters['state'] === 'configured' && ! $row['is_configured']) {
                    return false;
                }

                if ($filters['state'] === 'disabled' && $row['is_enabled']) {
                    return false;
                }

                if ($filters['state'] === 'attention' && ! $row['needs_attention']) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    /**
     * @return array{total: int, enabled: int, attention: int, carrier: int}
     */
    public function stats(?User $user): array
    {
        $rows = $this->visibleRows($user);

        return [
            'total' => $rows->count(),
            'enabled' => $rows->where('is_enabled', true)->count(),
            'attention' => $rows->where('needs_attention', true)->count(),
            'carrier' => $rows->where('visibility_scope', 'carrier')->count(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findVisibleDetail(?User $user, string $routeKey): ?array
    {
        $row = $this->visibleRows($user)
            ->first(static fn (array $candidate): bool => $candidate['route_key'] === $routeKey);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, string>
     */
    public function typeOptions(): array
    {
        return ['carrier' => 'Carrier', 'store' => 'Store', 'gateway' => 'Gateway'];
    }

    /**
     * @return array<string, string>
     */
    public function stateOptions(): array
    {
        return ['enabled' => 'Enabled', 'configured' => 'Configured', 'attention' => 'Needs attention', 'disabled' => 'Disabled'];
    }

    /**
     * @return array<string, string>
     */
    public function healthOptions(): array
    {
        return [
            IntegrationHealthLog::STATUS_HEALTHY => 'Healthy',
            IntegrationHealthLog::STATUS_DEGRADED => 'Degraded',
            IntegrationHealthLog::STATUS_DOWN => 'Down',
            'unknown' => 'Unknown',
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function visibleRows(?User $user): Collection
    {
        $rows = $this->allRows();

        if ($this->controlPlane->primaryCanonicalRole($user) === InternalControlPlane::ROLE_CARRIER_MANAGER) {
            return $rows->where('visibility_scope', 'carrier')->values();
        }

        return $rows;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function allRows(): Collection
    {
        $stores = $this->stores();
        $accounts = $this->accountsForStores($stores);
        $healthLogs = $this->healthLogs();
        $storeSyncLogs = $this->storeSyncLogs();
        $webhookEvents = $this->webhookEvents();
        $trackingWebhooks = $this->trackingWebhooks();
        $featureFlags = $this->featureFlags();
        $gateways = $this->gateways();

        return collect()
            ->concat($this->carrierRows($healthLogs, $trackingWebhooks, $featureFlags))
            ->concat($this->storeRows($stores, $accounts, $healthLogs, $storeSyncLogs, $webhookEvents, $featureFlags))
            ->concat($this->gatewayRows($gateways, $healthLogs, $featureFlags))
            ->sortBy([
                ['kind', 'asc'],
                ['provider_name', 'asc'],
                ['name', 'asc'],
            ])
            ->values();
    }

    /**
     * @param Collection<string, IntegrationHealthLog> $healthLogs
     * @param Collection<string, TrackingWebhook> $trackingWebhooks
     * @param Collection<string, FeatureFlag> $featureFlags
     * @return Collection<int, array<string, mixed>>
     */
    private function carrierRows(Collection $healthLogs, Collection $trackingWebhooks, Collection $featureFlags): Collection
    {
        return collect(self::CARRIERS)->map(function (array $definition, string $providerKey) use ($healthLogs, $trackingWebhooks, $featureFlags): array {
            $healthLog = $healthLogs->get($definition['health_service']);
            $webhook = $trackingWebhooks->get($providerKey);
            $credentials = $this->credentialSummary($this->safeArray(config($definition['config_path'], [])));
            $featureItems = $this->featureFlagItems([$definition['feature_flag']], $featureFlags);

            return $this->makeRow(
                routeKey: 'carrier~' . $providerKey,
                kind: 'carrier',
                name: $definition['label'],
                providerKey: $providerKey,
                providerName: $definition['label'],
                typeLabel: $definition['type_label'],
                visibilityScope: 'carrier',
                isEnabled: $this->enabledFromFlags($featureItems),
                isConfigured: $credentials['configured_count'] > 0,
                healthStatus: $healthLog?->status ?? 'unknown',
                healthSummary: $this->healthSummary($healthLog),
                activitySummary: [
                    'headline' => $webhook instanceof TrackingWebhook
                        ? 'Last webhook: ' . $this->headline((string) $webhook->event_type)
                        : 'No tracking webhook activity recorded yet',
                    'detail' => $webhook instanceof TrackingWebhook
                        ? implode(' • ', array_filter([
                            $this->headline((string) $webhook->status),
                            $this->displayDateTime($webhook->created_at),
                        ]))
                        : 'No safe webhook summary is currently available.',
                    'items' => collect([
                        ['label' => 'Last webhook event', 'value' => $webhook instanceof TrackingWebhook ? $this->headline((string) $webhook->event_type) : 'None recorded'],
                        ['label' => 'Webhook status', 'value' => $webhook instanceof TrackingWebhook ? $this->headline((string) $webhook->status) : 'Unknown'],
                        ['label' => 'Webhook time', 'value' => $webhook instanceof TrackingWebhook ? ($this->displayDateTime($webhook->created_at) ?? '—') : '—'],
                    ]),
                ],
                credentials: $credentials,
                featureFlags: $featureItems,
                accountSummary: null,
                metadata: [],
            );
        })->values();
    }

    /**
     * @param Collection<string, Store> $stores
     * @param Collection<string, Account> $accounts
     * @param Collection<string, IntegrationHealthLog> $healthLogs
     * @param Collection<string, StoreSyncLog> $storeSyncLogs
     * @param Collection<string, WebhookEvent> $webhookEvents
     * @param Collection<string, FeatureFlag> $featureFlags
     * @return Collection<int, array<string, mixed>>
     */
    private function storeRows(
        Collection $stores,
        Collection $accounts,
        Collection $healthLogs,
        Collection $storeSyncLogs,
        Collection $webhookEvents,
        Collection $featureFlags
    ): Collection {
        return $stores->map(function (Store $store) use ($accounts, $healthLogs, $storeSyncLogs, $webhookEvents, $featureFlags): array {
            $providerKey = Str::lower((string) $store->platform);
            $healthLog = $healthLogs->get('store:' . $providerKey);
            $syncLog = $storeSyncLogs->get((string) $store->id);
            $webhookEvent = $webhookEvents->get((string) $store->id);
            $connectionState = $this->storeConnectionState($store);
            $credentials = $this->credentialSummary($this->storeCredentialPayload($store));
            $featureItems = $this->featureFlagItems([self::STORE_FLAG_KEYS[$providerKey] ?? null], $featureFlags);
            $account = $accounts->get((string) $store->account_id);

            return $this->makeRow(
                routeKey: 'store~' . (string) $store->id,
                kind: 'store',
                name: (string) $store->name,
                providerKey: $providerKey,
                providerName: $this->headline($providerKey),
                typeLabel: 'Store Connector',
                visibilityScope: 'all',
                isEnabled: $this->storeIsEnabled($store),
                isConfigured: $credentials['configured_count'] > 0,
                healthStatus: $healthLog?->status ?? ($connectionState === 'error' ? IntegrationHealthLog::STATUS_DEGRADED : 'unknown'),
                healthSummary: $this->healthSummary($healthLog),
                activitySummary: [
                    'headline' => $syncLog instanceof StoreSyncLog
                        ? 'Last sync: ' . $this->headline((string) $syncLog->status)
                        : ($webhookEvent instanceof WebhookEvent ? 'Last webhook: ' . $this->headline((string) $webhookEvent->event_type) : 'No recent sync or webhook summary'),
                    'detail' => implode(' • ', array_filter([
                        $syncLog instanceof StoreSyncLog ? 'Sync ' . $this->headline((string) $syncLog->sync_type) : null,
                        $syncLog instanceof StoreSyncLog ? $this->displayDateTime($syncLog->completed_at ?: $syncLog->started_at) : null,
                        $webhookEvent instanceof WebhookEvent ? 'Webhook ' . $this->headline((string) $webhookEvent->status) : null,
                    ])) ?: 'No safe sync or webhook activity summary is available.',
                    'items' => collect([
                        ['label' => 'Connection state', 'value' => $this->headline($connectionState)],
                        ['label' => 'Last sync', 'value' => $syncLog instanceof StoreSyncLog ? ($this->displayDateTime($syncLog->completed_at ?: $syncLog->started_at) ?? '—') : ($this->displayDateTime($this->storeLastSyncAt($store)) ?? '—')],
                        ['label' => 'Last webhook', 'value' => $webhookEvent instanceof WebhookEvent ? $this->headline((string) $webhookEvent->event_type) . ' • ' . $this->headline((string) $webhookEvent->status) : 'None recorded'],
                    ]),
                ],
                credentials: $credentials,
                featureFlags: $featureItems,
                accountSummary: $account instanceof Account ? [
                    'account' => $account,
                    'name' => (string) $account->name,
                    'slug' => (string) ($account->slug ?? '—'),
                    'type_label' => $account->isOrganization() ? 'Organization' : 'Individual',
                ] : null,
                metadata: [
                    'status' => $this->headline((string) $store->status),
                    'connection_state' => $this->headline($connectionState),
                    'platform' => $this->headline((string) $store->platform),
                    'platform_store_id' => trim((string) $store->external_store_id) ?: '—',
                    'store_url' => $this->storeUrl($store),
                    'last_sync_at' => $this->displayDateTime($this->storeLastSyncAt($store)) ?? '—',
                ],
            );
        })->values();
    }

    /**
     * @param Collection<int, PaymentGateway> $gateways
     * @param Collection<string, IntegrationHealthLog> $healthLogs
     * @param Collection<string, FeatureFlag> $featureFlags
     * @return Collection<int, array<string, mixed>>
     */
    private function gatewayRows(Collection $gateways, Collection $healthLogs, Collection $featureFlags): Collection
    {
        return $gateways->map(function (PaymentGateway $gateway) use ($healthLogs, $featureFlags): array {
            $providerKey = Str::lower((string) $gateway->provider);
            $healthLog = $healthLogs->get('gateway:' . $providerKey);
            $credentials = $this->credentialSummary($this->safeArray($gateway->getAttribute('config')));
            $featureItems = $this->featureFlagItems([self::GATEWAY_FLAG_KEYS[$providerKey] ?? null], $featureFlags);

            return $this->makeRow(
                routeKey: 'gateway~' . (string) $gateway->id,
                kind: 'gateway',
                name: (string) $gateway->name,
                providerKey: $providerKey,
                providerName: (string) $gateway->provider,
                typeLabel: 'Payment gateway',
                visibilityScope: 'all',
                isEnabled: (bool) $gateway->is_active,
                isConfigured: $credentials['configured_count'] > 0,
                healthStatus: $healthLog?->status ?? 'unknown',
                healthSummary: $this->healthSummary($healthLog),
                activitySummary: [
                    'headline' => $healthLog instanceof IntegrationHealthLog
                        ? 'Health check: ' . $this->headline((string) $healthLog->status)
                        : 'No recent sync or webhook summary',
                    'detail' => implode(' • ', array_filter([
                        $gateway->is_sandbox ? 'Sandbox mode' : 'Live mode',
                        $this->displayDateTime($gateway->updated_at),
                    ])),
                    'items' => collect([
                        ['label' => 'Gateway mode', 'value' => $gateway->is_sandbox ? 'Sandbox' : 'Live'],
                        ['label' => 'Supported methods', 'value' => $this->listSummary($this->safeArray($gateway->supported_methods))],
                        ['label' => 'Updated at', 'value' => $this->displayDateTime($gateway->updated_at) ?? '—'],
                    ]),
                ],
                credentials: $credentials,
                featureFlags: $featureItems,
                accountSummary: null,
                metadata: [
                    'supported_currencies' => $this->listSummary($this->safeArray($gateway->supported_currencies)),
                    'fee_summary' => sprintf(
                        '%s%% + %s',
                        number_format((float) $gateway->transaction_fee_pct, 2),
                        number_format((float) $gateway->transaction_fee_fixed, 2)
                    ),
                ],
            );
        })->values();
    }

    /**
     * @param array<string, mixed> $healthSummary
     * @param array<string, mixed> $activitySummary
     * @param array<string, mixed> $credentials
     * @param Collection<int, array<string, mixed>> $featureFlags
     * @param array<string, mixed>|null $accountSummary
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function makeRow(
        string $routeKey,
        string $kind,
        string $name,
        string $providerKey,
        string $providerName,
        string $typeLabel,
        string $visibilityScope,
        bool $isEnabled,
        bool $isConfigured,
        string $healthStatus,
        array $healthSummary,
        array $activitySummary,
        array $credentials,
        Collection $featureFlags,
        ?array $accountSummary,
        array $metadata
    ): array {
        $needsAttention = $healthStatus === IntegrationHealthLog::STATUS_DEGRADED
            || $healthStatus === IntegrationHealthLog::STATUS_DOWN
            || (! $isEnabled && $isConfigured);

        return [
            'route_key' => $routeKey,
            'kind' => $kind,
            'name' => $name,
            'provider_key' => $providerKey,
            'provider_name' => $providerName,
            'type_label' => $typeLabel,
            'visibility_scope' => $visibilityScope,
            'is_enabled' => $isEnabled,
            'enabled_label' => $isEnabled ? 'Enabled' : 'Disabled',
            'is_configured' => $isConfigured,
            'configuration_label' => $isConfigured ? 'Configured' : 'Configuration incomplete',
            'health_status' => $healthStatus,
            'health_label' => $this->healthOptions()[$healthStatus] ?? 'Unknown',
            'needs_attention' => $needsAttention,
            'state_badge' => $needsAttention ? 'Needs attention' : ($isEnabled ? 'Operational' : 'Read only'),
            'health_summary' => $healthSummary,
            'activity_summary' => $activitySummary,
            'credentials' => $credentials,
            'feature_flags' => $featureFlags,
            'feature_flag_summary' => $this->featureFlagSummary($featureFlags),
            'masked_api_summary' => $credentials['summary'],
            'account_summary' => $accountSummary,
            'metadata' => $metadata,
        ];
    }

    /**
     * @return Collection<string, Store>
     */
    private function stores(): Collection
    {
        if (! Schema::hasTable('stores')) {
            return collect();
        }

        return Store::query()
            ->withoutGlobalScopes()
            ->orderBy('name')
            ->get()
            ->keyBy(static fn (Store $store): string => (string) $store->id);
    }

    /**
     * @param Collection<string, Store> $stores
     * @return Collection<string, Account>
     */
    private function accountsForStores(Collection $stores): Collection
    {
        $accountIds = $stores->pluck('account_id')
            ->filter()
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();

        if ($accountIds === []) {
            return collect();
        }

        return Account::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $accountIds)
            ->get()
            ->keyBy(static fn (Account $account): string => (string) $account->id);
    }

    /**
     * @return Collection<int, PaymentGateway>
     */
    private function gateways(): Collection
    {
        if (! Schema::hasTable('payment_gateways')) {
            return collect();
        }

        return PaymentGateway::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->values();
    }

    /**
     * @return Collection<string, IntegrationHealthLog>
     */
    private function healthLogs(): Collection
    {
        if (! Schema::hasTable('integration_health_logs')) {
            return collect();
        }

        return IntegrationHealthLog::query()
            ->orderByDesc('checked_at')
            ->get()
            ->unique('service')
            ->keyBy(static fn (IntegrationHealthLog $log): string => (string) $log->service);
    }

    /**
     * @return Collection<string, StoreSyncLog>
     */
    private function storeSyncLogs(): Collection
    {
        if (! Schema::hasTable('store_sync_logs')) {
            return collect();
        }

        return StoreSyncLog::query()
            ->orderByDesc('completed_at')
            ->orderByDesc('started_at')
            ->get()
            ->unique('store_id')
            ->keyBy(static fn (StoreSyncLog $log): string => (string) $log->store_id);
    }

    /**
     * @return Collection<string, WebhookEvent>
     */
    private function webhookEvents(): Collection
    {
        if (! Schema::hasTable('webhook_events')) {
            return collect();
        }

        return WebhookEvent::query()
            ->withoutGlobalScopes()
            ->orderByDesc('processed_at')
            ->orderByDesc('created_at')
            ->get()
            ->unique('store_id')
            ->keyBy(static fn (WebhookEvent $event): string => (string) $event->store_id);
    }

    /**
     * @return Collection<string, TrackingWebhook>
     */
    private function trackingWebhooks(): Collection
    {
        if (! Schema::hasTable('tracking_webhooks')) {
            return collect();
        }

        return TrackingWebhook::query()
            ->orderByDesc('created_at')
            ->get()
            ->unique('carrier_code')
            ->keyBy(static fn (TrackingWebhook $webhook): string => Str::lower((string) $webhook->carrier_code));
    }

    /**
     * @return Collection<string, FeatureFlag>
     */
    private function featureFlags(): Collection
    {
        if (! Schema::hasTable('feature_flags')) {
            return collect();
        }

        return FeatureFlag::query()
            ->get()
            ->keyBy(static fn (FeatureFlag $flag): string => (string) $flag->key);
    }

    /**
     * @param array<int, string|null> $keys
     * @param Collection<string, FeatureFlag> $featureFlags
     * @return Collection<int, array<string, mixed>>
     */
    private function featureFlagItems(array $keys, Collection $featureFlags): Collection
    {
        return collect($keys)
            ->filter(static fn ($key): bool => is_string($key) && trim($key) !== '')
            ->values()
            ->map(function (string $key) use ($featureFlags): array {
                $flag = $featureFlags->get($key);
                $enabled = $flag instanceof FeatureFlag
                    ? (bool) $flag->is_enabled
                    : (bool) config('features.' . $key, false);

                return [
                    'key' => $key,
                    'label' => Str::headline($key),
                    'is_enabled' => $enabled,
                    'state_label' => $enabled ? 'Enabled' : 'Disabled',
                ];
            });
    }

    /**
     * @param Collection<int, array<string, mixed>> $items
     */
    private function enabledFromFlags(Collection $items): bool
    {
        if ($items->isEmpty()) {
            return false;
        }

        return $items->contains(static fn (array $item): bool => (bool) ($item['is_enabled'] ?? false));
    }

    /**
     * @param Collection<int, array<string, mixed>> $items
     */
    private function featureFlagSummary(Collection $items): string
    {
        if ($items->isEmpty()) {
            return 'No feature flags are currently linked to this integration.';
        }

        return $items
            ->map(static fn (array $item): string => sprintf(
                '%s: %s',
                (string) $item['label'],
                (string) $item['state_label']
            ))
            ->implode(' • ');
    }

    /**
     * @param array<string, mixed> $config
     * @return array{configured_count: int, hidden_for_role: bool, summary: string, items: Collection<int, array{label: string, value: string}>}
     */
    private function credentialSummary(array $config): array
    {
        $items = collect($config)
            ->filter(fn (mixed $value, mixed $key): bool => is_string($key) && $this->looksSensitiveKey($key))
            ->map(function (mixed $value, string $key): array {
                return [
                    'label' => Str::headline($key),
                    'value' => $this->hasVisibleValue($value) ? '********' : 'Not configured',
                    'configured' => $this->hasVisibleValue($value),
                ];
            })
            ->values();

        $configuredCount = $items->where('configured', true)->count();

        return [
            'configured_count' => $configuredCount,
            'hidden_for_role' => false,
            'summary' => $configuredCount > 0
                ? number_format($configuredCount) . ' masked credential field(s) configured'
                : 'No configured credential fields detected',
            'items' => $items->map(static fn (array $item): array => [
                'label' => $item['label'],
                'value' => $item['value'],
            ]),
        ];
    }

    /**
     * @return array{label: string, checked_at: string, response_time: string, request_summary: string}
     */
    private function healthSummary(?IntegrationHealthLog $log): array
    {
        if (! $log instanceof IntegrationHealthLog) {
            return [
                'label' => 'Unknown',
                'checked_at' => '—',
                'response_time' => '—',
                'request_summary' => 'No recent health check summary',
            ];
        }

        return [
            'label' => $this->healthOptions()[$log->status] ?? 'Unknown',
            'checked_at' => $this->displayDateTime($log->checked_at) ?? '—',
            'response_time' => $log->response_time_ms !== null ? number_format((int) $log->response_time_ms) . ' ms' : '—',
            'request_summary' => sprintf(
                '%s total • %s failed',
                number_format((int) $log->total_requests),
                number_format((int) $log->failed_requests)
            ),
        ];
    }

    private function storeConnectionState(Store $store): string
    {
        $connectionState = Str::lower(trim((string) $store->getAttribute('connection_status')));

        if ($connectionState !== '') {
            return $connectionState;
        }

        return Str::lower(trim((string) $store->getAttribute('status')));
    }

    private function storeIsEnabled(Store $store): bool
    {
        $status = Str::lower(trim((string) $store->getAttribute('status')));
        $hasExplicitConnectionState = trim((string) $store->getAttribute('connection_status')) !== '';

        if ($hasExplicitConnectionState) {
            return $status === 'active' && $this->storeConnectionState($store) === 'connected';
        }

        return $this->storeConnectionState($store) === 'connected';
    }

    private function storeLastSyncAt(Store $store): mixed
    {
        return $store->getAttribute('last_synced_at') ?: $store->getAttribute('last_sync_at');
    }

    private function storeUrl(Store $store): string
    {
        $raw = trim((string) ($store->getAttribute('external_store_url') ?: $store->getAttribute('store_url')));

        if ($raw === '') {
            return '';
        }

        $candidate = Str::startsWith($raw, ['http://', 'https://']) ? $raw : 'https://' . $raw;
        $parts = parse_url($candidate);
        $host = is_array($parts) ? trim((string) ($parts['host'] ?? '')) : '';

        return $host !== '' ? $host : $raw;
    }

    /**
     * @return array<string, mixed>
     */
    private function storeCredentialPayload(Store $store): array
    {
        $payload = $this->decodeJsonArray($store->getAttribute('connection_config'));

        foreach (['api_key', 'api_secret'] as $key) {
            $value = $store->getAttribute($key);

            if ($this->hasVisibleValue($value) && ! array_key_exists($key, $payload)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function safeArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function looksSensitiveKey(string $key): bool
    {
        $normalized = Str::lower($key);

        foreach (['account', 'auth', 'client', 'key', 'password', 'pin', 'secret', 'sid', 'token', 'username'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function hasVisibleValue(mixed $value): bool
    {
        if (is_array($value)) {
            return collect($value)->contains(fn (mixed $nested): bool => $this->hasVisibleValue($nested));
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return true;
        }

        return is_string($value) && trim($value) !== '';
    }

    /**
     * @param mixed $value
     */
    private function listSummary(mixed $value): string
    {
        $items = collect($value)
            ->filter(static fn ($item): bool => is_string($item) && trim($item) !== '')
            ->map(static fn (string $item): string => Str::headline($item))
            ->values();

        return $items->isNotEmpty() ? $items->implode(' • ') : 'No linked values';
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

        return $value === '' ? 'Unknown' : Str::headline($value);
    }
}
