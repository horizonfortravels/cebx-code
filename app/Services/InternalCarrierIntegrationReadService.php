<?php

namespace App\Services;

use App\Models\CarrierError;
use App\Models\TrackingWebhook;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InternalCarrierIntegrationReadService
{
    public function __construct(
        private readonly InternalIntegrationReadService $integrationReadService,
    ) {}

    /**
     * @param array{q: string, state: string, health: string} $filters
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
     * @param array{q: string, state: string, health: string} $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function filteredRows(?User $user, array $filters): Collection
    {
        return $this->carrierRows($user)
            ->filter(function (array $row) use ($filters): bool {
                if ($filters['q'] !== '') {
                    $haystack = Str::lower(implode(' ', array_filter([
                        (string) $row['name'],
                        (string) $row['provider_name'],
                        (string) $row['provider_key'],
                        (string) ($row['shipper_account_summary']['summary'] ?? ''),
                        (string) ($row['last_error_summary']['headline'] ?? ''),
                    ])));

                    if (! str_contains($haystack, Str::lower($filters['q']))) {
                        return false;
                    }
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
     * @return array{total: int, enabled: int, attention: int, configured: int}
     */
    public function stats(?User $user): array
    {
        $rows = $this->carrierRows($user);

        return [
            'total' => $rows->count(),
            'enabled' => $rows->where('is_enabled', true)->count(),
            'attention' => $rows->where('needs_attention', true)->count(),
            'configured' => $rows->where('is_configured', true)->count(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findVisibleDetail(?User $user, string $providerKey): ?array
    {
        $detail = $this->integrationReadService->findVisibleDetail($user, 'carrier~' . Str::lower($providerKey));

        if (! is_array($detail) || ($detail['kind'] ?? null) !== 'carrier') {
            return null;
        }

        return $this->enrichCarrierRow($detail);
    }

    /**
     * @return array<string, string>
     */
    public function stateOptions(): array
    {
        return $this->integrationReadService->stateOptions();
    }

    /**
     * @return array<string, string>
     */
    public function healthOptions(): array
    {
        return $this->integrationReadService->healthOptions();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function carrierRows(?User $user): Collection
    {
        return $this->integrationReadService
            ->filteredRows($user, [
                'q' => '',
                'type' => 'carrier',
                'state' => '',
                'health' => '',
            ])
            ->map(fn (array $row): array => $this->enrichCarrierRow($row))
            ->values();
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function enrichCarrierRow(array $row): array
    {
        $providerKey = Str::lower((string) ($row['provider_key'] ?? ''));
        $config = $this->safeArray(config('services.' . $providerKey, []));
        $shipperAccountSummary = $this->shipperAccountSummary($config);
        $lastErrorSummary = $this->lastErrorSummary($providerKey, $row);

        $row['mode_label'] = $this->modeLabel($config);
        $row['mode_summary'] = $this->modeSummary($config, $row['mode_label']);
        $row['shipper_account_summary'] = $shipperAccountSummary;
        $row['connection_test_summary'] = $this->connectionTestSummary($row);
        $row['last_error_summary'] = $lastErrorSummary;
        $row['metadata'] = array_merge($row['metadata'] ?? [], [
            'connection_mode' => $row['mode_label'],
            'shipper_account' => $shipperAccountSummary['summary'],
            'last_connection_test' => $row['connection_test_summary']['summary'],
            'last_error' => $lastErrorSummary['summary'],
        ]);

        return $row;
    }

    /**
     * @param array<string, mixed> $config
     * @return array{summary: string, masked_value: string}
     */
    private function shipperAccountSummary(array $config): array
    {
        foreach (['account_number', 'shipper_account', 'bill_to_account'] as $key) {
            $value = trim((string) ($config[$key] ?? ''));

            if ($value === '') {
                continue;
            }

            $visible = preg_replace('/[^A-Za-z0-9]/', '', $value) ?: $value;
            $lastFour = substr($visible, -4);

            return [
                'summary' => $lastFour !== false && $lastFour !== ''
                    ? 'Configured ending ' . $lastFour
                    : 'Configured',
                'masked_value' => $lastFour !== false && $lastFour !== ''
                    ? '****' . $lastFour
                    : '********',
            ];
        }

        return [
            'summary' => 'No shipper account configured',
            'masked_value' => 'Not configured',
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function modeLabel(array $config): string
    {
        foreach (['is_sandbox', 'sandbox'] as $key) {
            if (array_key_exists($key, $config)) {
                return (bool) $config[$key] ? 'Sandbox' : 'Live';
            }
        }

        foreach (['base_url', 'oauth_url'] as $key) {
            $value = Str::lower(trim((string) ($config[$key] ?? '')));

            if ($value === '') {
                continue;
            }

            if (str_contains($value, 'sandbox') || str_contains($value, 'test') || str_contains($value, 'staging')) {
                return 'Sandbox';
            }

            return 'Live';
        }

        return 'Not modeled';
    }

    /**
     * @param array<string, mixed> $config
     */
    private function modeSummary(array $config, string $modeLabel): string
    {
        if ($modeLabel === 'Not modeled') {
            return 'This carrier does not expose an explicit sandbox or live mode in the current config contract.';
        }

        $endpoint = trim((string) ($config['base_url'] ?? $config['oauth_url'] ?? ''));

        if ($endpoint === '') {
            return $modeLabel . ' mode is modeled without a visible endpoint summary.';
        }

        $host = parse_url($endpoint, PHP_URL_HOST);

        return $modeLabel . ' mode' . ($host ? ' via ' . $host : '');
    }

    /**
     * @param array<string, mixed> $row
     * @return array{headline: string, detail: string, summary: string}
     */
    private function connectionTestSummary(array $row): array
    {
        $healthLabel = (string) ($row['health_label'] ?? 'Unknown');
        $checkedAt = (string) data_get($row, 'health_summary.checked_at', '—');
        $requests = (string) data_get($row, 'health_summary.request_summary', 'No recent health check summary');

        return [
            'headline' => 'Last health check: ' . $healthLabel,
            'detail' => implode(' • ', array_filter([$checkedAt, $requests])),
            'summary' => $healthLabel . ' • ' . $checkedAt,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array{headline: string, detail: string, summary: string}
     */
    private function lastErrorSummary(string $providerKey, array $row): array
    {
        $carrierError = $this->latestCarrierError($providerKey);

        if ($carrierError instanceof CarrierError) {
            $headline = trim((string) ($carrierError->internal_message ?: 'Carrier error recorded'));
            $detail = implode(' • ', array_filter([
                $this->headline((string) $carrierError->operation),
                $this->headline((string) $carrierError->internal_code),
                $carrierError->is_retriable ? 'Retriable' : 'Not retriable',
                $this->displayDateTime($carrierError->updated_at ?: $carrierError->created_at),
            ]));

            return [
                'headline' => $headline,
                'detail' => $detail !== '' ? $detail : 'Safe carrier error summary recorded.',
                'summary' => $headline,
            ];
        }

        $trackingFailure = $this->latestTrackingFailure($providerKey);

        if ($trackingFailure instanceof TrackingWebhook) {
            $headline = 'Latest tracking webhook delivery needs review';
            $detail = implode(' • ', array_filter([
                $this->headline((string) $trackingFailure->status),
                $this->displayDateTime($trackingFailure->created_at),
            ]));

            return [
                'headline' => $headline,
                'detail' => $detail !== '' ? $detail : 'Safe tracking webhook failure summary recorded.',
                'summary' => $headline,
            ];
        }

        $healthStatus = (string) ($row['health_status'] ?? 'unknown');

        if (in_array($healthStatus, ['degraded', 'down'], true)) {
            $headline = $healthStatus === 'down'
                ? 'Carrier service is currently down'
                : 'Carrier service is currently degraded';

            return [
                'headline' => $headline,
                'detail' => (string) data_get($row, 'health_summary.request_summary', 'Recent requests show elevated failures.'),
                'summary' => $headline,
            ];
        }

        return [
            'headline' => 'No recent safe carrier error summary',
            'detail' => 'No unresolved carrier or tracking failure summary is currently visible.',
            'summary' => 'No recent safe carrier error summary',
        ];
    }

    private function latestCarrierError(string $providerKey): ?CarrierError
    {
        if (! Schema::hasTable('carrier_errors')) {
            return null;
        }

        return CarrierError::query()
            ->where('carrier_code', $providerKey)
            ->orderBy('was_resolved')
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->first();
    }

    private function latestTrackingFailure(string $providerKey): ?TrackingWebhook
    {
        if (! Schema::hasTable('tracking_webhooks')) {
            return null;
        }

        return TrackingWebhook::query()
            ->where('carrier_code', $providerKey)
            ->whereIn('status', [TrackingWebhook::STATUS_REJECTED, TrackingWebhook::STATUS_FAILED])
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function safeArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function headline(string $value): string
    {
        $value = trim($value);

        return $value === '' ? 'Unknown' : Str::headline($value);
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
}
