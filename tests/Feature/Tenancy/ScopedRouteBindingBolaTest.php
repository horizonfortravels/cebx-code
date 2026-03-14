<?php

namespace Tests\Feature\Tenancy;

use App\Models\Account;
use App\Models\Permission;
use App\Models\User;
use App\Services\StoreService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ScopedRouteBindingBolaTest extends TestCase
{
    #[Test]
    public function test_external_cross_tenant_bindings_return_404_for_shipments_orders_and_stores(): void
    {
        if (!$this->hasRequiredTables(['shipments', 'orders', 'stores'])) {
            $this->markTestSkipped('Required tables (shipments/orders/stores) are not available in this environment.');
        }

        $accountA = $this->createAccount();
        $accountB = $this->createAccount();

        $userA = $this->createUser([
            'account_id' => (string) $accountA->id,
            'user_type' => 'external',
        ]);

        $userB = $this->createUser([
            'account_id' => (string) $accountB->id,
            'user_type' => 'external',
        ]);

        $this->grantExternalPermissions((string) $userA->id, (string) $accountA->id, [
            'shipments.read',
            'orders.read',
            'stores.read',
        ]);

        $storeB = $this->createStore((string) $accountB->id);
        $orderB = $this->createOrder((string) $accountB->id, (string) $storeB->id);
        $shipmentB = $this->createShipment((string) $accountB->id, (string) $userB->id);

        Sanctum::actingAs($userA);

        $this->getJson('/api/v1/shipments/' . $shipmentB->id)->assertNotFound();
        $this->getJson('/api/v1/orders/' . $orderB->id)->assertNotFound();
        $this->getJson('/api/v1/stores/' . $storeB->id)->assertNotFound();
    }

    #[Test]
    public function test_external_same_tenant_store_show_succeeds(): void
    {
        if (!Schema::hasTable('stores')) {
            $this->markTestSkipped('stores table is not available in this environment.');
        }

        $account = $this->createAccount();
        $user = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        $store = $this->createStore((string) $account->id);

        $this->grantExternalPermissions((string) $user->id, (string) $account->id, [
            'stores.read',
        ]);

        $this->mock(StoreService::class, function ($mock) use ($account, $store): void {
            $mock->shouldReceive('getStore')
                ->once()
                ->with((string) $account->id, (string) $store->id)
                ->andReturn([
                    'id' => (string) $store->id,
                    'account_id' => (string) $account->id,
                    'name' => 'Mock Store',
                    'platform' => 'shopify',
                ]);
        });

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/stores/' . $store->id)->assertOk();
    }

    /**
     * @param array<int, string> $tables
     */
    private function hasRequiredTables(array $tables): bool
    {
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, string> $permissions
     */
    private function grantExternalPermissions(string $userId, string $accountId, array $permissions): void
    {
        $roleId = (string) Str::uuid();

        DB::table('roles')->insert([
            'id' => $roleId,
            'account_id' => $accountId,
            'name' => 'ext_' . Str::random(8),
            'display_name' => 'External BOLA Role',
            'description' => 'External BOLA role',
            'is_system' => false,
            'template' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        foreach ($permissions as $permissionKey) {
            $permission = $this->upsertPermission($permissionKey, 'external');

            DB::table('role_permission')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => $permission->id,
            ], [
                'granted_at' => now(),
            ]);
        }

        DB::table('user_role')->updateOrInsert([
            'user_id' => $userId,
            'role_id' => $roleId,
        ], [
            'assigned_by' => null,
            'assigned_at' => now(),
        ]);
    }

    private function createAccount(): Account
    {
        $payload = [
            'name' => 'Account ' . Str::random(8),
        ];

        if (Schema::hasColumn('accounts', 'slug')) {
            $payload['slug'] = 'acct-' . Str::lower(Str::random(8));
        }
        if (Schema::hasColumn('accounts', 'status')) {
            $payload['status'] = 'active';
        }
        if (Schema::hasColumn('accounts', 'type')) {
            $payload['type'] = 'organization';
        }
        if (Schema::hasColumn('accounts', 'kyc_status')) {
            $payload['kyc_status'] = 'not_submitted';
        }
        if (Schema::hasColumn('accounts', 'settings')) {
            $payload['settings'] = [];
        }
        if (Schema::hasColumn('accounts', 'created_at')) {
            $payload['created_at'] = now();
        }
        if (Schema::hasColumn('accounts', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        $accountId = $this->insertRowAndReturnId('accounts', $payload);

        /** @var Account $account */
        $account = Account::withoutGlobalScopes()->where('id', $accountId)->firstOrFail();

        return $account;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createUser(array $overrides): User
    {
        $payload = [
            'name' => 'User ' . Str::random(8),
            'email' => Str::lower(Str::random(10)) . '@example.test',
            'password' => Hash::make('Password1!'),
        ];

        if (Schema::hasColumn('users', 'account_id')) {
            $payload['account_id'] = $overrides['account_id'] ?? null;
        }
        if (Schema::hasColumn('users', 'user_type')) {
            $payload['user_type'] = $overrides['user_type'] ?? 'external';
        }
        if (Schema::hasColumn('users', 'status')) {
            $payload['status'] = $overrides['status'] ?? 'active';
        }
        if (Schema::hasColumn('users', 'is_active')) {
            $payload['is_active'] = $overrides['is_active'] ?? true;
        }
        if (Schema::hasColumn('users', 'is_owner')) {
            $payload['is_owner'] = $overrides['is_owner'] ?? false;
        }
        if (Schema::hasColumn('users', 'is_super_admin')) {
            $payload['is_super_admin'] = $overrides['is_super_admin'] ?? false;
        }
        if (Schema::hasColumn('users', 'role')) {
            $payload['role'] = $overrides['role'] ?? 'operator';
        }
        if (Schema::hasColumn('users', 'role_name')) {
            $payload['role_name'] = $overrides['role_name'] ?? 'operator';
        }
        if (Schema::hasColumn('users', 'locale')) {
            $payload['locale'] = $overrides['locale'] ?? 'en';
        }
        if (Schema::hasColumn('users', 'timezone')) {
            $payload['timezone'] = $overrides['timezone'] ?? 'UTC';
        }
        if (Schema::hasColumn('users', 'created_at')) {
            $payload['created_at'] = now();
        }
        if (Schema::hasColumn('users', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        $userId = $this->insertRowAndReturnId('users', $payload);

        /** @var User $user */
        $user = User::withoutGlobalScopes()->where('id', $userId)->firstOrFail();

        return $user;
    }

    private function createStore(string $accountId): object
    {
        $payload = [];

        if (Schema::hasColumn('stores', 'account_id')) {
            $payload['account_id'] = $accountId;
        }
        if (Schema::hasColumn('stores', 'name')) {
            $payload['name'] = 'Store ' . Str::random(8);
        }
        if (Schema::hasColumn('stores', 'platform')) {
            $payload['platform'] = 'shopify';
        }
        if (Schema::hasColumn('stores', 'created_at')) {
            $payload['created_at'] = now();
        }
        if (Schema::hasColumn('stores', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        $storeId = $this->insertRowAndReturnId('stores', $payload);

        return DB::table('stores')->where('id', $storeId)->firstOrFail();
    }

    private function createOrder(string $accountId, string $storeId): object
    {
        $payload = [];

        if (Schema::hasColumn('orders', 'account_id')) {
            $payload['account_id'] = $accountId;
        }
        if (Schema::hasColumn('orders', 'store_id')) {
            $payload['store_id'] = $storeId;
        }
        if (Schema::hasColumn('orders', 'order_number')) {
            $payload['order_number'] = 'ORD-' . strtoupper(Str::random(10));
        }
        if (Schema::hasColumn('orders', 'external_order_id')) {
            $payload['external_order_id'] = 'EXT-' . strtoupper(Str::random(10));
        }
        if (Schema::hasColumn('orders', 'external_order_number')) {
            $payload['external_order_number'] = 'NO-' . strtoupper(Str::random(6));
        }
        if (Schema::hasColumn('orders', 'customer_name')) {
            $payload['customer_name'] = 'Customer ' . Str::random(5);
        }
        if (Schema::hasColumn('orders', 'source')) {
            $payload['source'] = 'manual';
        }
        if (Schema::hasColumn('orders', 'status')) {
            $payload['status'] = 'pending';
        }
        if (Schema::hasColumn('orders', 'currency')) {
            $payload['currency'] = 'SAR';
        }
        if (Schema::hasColumn('orders', 'created_at')) {
            $payload['created_at'] = now();
        }
        if (Schema::hasColumn('orders', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        $orderId = $this->insertRowAndReturnId('orders', $payload);

        return DB::table('orders')->where('id', $orderId)->firstOrFail();
    }

    private function createShipment(string $accountId, string $userId): object
    {
        $payload = [];

        if (Schema::hasColumn('shipments', 'account_id')) {
            $payload['account_id'] = $accountId;
        }
        if (Schema::hasColumn('shipments', 'user_id')) {
            $payload['user_id'] = $userId;
        }
        if (Schema::hasColumn('shipments', 'created_by')) {
            $payload['created_by'] = $userId;
        }
        if (Schema::hasColumn('shipments', 'reference_number')) {
            $payload['reference_number'] = 'SHP-T-' . strtoupper(Str::random(10));
        }
        if (Schema::hasColumn('shipments', 'type')) {
            $payload['type'] = 'domestic';
        }
        if (Schema::hasColumn('shipments', 'source')) {
            $payload['source'] = 'direct';
        }
        if (Schema::hasColumn('shipments', 'status')) {
            $payload['status'] = 'draft';
        }
        if (Schema::hasColumn('shipments', 'sender_name')) {
            $payload['sender_name'] = 'Sender';
        }
        if (Schema::hasColumn('shipments', 'sender_phone')) {
            $payload['sender_phone'] = '+966500000001';
        }
        if (Schema::hasColumn('shipments', 'sender_city')) {
            $payload['sender_city'] = 'Riyadh';
        }
        if (Schema::hasColumn('shipments', 'sender_country')) {
            $payload['sender_country'] = 'SA';
        }
        if (Schema::hasColumn('shipments', 'sender_address')) {
            $payload['sender_address'] = 'Street 1';
        }
        if (Schema::hasColumn('shipments', 'sender_address_1')) {
            $payload['sender_address_1'] = 'Street 1';
        }
        if (Schema::hasColumn('shipments', 'recipient_name')) {
            $payload['recipient_name'] = 'Recipient';
        }
        if (Schema::hasColumn('shipments', 'recipient_phone')) {
            $payload['recipient_phone'] = '+966500000002';
        }
        if (Schema::hasColumn('shipments', 'recipient_city')) {
            $payload['recipient_city'] = 'Jeddah';
        }
        if (Schema::hasColumn('shipments', 'recipient_country')) {
            $payload['recipient_country'] = 'SA';
        }
        if (Schema::hasColumn('shipments', 'recipient_address')) {
            $payload['recipient_address'] = 'Street 2';
        }
        if (Schema::hasColumn('shipments', 'recipient_address_1')) {
            $payload['recipient_address_1'] = 'Street 2';
        }
        if (Schema::hasColumn('shipments', 'currency')) {
            $payload['currency'] = 'SAR';
        }
        if (Schema::hasColumn('shipments', 'created_at')) {
            $payload['created_at'] = now();
        }
        if (Schema::hasColumn('shipments', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        $shipmentId = $this->insertRowAndReturnId('shipments', $payload);

        return DB::table('shipments')->where('id', $shipmentId)->firstOrFail();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function insertRowAndReturnId(string $table, array $payload): string|int
    {
        if (!array_key_exists('id', $payload) && !$this->isNumericId($table)) {
            $payload['id'] = (string) Str::uuid();
        }

        if ($this->isNumericId($table)) {
            unset($payload['id']);
            return DB::table($table)->insertGetId($payload);
        }

        DB::table($table)->insert($payload);

        return $payload['id'];
    }

    private function isNumericId(string $table): bool
    {
        if (!Schema::hasColumn($table, 'id')) {
            return false;
        }

        $type = strtolower((string) Schema::getColumnType($table, 'id'));

        return in_array($type, [
            'integer', 'int', 'tinyint', 'smallint', 'mediumint', 'bigint',
            'biginteger', 'unsignedinteger', 'unsignedbiginteger',
        ], true);
    }

    private function upsertPermission(string $key, string $audience): Permission
    {
        $values = [
            'group' => explode('.', $key)[0],
            'display_name' => $key,
            'description' => $key,
        ];

        if (Schema::hasColumn('permissions', 'audience')) {
            $values['audience'] = $audience;
        }

        return Permission::query()->updateOrCreate(['key' => $key], $values);
    }
}
