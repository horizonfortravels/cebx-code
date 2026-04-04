<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\User;
use App\Support\Internal\InternalControlPlane;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InternalApiKeyReadService
{
    /**
     * @var array<string, string>
     */
    private const SAFE_SCOPE_OPTIONS = [
        'shipments:read' => 'Shipments read',
        'shipments:write' => 'Shipments write',
    ];

    public function __construct(
        private readonly InternalControlPlane $controlPlane,
    ) {}

    /**
     * @param array{q: string, state: string, scope: string, account: string} $filters
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
     * @param array{q: string, state: string, scope: string, account: string} $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function filteredRows(?User $user, array $filters): Collection
    {
        return $this->visibleRows($user)
            ->filter(function (array $row) use ($filters): bool {
                if ($filters['q'] !== '') {
                    $haystack = Str::lower(implode(' ', array_filter([
                        (string) $row['name'],
                        (string) $row['masked_prefix'],
                        (string) ($row['account_summary']['name'] ?? ''),
                        (string) ($row['account_summary']['slug'] ?? ''),
                        implode(' ', $row['scope_keys']),
                    ])));

                    if (! str_contains($haystack, Str::lower($filters['q']))) {
                        return false;
                    }
                }

                if ($filters['state'] !== '' && $row['state_key'] !== $filters['state']) {
                    return false;
                }

                if ($filters['scope'] !== '' && ! in_array($filters['scope'], $row['scope_keys'], true)) {
                    return false;
                }

                if ($filters['account'] !== '' && (string) ($row['account_summary']['id'] ?? '') !== $filters['account']) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    /**
     * @return array{total: int, active: int, revoked: int, expiring: int}
     */
    public function stats(?User $user): array
    {
        $rows = $this->visibleRows($user);

        return [
            'total' => $rows->count(),
            'active' => $rows->where('state_key', 'active')->count(),
            'revoked' => $rows->where('state_key', 'revoked')->count(),
            'expiring' => $rows->where('state_key', 'expiring')->count(),
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
    public function stateOptions(): array
    {
        return [
            'active' => 'Active',
            'expiring' => 'Expiring soon',
            'expired' => 'Expired',
            'revoked' => 'Revoked',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function scopeOptions(): array
    {
        return self::SAFE_SCOPE_OPTIONS;
    }

    /**
     * @return Collection<int, Account>
     */
    public function accountOptions(): Collection
    {
        if (! Schema::hasTable('accounts')) {
            return collect();
        }

        return Account::query()
            ->withoutGlobalScopes()
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function visibleRows(?User $user): Collection
    {
        if ($this->controlPlane->primaryCanonicalRole($user) === InternalControlPlane::ROLE_CARRIER_MANAGER) {
            return collect();
        }

        return $this->allRows();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function allRows(): Collection
    {
        if (! Schema::hasTable('api_keys')) {
            return collect();
        }

        return ApiKey::query()
            ->withoutGlobalScopes()
            ->with(['account', 'creator'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ApiKey $apiKey): array => $this->mapRow($apiKey))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(ApiKey $apiKey): array
    {
        $status = $this->statusFor($apiKey);
        $scopeKeys = collect($apiKey->scopes ?? [])
            ->filter(static fn ($scope): bool => is_string($scope) && trim($scope) !== '')
            ->values()
            ->all();
        $scopeItems = collect($scopeKeys)->map(function (string $scope): array {
            return [
                'key' => $scope,
                'label' => self::SAFE_SCOPE_OPTIONS[$scope] ?? Str::headline(str_replace(':', ' ', $scope)),
            ];
        })->values();
        $allowedIpCount = collect($apiKey->allowed_ips ?? [])->filter(static fn ($ip): bool => is_string($ip) && trim($ip) !== '')->count();
        $account = $apiKey->account;
        $creator = $apiKey->creator;

        return [
            'route_key' => (string) $apiKey->id,
            'id' => (string) $apiKey->id,
            'name' => (string) $apiKey->name,
            'masked_prefix' => (string) $apiKey->key_prefix . '...',
            'state_key' => $status['key'],
            'state_label' => $status['label'],
            'status_detail' => $status['detail'],
            'is_active' => ! $apiKey->isRevoked() && ! $this->isExpired($apiKey),
            'is_rotatable' => ! $apiKey->isRevoked(),
            'is_revokable' => ! $apiKey->isRevoked(),
            'scope_keys' => $scopeKeys,
            'scope_items' => $scopeItems,
            'scope_summary' => $scopeItems->isNotEmpty()
                ? $scopeItems->pluck('label')->implode(' | ')
                : 'All scopes (legacy unrestricted key)',
            'allowed_ip_summary' => $allowedIpCount > 0
                ? number_format($allowedIpCount) . ' allowlisted IP(s)'
                : 'No IP allowlist',
            'allowed_ip_count' => $allowedIpCount,
            'created_at' => $this->displayDateTime($apiKey->created_at) ?? '-',
            'last_used_at' => $this->displayDateTime($apiKey->last_used_at) ?? 'Never used',
            'expires_at' => $this->displayDateTime($apiKey->expires_at) ?? 'Does not expire',
            'revoked_at' => $this->displayDateTime($apiKey->revoked_at) ?? 'Not revoked',
            'account_summary' => $account instanceof Account ? [
                'account' => $account,
                'id' => (string) $account->id,
                'name' => (string) $account->name,
                'slug' => (string) ($account->slug ?? '-'),
                'type_label' => $account->isOrganization() ? 'Organization' : 'Individual',
            ] : null,
            'creator_summary' => [
                'name' => $creator?->name ?: 'System',
                'email' => $creator?->email ?: 'system',
            ],
        ];
    }

    /**
     * @return array{key: string, label: string, detail: string}
     */
    private function statusFor(ApiKey $apiKey): array
    {
        if ($apiKey->isRevoked()) {
            return [
                'key' => 'revoked',
                'label' => 'Revoked',
                'detail' => 'This key can no longer authenticate requests.',
            ];
        }

        if ($this->isExpired($apiKey)) {
            return [
                'key' => 'expired',
                'label' => 'Expired',
                'detail' => 'This key has passed its expiry timestamp and should be rotated or replaced.',
            ];
        }

        if ($apiKey->expires_at instanceof Carbon && $apiKey->expires_at->lte(now()->addDays(14))) {
            return [
                'key' => 'expiring',
                'label' => 'Expiring soon',
                'detail' => 'This key expires within the next 14 days.',
            ];
        }

        return [
            'key' => 'active',
            'label' => 'Active',
            'detail' => 'This key is currently eligible for API authentication.',
        ];
    }

    private function isExpired(ApiKey $apiKey): bool
    {
        return $apiKey->expires_at instanceof Carbon && $apiKey->expires_at->isPast();
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
