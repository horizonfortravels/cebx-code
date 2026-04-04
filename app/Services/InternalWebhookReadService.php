<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Store;
use App\Models\TrackingWebhook;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Platforms\PlatformAdapterFactory;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InternalWebhookReadService
{
    /**
     * @var array<string, string>
     */
    private const TRACKING_PROVIDER_LABELS = [
        'aramex' => 'Aramex',
        'dhl' => 'DHL Express',
        'fedex' => 'FedEx',
        'smsa' => 'SMSA',
    ];

    /**
     * @param array{q: string, type: string, state: string} $filters
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
     * @param array{q: string, type: string, state: string} $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function filteredRows(?User $user, array $filters): Collection
    {
        return $this->visibleRows($user)
            ->filter(function (array $row) use ($filters): bool {
                if ($filters['q'] !== '') {
                    $haystack = Str::lower(implode(' ', array_filter([
                        (string) ($row['name'] ?? ''),
                        (string) ($row['provider_name'] ?? ''),
                        (string) ($row['endpoint_label'] ?? ''),
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

                if ($filters['state'] !== '' && $row['state_key'] !== $filters['state']) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    /**
     * @return array{total: int, attention: int, retryable: int, tracking: int}
     */
    public function stats(?User $user): array
    {
        $rows = $this->visibleRows($user);

        return [
            'total' => $rows->count(),
            'attention' => $rows->where('has_failures', true)->count(),
            'retryable' => $rows->where('has_retryable_events', true)->count(),
            'tracking' => $rows->where('kind', 'tracking')->count(),
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
        return [
            'store' => 'نقاط نهاية المتاجر',
            'tracking' => 'نقاط نهاية التتبع',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function stateOptions(): array
    {
        return [
            'operational' => 'تشغيلي',
            'attention' => 'يحتاج إلى انتباه',
            'retryable' => 'إخفاقات قابلة لإعادة المحاولة',
        ];
    }

    /**
     * @return array{kind: string, id: string}|null
     */
    public function parseRouteKey(string $routeKey): ?array
    {
        $parts = explode('~', trim($routeKey), 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$kind, $id] = $parts;

        if (! in_array($kind, ['store', 'tracking'], true) || trim($id) === '') {
            return null;
        }

        return [
            'kind' => $kind,
            'id' => $id,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function visibleRows(?User $user): Collection
    {
        return $this->allRows();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function allRows(): Collection
    {
        $stores = $this->stores();
        $accounts = $this->accountsForStores($stores);
        $storeEvents = $this->storeEvents();
        $trackingEvents = $this->trackingEvents();

        return collect()
            ->concat($this->storeRows($stores, $accounts, $storeEvents))
            ->concat($this->trackingRows($trackingEvents))
            ->sortBy([
                ['kind', 'asc'],
                ['provider_name', 'asc'],
                ['name', 'asc'],
            ])
            ->values();
    }

    /**
     * @param Collection<string, Store> $stores
     * @param Collection<string, Account> $accounts
     * @param Collection<string, Collection<int, WebhookEvent>> $storeEvents
     * @return Collection<int, array<string, mixed>>
     */
    private function storeRows(Collection $stores, Collection $accounts, Collection $storeEvents): Collection
    {
        return $stores->map(function (Store $store) use ($accounts, $storeEvents): array {
            $events = $storeEvents->get((string) $store->id, collect());
            $recentAttempts = $events->take(10)->map(fn (WebhookEvent $event): array => $this->storeAttemptSummary($store, $event))->values();
            $recentFailures = $recentAttempts->where('is_failure', true)->values();
            $lastEvent = $events->first();
            $lastSuccess = $events->first(fn (WebhookEvent $event): bool => (string) $event->status === WebhookEvent::STATUS_PROCESSED);
            $lastFailure = $events->first(fn (WebhookEvent $event): bool => (string) $event->status === WebhookEvent::STATUS_FAILED);
            $hasRetryableEvents = $recentAttempts->contains(fn (array $attempt): bool => (bool) ($attempt['is_retryable'] ?? false));
            $hasFailures = $recentFailures->isNotEmpty();
            $isEnabled = $this->storeIsEnabled($store);
            $stateKey = $hasRetryableEvents ? 'retryable' : ($hasFailures ? 'attention' : 'operational');
            $account = $accounts->get((string) $store->account_id);

            return [
                'route_key' => 'store~' . (string) $store->id,
                'kind' => 'store',
                'name' => (string) $store->name,
                'provider_name' => $this->providerLabel((string) $store->platform),
                'provider_key' => Str::lower((string) $store->platform),
                'endpoint_label' => 'نقطة نهاية ويب هوك المتجر',
                'state_key' => $stateKey,
                'state_label' => $this->stateLabel($stateKey),
                'is_enabled' => $isEnabled,
                'enabled_label' => $isEnabled ? 'يستقبل' : 'يحتاج إلى انتباه',
                'has_failures' => $hasFailures,
                'has_retryable_events' => $hasRetryableEvents,
                'attempts_count' => $events->count(),
                'failures_count' => $recentFailures->count(),
                'retryable_count' => $recentAttempts->where('is_retryable', true)->count(),
                'last_attempt_at' => $this->displayDateTime($lastEvent?->created_at) ?? '—',
                'last_success_at' => $this->displayDateTime($lastSuccess?->processed_at ?? $lastSuccess?->created_at) ?? '—',
                'last_failure_at' => $this->displayDateTime($lastFailure?->created_at) ?? '—',
                'recent_summary' => $lastEvent instanceof WebhookEvent
                    ? sprintf(
                        '%s • %s',
                        $this->headline((string) $lastEvent->event_type),
                        $this->headline((string) $lastEvent->status)
                    )
                    : 'لا توجد عمليات تسليم ويب هوك مسجلة حتى الآن',
                'failure_summary' => $lastFailure instanceof WebhookEvent
                    ? $this->safeFailureSummary((string) $lastFailure->error_message, 'فشلت عملية تسليم حديثة للمتجر.')
                    : 'لا توجد إخفاقات حديثة',
                'security_summary' => $this->storeSecuritySummary($store),
                'account_summary' => $account instanceof Account ? [
                    'account' => $account,
                    'name' => (string) $account->name,
                    'slug' => (string) ($account->slug ?? '—'),
                    'type_label' => $account->isOrganization() ? 'منظمة' : 'فردي',
                ] : null,
                'recent_attempts' => $recentAttempts,
                'recent_failures' => $recentFailures,
            ];
        })->values();
    }

    /**
     * @param Collection<string, Collection<int, TrackingWebhook>> $trackingEvents
     * @return Collection<int, array<string, mixed>>
     */
    private function trackingRows(Collection $trackingEvents): Collection
    {
        return $trackingEvents->map(function (Collection $events, string $carrierCode): array {
            $recentAttempts = $events->take(10)->map(fn (TrackingWebhook $event): array => $this->trackingAttemptSummary($event))->values();
            $recentFailures = $recentAttempts->where('is_failure', true)->values();
            $lastEvent = $events->first();
            $lastSuccess = $events->first(fn (TrackingWebhook $event): bool => in_array((string) $event->status, [TrackingWebhook::STATUS_VALIDATED, TrackingWebhook::STATUS_PROCESSED], true));
            $lastFailure = $events->first(fn (TrackingWebhook $event): bool => in_array((string) $event->status, [TrackingWebhook::STATUS_REJECTED, TrackingWebhook::STATUS_FAILED], true));
            $hasFailures = $recentFailures->isNotEmpty();
            $stateKey = $hasFailures ? 'attention' : 'operational';
            $providerName = self::TRACKING_PROVIDER_LABELS[$carrierCode] ?? Str::headline($carrierCode);

            return [
                'route_key' => 'tracking~' . $carrierCode,
                'kind' => 'tracking',
                'name' => $providerName . ' ويب هوك وارد',
                'provider_name' => $providerName,
                'provider_key' => $carrierCode,
                'endpoint_label' => 'نقطة نهاية ويب هوك التتبع',
                'state_key' => $stateKey,
                'state_label' => $this->stateLabel($stateKey),
                'is_enabled' => true,
                'enabled_label' => 'يستقبل',
                'has_failures' => $hasFailures,
                'has_retryable_events' => false,
                'attempts_count' => $events->count(),
                'failures_count' => $recentFailures->count(),
                'retryable_count' => 0,
                'last_attempt_at' => $this->displayDateTime($lastEvent?->created_at) ?? '—',
                'last_success_at' => $this->displayDateTime($lastSuccess?->created_at) ?? '—',
                'last_failure_at' => $this->displayDateTime($lastFailure?->created_at) ?? '—',
                'recent_summary' => $lastEvent instanceof TrackingWebhook
                    ? sprintf(
                        '%s • %s',
                        $this->headline((string) $lastEvent->event_type),
                        $this->headline((string) $lastEvent->status)
                    )
                    : 'لا توجد عمليات تسليم ويب هوك مسجلة حتى الآن',
                'failure_summary' => $lastFailure instanceof TrackingWebhook
                    ? $this->safeFailureSummary((string) $lastFailure->rejection_reason, 'فشلت عملية تسليم حديثة للتتبع.')
                    : 'لا توجد إخفاقات حديثة',
                'security_summary' => $this->trackingSecuritySummary($lastEvent),
                'account_summary' => null,
                'recent_attempts' => $recentAttempts,
                'recent_failures' => $recentFailures,
            ];
        })->values();
    }

    /**
     * @return Collection<string, Store>
     */
    private function stores(): Collection
    {
        if (! Schema::hasTable('stores')) {
            return collect();
        }

        $supportedPlatforms = PlatformAdapterFactory::supportedPlatforms();

        return Store::query()
            ->withoutGlobalScopes()
            ->whereIn('platform', $supportedPlatforms)
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
            ->map(static fn ($value): string => (string) $value)
            ->unique()
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
     * @return Collection<string, Collection<int, WebhookEvent>>
     */
    private function storeEvents(): Collection
    {
        if (! Schema::hasTable('webhook_events')) {
            return collect();
        }

        return WebhookEvent::query()
            ->withoutGlobalScopes()
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(static fn (WebhookEvent $event): string => (string) $event->store_id);
    }

    /**
     * @return Collection<string, Collection<int, TrackingWebhook>>
     */
    private function trackingEvents(): Collection
    {
        if (! Schema::hasTable('tracking_webhooks')) {
            return collect();
        }

        return TrackingWebhook::query()
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(static fn (TrackingWebhook $event): string => Str::lower((string) $event->carrier_code));
    }

    /**
     * @return array<string, mixed>
     */
    private function storeAttemptSummary(Store $store, WebhookEvent $event): array
    {
        $isRetryable = (string) $event->status === WebhookEvent::STATUS_FAILED
            && PlatformAdapterFactory::supports((string) $store->platform);

        return [
            'event_id' => (string) $event->id,
            'headline' => $this->headline((string) $event->event_type),
            'status_label' => $this->headline((string) $event->status),
            'resource_summary' => trim((string) $event->external_resource_id) !== ''
                ? 'تم تسجيل مرجع المورد الخارجي'
                : 'لا يوجد مرجع مورد خارجي',
            'received_at' => $this->displayDateTime($event->created_at) ?? '—',
            'processed_at' => $this->displayDateTime($event->processed_at) ?? '—',
            'attempt_summary' => sprintf(
                '%s محاولة إعادة',
                number_format((int) $event->retry_count)
            ),
            'failure_summary' => (string) $event->status === WebhookEvent::STATUS_FAILED
                ? $this->safeFailureSummary((string) $event->error_message, 'فشلت عملية التسليم المخزنة.')
                : 'لا يوجد ملخص فشل نشط',
            'is_failure' => (string) $event->status === WebhookEvent::STATUS_FAILED,
            'is_retryable' => $isRetryable,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function trackingAttemptSummary(TrackingWebhook $event): array
    {
        $isFailure = in_array((string) $event->status, [TrackingWebhook::STATUS_REJECTED, TrackingWebhook::STATUS_FAILED], true);

        return [
            'event_id' => (string) $event->id,
            'headline' => $this->headline((string) $event->event_type),
            'status_label' => $this->headline((string) $event->status),
            'resource_summary' => trim((string) $event->message_reference) !== ''
                ? 'تم تسجيل مرجع ويب هوك'
                : 'لم يتم تسجيل مرجع ويب هوك',
            'received_at' => $this->displayDateTime($event->created_at) ?? '—',
            'processed_at' => $this->displayDateTime($event->updated_at) ?? '—',
            'attempt_summary' => sprintf(
                '%s حدثًا مستخرجًا',
                number_format((int) $event->events_extracted)
            ),
            'failure_summary' => $isFailure
                ? $this->safeFailureSummary((string) $event->rejection_reason, 'فشلت عملية التسليم المخزنة في التحقق.')
                : 'لا يوجد ملخص فشل نشط',
            'is_failure' => $isFailure,
            'is_retryable' => false,
        ];
    }

    private function providerLabel(string $providerKey): string
    {
        return match (Str::lower(trim($providerKey))) {
            'shopify' => 'شوبيفاي',
            'woocommerce' => 'ووكومرس',
            default => 'مزوّد غير معروف',
        };
    }

    private function stateLabel(string $stateKey): string
    {
        return match ($stateKey) {
            'retryable' => 'إخفاقات قابلة لإعادة المحاولة',
            'attention' => 'يحتاج إلى انتباه',
            default => 'تشغيلي',
        };
    }

    private function storeIsEnabled(Store $store): bool
    {
        $status = Str::lower(trim((string) $store->getAttribute('status')));
        $connectionStatus = Str::lower(trim((string) $store->getAttribute('connection_status')));

        if ($connectionStatus !== '') {
            return $status === 'active' && $connectionStatus === 'connected';
        }

        return $status === 'connected' || $status === 'active';
    }

    private function storeSecuritySummary(Store $store): string
    {
        $config = $this->decodeJsonArray($store->getAttribute('connection_config'));
        $hasWebhookSecret = $this->hasVisibleValue($config['webhook_secret'] ?? null)
            || $this->hasVisibleValue($store->getAttribute('api_secret'));

        return $hasWebhookSecret
            ? 'تم إعداد سر التوقيع الوارد ويظل مخفيًا.'
            : 'لا يتوفر حاليًا ملخص لسر التوقيع المقنّع.';
    }

    private function trackingSecuritySummary(?TrackingWebhook $event): string
    {
        if (! $event instanceof TrackingWebhook) {
            return 'يُسجَّل التحقق من التوقيع الوارد عند وصول التسليمات.';
        }

        return match (true) {
            (bool) $event->signature_valid => 'اجتازت التسليمات الحديثة التحقق من التوقيع وتبقى التواقيع الخام مخفية.',
            $event->signature_valid === false => 'تتضمن التسليمات الحديثة إخفاقات في التحقق من التوقيع وتبقى الرؤوس والتواقيع الخام مخفية.',
            default => 'يُسجَّل التحقق من التوقيع الوارد عند وصول التسليمات.',
        };
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

    private function safeFailureSummary(string $value, string $fallback): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        if ($value === '') {
            return $fallback;
        }

        $normalized = Str::lower($value);

        return match (true) {
            str_contains($normalized, 'signature') && (str_contains($normalized, 'invalid') || str_contains($normalized, 'failed'))
                => 'فشل التحقق من التوقيع لهذه العملية.',
            str_contains($normalized, 'timed out') || str_contains($normalized, 'timeout')
                => 'انتهت مهلة محاولة معالجة سابقة لهذه العملية.',
            str_contains($normalized, 'duplicate')
                => 'طابقت العملية المخزنة حاجز حماية من التكرار.',
            str_contains($normalized, 'validation') || str_contains($normalized, 'invalid')
                => 'فشلت العملية المخزنة في اجتياز التحقق.',
            str_contains($normalized, 'auth') || str_contains($normalized, 'unauthorized')
                => 'فشل التحقق من الهوية لهذه العملية.',
            str_contains($normalized, 'rate limit') || str_contains($normalized, '429')
                => 'تعرّضت العملية لحدّ معدل أثناء المعالجة.',
            default => $fallback,
        };
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
        $value = Str::lower(trim($value));

        return match ($value) {
            '', 'unknown' => 'غير معروف',
            'processed' => 'تمت المعالجة',
            'validated' => 'تم التحقق',
            'failed' => 'فشل',
            'rejected' => 'مرفوض',
            'received' => 'تم الاستلام',
            'delivered' => 'تم التسليم',
            'enabled' => 'مفعّل',
            'disabled' => 'معطّل',
            'operational' => 'تشغيلي',
            'attention' => 'يحتاج إلى انتباه',
            'retryable' => 'قابل لإعادة المحاولة',
            'created' => 'تم الإنشاء',
            'updated' => 'تم التحديث',
            'deleted' => 'تم الحذف',
            'pending' => 'قيد الانتظار',
            'success' => 'نجاح',
            'warning' => 'تحذير',
            'store' => 'المتجر',
            'tracking' => 'التتبع',
            'shipment_preflight' => 'حجز مسبق للشحنة',
            default => 'غير معروف',
        };
    }
}
