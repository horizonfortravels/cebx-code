<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Account;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InternalApiKeyActionService
{
    /**
     * @var array<string, string>
     */
    private const SAFE_SCOPE_OPTIONS = [
        'shipments:read' => 'Shipments read',
        'shipments:write' => 'Shipments write',
    ];

    public function __construct(
        private readonly AdminService $adminService,
        private readonly AuditService $auditService,
    ) {}

    /**
     * @return array{api_key: ApiKey, raw_key: string}
     */
    public function createKey(string $accountId, User $actor, string $name, array $scopes, string $reason): array
    {
        $this->assertCanManageKeys($actor);

        $account = Account::query()->withoutGlobalScopes()->findOrFail($accountId);
        $normalizedName = $this->normalizeName($name);
        $normalizedScopes = $this->normalizeScopes($scopes);
        $normalizedReason = $this->normalizeReason($reason);

        if ($normalizedName === '') {
            throw new BusinessException(
                'A key name is required before creating an internal API key.',
                'ERR_API_KEY_NAME_REQUIRED',
                422
            );
        }

        if ($normalizedScopes === []) {
            throw new BusinessException(
                'Choose at least one safe scope before creating an internal API key.',
                'ERR_API_KEY_SCOPE_REQUIRED',
                422
            );
        }

        return DB::transaction(function () use ($account, $actor, $normalizedName, $normalizedScopes, $normalizedReason): array {
            $created = $this->adminService->createApiKey($account, $actor, $normalizedName, $normalizedScopes);
            $apiKey = ApiKey::query()
                ->withoutGlobalScopes()
                ->with(['account', 'creator'])
                ->findOrFail((string) $created['api_key']->id);

            $this->auditService->info(
                (string) $account->id,
                (string) $actor->id,
                'api_key.created',
                AuditLog::CATEGORY_ACCOUNT,
                'ApiKey',
                (string) $apiKey->id,
                null,
                $this->snapshot($apiKey),
                [
                    'reason' => $normalizedReason,
                    'scope_count' => count($normalizedScopes),
                    'scopes' => $normalizedScopes,
                    'key_prefix' => (string) $apiKey->key_prefix,
                    'internal_scope' => 'internal_api_keys_center',
                ]
            );

            return [
                'api_key' => $apiKey,
                'raw_key' => (string) $created['raw_key'],
            ];
        });
    }

    /**
     * @return array{api_key: ApiKey, raw_key: string}
     */
    public function rotateKey(string $keyId, User $actor, string $reason): array
    {
        $this->assertCanManageKeys($actor);

        $apiKey = ApiKey::query()
            ->withoutGlobalScopes()
            ->findOrFail($keyId);
        $normalizedReason = $this->normalizeReason($reason);

        if ($apiKey->isRevoked()) {
            throw new BusinessException(
                'Only active API keys can be rotated from the internal center.',
                'ERR_API_KEY_ALREADY_REVOKED',
                422
            );
        }

        return DB::transaction(function () use ($apiKey, $actor, $normalizedReason): array {
            $oldValues = $this->snapshot($apiKey);
            $rotated = $this->adminService->rotateApiKey((string) $apiKey->id, $actor);
            $newKey = ApiKey::query()
                ->withoutGlobalScopes()
                ->with(['account', 'creator'])
                ->findOrFail((string) $rotated['api_key']->id);

            $this->auditService->info(
                (string) $newKey->account_id,
                (string) $actor->id,
                'api_key.rotated',
                AuditLog::CATEGORY_ACCOUNT,
                'ApiKey',
                (string) $newKey->id,
                $oldValues,
                $this->snapshot($newKey),
                [
                    'reason' => $normalizedReason,
                    'rotated_from_id' => (string) $apiKey->id,
                    'rotated_from_prefix' => (string) $apiKey->key_prefix,
                    'internal_scope' => 'internal_api_keys_center',
                ]
            );

            return [
                'api_key' => $newKey,
                'raw_key' => (string) $rotated['raw_key'],
            ];
        });
    }

    public function revokeKey(string $keyId, User $actor, string $reason): ApiKey
    {
        $this->assertCanManageKeys($actor);

        $apiKey = ApiKey::query()
            ->withoutGlobalScopes()
            ->with(['account', 'creator'])
            ->findOrFail($keyId);
        $normalizedReason = $this->normalizeReason($reason);

        if ($apiKey->isRevoked()) {
            throw new BusinessException(
                'This API key has already been revoked.',
                'ERR_API_KEY_ALREADY_REVOKED',
                422
            );
        }

        return DB::transaction(function () use ($apiKey, $actor, $normalizedReason): ApiKey {
            $oldValues = $this->snapshot($apiKey);
            $this->adminService->revokeApiKey((string) $apiKey->id);
            $apiKey->refresh();

            $this->auditService->info(
                (string) $apiKey->account_id,
                (string) $actor->id,
                'api_key.revoked',
                AuditLog::CATEGORY_ACCOUNT,
                'ApiKey',
                (string) $apiKey->id,
                $oldValues,
                $this->snapshot($apiKey),
                [
                    'reason' => $normalizedReason,
                    'internal_scope' => 'internal_api_keys_center',
                ]
            );

            return $apiKey;
        });
    }

    /**
     * @return array<string, string>
     */
    public function scopeOptions(): array
    {
        return self::SAFE_SCOPE_OPTIONS;
    }

    private function assertCanManageKeys(User $actor): void
    {
        if (! $actor->hasPermission('api_keys.manage')) {
            throw BusinessException::permissionDenied();
        }
    }

    private function normalizeName(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private function normalizeReason(string $value): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        if ($normalized === '') {
            throw new BusinessException(
                'A clear operator reason is required for internal API-key actions.',
                'ERR_API_KEY_REASON_REQUIRED',
                422
            );
        }

        return $normalized;
    }

    /**
     * @param array<int, mixed> $scopes
     * @return array<int, string>
     */
    private function normalizeScopes(array $scopes): array
    {
        return collect($scopes)
            ->map(static fn ($scope): string => strtolower(trim((string) $scope)))
            ->filter(static fn (string $scope): bool => array_key_exists($scope, self::SAFE_SCOPE_OPTIONS))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ApiKey $apiKey): array
    {
        return [
            'name' => (string) $apiKey->name,
            'key_prefix' => (string) $apiKey->key_prefix,
            'account_id' => (string) $apiKey->account_id,
            'created_by' => (string) $apiKey->created_by,
            'is_active' => (bool) $apiKey->is_active,
            'scopes' => collect($apiKey->scopes ?? [])
                ->filter(static fn ($scope): bool => is_string($scope) && trim($scope) !== '')
                ->values()
                ->all(),
            'last_used_at' => $apiKey->last_used_at?->toIso8601String(),
            'expires_at' => $apiKey->expires_at?->toIso8601String(),
            'revoked_at' => $apiKey->revoked_at?->toIso8601String(),
        ];
    }
}
