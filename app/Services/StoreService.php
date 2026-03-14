<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Store;
use App\Models\User;
use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;

/**
 * StoreService — FR-IAM-009: Multi-Store Management
 *
 * CRUD for stores within an account, with:
 * - Unique name enforcement per account
 * - Max store limit (configurable, default 20)
 * - Default store management
 * - Connection status tracking
 * - Full audit trail
 */
class StoreService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * List all stores for an account.
     */
    public function listStores(string $accountId, array $filters = []): array
    {
        $query = Store::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->orderByDesc('is_default')
            ->orderBy('name');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['platform'])) {
            $query->where('platform', $filters['platform']);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'ilike', '%' . $filters['search'] . '%')
                  ->orWhere('contact_email', 'ilike', '%' . $filters['search'] . '%');
            });
        }

        $stores = $query->get();

        return $stores->map(fn (Store $s) => $this->formatStore($s))->toArray();
    }

    /**
     * Get a single store by ID.
     */
    public function getStore(string $accountId, string $storeId): array
    {
        $store = Store::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('id', $storeId)
            ->firstOrFail();

        return $this->formatStore($store);
    }

    /**
     * Create a new store.
     *
     * Validates: unique name, max limit, default store logic.
     */
    public function createStore(string $accountId, array $data, User $performer): Store
    {
        $this->assertCanManageStores($performer);
        $this->assertStoreLimit($accountId);
        $this->assertUniqueName($accountId, $data['name']);

        return DB::transaction(function () use ($accountId, $data, $performer) {
            $isFirst = !Store::withoutGlobalScopes()
                ->where('account_id', $accountId)
                ->exists();

            $store = Store::create([
                'account_id'     => $accountId,
                'name'           => $data['name'],
                'slug'           => Store::generateSlug($data['name'], $accountId),
                'status'         => Store::STATUS_ACTIVE,
                'platform'       => $data['platform'] ?? Store::PLATFORM_MANUAL,
                'contact_name'   => $data['contact_name'] ?? null,
                'contact_phone'  => $data['contact_phone'] ?? null,
                'contact_email'  => $data['contact_email'] ?? null,
                'address_line_1' => $data['address_line_1'] ?? null,
                'address_line_2' => $data['address_line_2'] ?? null,
                'city'           => $data['city'] ?? null,
                'state_province' => $data['state_province'] ?? null,
                'postal_code'    => $data['postal_code'] ?? null,
                'country'        => $data['country'] ?? 'SA',
                'currency'       => $data['currency'] ?? 'SAR',
                'language'       => $data['language'] ?? 'ar',
                'timezone'       => $data['timezone'] ?? 'Asia/Riyadh',
                'website_url'    => $data['website_url'] ?? null,
                'is_default'     => $isFirst, // First store is always default
                'created_by'     => $performer->id,
            ]);

            $this->auditService->info(
                $accountId, $performer->id,
                'store.created', AuditLog::CATEGORY_ACCOUNT,
                'Store', $store->id,
                null,
                ['name' => $store->name, 'platform' => $store->platform, 'is_default' => $store->is_default],
                ['slug' => $store->slug]
            );

            return $store;
        });
    }

    /**
     * Update a store.
     */
    public function updateStore(string $accountId, string $storeId, array $data, User $performer): Store
    {
        $this->assertCanManageStores($performer);

        $store = Store::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('id', $storeId)
            ->firstOrFail();

        // If name is being changed, check uniqueness
        if (isset($data['name']) && $data['name'] !== $store->name) {
            $this->assertUniqueName($accountId, $data['name'], $storeId);
            $data['slug'] = Store::generateSlug($data['name'], $accountId);
        }

        return DB::transaction(function () use ($store, $data, $performer) {
            $oldValues = $store->only(array_keys($data));

            $updatable = [
                'name', 'slug', 'status', 'platform',
                'contact_name', 'contact_phone', 'contact_email',
                'address_line_1', 'address_line_2', 'city', 'state_province',
                'postal_code', 'country', 'currency', 'language', 'timezone',
                'website_url',
            ];

            $changes = array_intersect_key($data, array_flip($updatable));
            $store->update($changes);

            $newValues = $store->only(array_keys($changes));

            $this->auditService->info(
                $store->account_id, $performer->id,
                'store.updated', AuditLog::CATEGORY_ACCOUNT,
                'Store', $store->id,
                $oldValues, $newValues,
                ['fields_changed' => array_keys($changes)]
            );

            return $store->fresh();
        });
    }

    /**
     * Set a store as the default for the account.
     */
    public function setDefault(string $accountId, string $storeId, User $performer): Store
    {
        $this->assertCanManageStores($performer);

        $store = Store::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('id', $storeId)
            ->firstOrFail();

        if ($store->is_default) {
            return $store; // Already default
        }

        return DB::transaction(function () use ($accountId, $store, $performer) {
            // Unset current default
            Store::withoutGlobalScopes()
                ->where('account_id', $accountId)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            $store->update(['is_default' => true]);

            $this->auditService->info(
                $accountId, $performer->id,
                'store.set_default', AuditLog::CATEGORY_ACCOUNT,
                'Store', $store->id,
                null,
                ['is_default' => true, 'name' => $store->name]
            );

            return $store->fresh();
        });
    }

    /**
     * Delete (soft) a store.
     * Cannot delete the default store unless it's the last one.
     */
    public function deleteStore(string $accountId, string $storeId, User $performer): void
    {
        $this->assertCanManageStores($performer);

        $store = Store::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('id', $storeId)
            ->firstOrFail();

        $totalStores = Store::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->count();

        if ($store->is_default && $totalStores > 1) {
            throw new BusinessException(
                'لا يمكن حذف المتجر الافتراضي. يرجى تعيين متجر آخر كافتراضي أولاً.',
                'ERR_CANNOT_DELETE_DEFAULT', 422
            );
        }

        DB::transaction(function () use ($store, $performer) {
            $store->delete();

            $this->auditService->warning(
                $store->account_id, $performer->id,
                'store.deleted', AuditLog::CATEGORY_ACCOUNT,
                'Store', $store->id,
                ['name' => $store->name, 'platform' => $store->platform],
                null,
                ['was_default' => $store->is_default]
            );
        });
    }

    /**
     * Toggle store status (activate / deactivate).
     */
    public function toggleStatus(string $accountId, string $storeId, User $performer): Store
    {
        $this->assertCanManageStores($performer);

        $store = Store::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('id', $storeId)
            ->firstOrFail();

        $newStatus = $store->isActive() ? Store::STATUS_INACTIVE : Store::STATUS_ACTIVE;

        $store->update(['status' => $newStatus]);

        $this->auditService->info(
            $accountId, $performer->id,
            'store.status_changed', AuditLog::CATEGORY_ACCOUNT,
            'Store', $store->id,
            ['status' => $store->getOriginal('status')],
            ['status' => $newStatus]
        );

        return $store->fresh();
    }

    /**
     * Get store statistics for the account.
     */
    public function getStoreStats(string $accountId): array
    {
        $stores = Store::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->get();

        return [
            'total'         => $stores->count(),
            'active'        => $stores->where('status', 'active')->count(),
            'inactive'      => $stores->where('status', 'inactive')->count(),
            'connected'     => $stores->where('connection_status', 'connected')->count(),
            'max_allowed'   => Store::MAX_STORES_PER_ACCOUNT,
            'remaining'     => max(0, Store::MAX_STORES_PER_ACCOUNT - $stores->count()),
            'by_platform'   => $stores->groupBy('platform')->map->count()->toArray(),
        ];
    }

    // ─── Validations ─────────────────────────────────────────────

    private function assertStoreLimit(string $accountId): void
    {
        $count = Store::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->count();

        if ($count >= Store::MAX_STORES_PER_ACCOUNT) {
            throw new BusinessException(
                'تم الوصول للحد الأقصى لعدد المتاجر (' . Store::MAX_STORES_PER_ACCOUNT . ').',
                'ERR_MAX_STORES_REACHED', 422
            );
        }
    }

    private function assertUniqueName(string $accountId, string $name, ?string $excludeId = null): void
    {
        $query = Store::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('name', $name);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new BusinessException(
                'يوجد متجر بنفس الاسم في هذا الحساب.',
                'ERR_STORE_EXISTS', 422
            );
        }
    }

    private function assertCanManageStores(User $user): void
    {
        if (!$user->hasPermission('stores.manage')) {
            $this->auditService->warning(
                $user->account_id, $user->id,
                'store.access_denied', AuditLog::CATEGORY_ACCOUNT,
                null, null, null, null,
                ['attempted_action' => 'manage_stores']
            );
            throw BusinessException::permissionDenied();
        }
    }

    // ─── Formatting ──────────────────────────────────────────────

    private function formatStore(Store $store): array
    {
        return [
            'id'                => $store->id,
            'name'              => $store->name,
            'slug'              => $store->slug,
            'status'            => $store->status,
            'platform'          => $store->platform,
            'platform_display'  => $store->platformDisplay(),
            'contact'           => [
                'name'  => $store->contact_name,
                'phone' => $store->contact_phone,
                'email' => $store->contact_email,
            ],
            'address'           => [
                'line_1'         => $store->address_line_1,
                'line_2'         => $store->address_line_2,
                'city'           => $store->city,
                'state_province' => $store->state_province,
                'postal_code'    => $store->postal_code,
                'country'        => $store->country,
            ],
            'currency'          => $store->currency,
            'language'          => $store->language,
            'timezone'          => $store->timezone,
            'website_url'       => $store->website_url,
            'connection_status' => $store->connection_status,
            'last_synced_at'    => $store->last_synced_at?->toISOString(),
            'is_default'        => $store->is_default,
            'created_at'        => $store->created_at?->toISOString(),
        ];
    }
}
