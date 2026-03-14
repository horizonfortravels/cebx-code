<?php

namespace Tests\Feature\Authorization;

use App\Models\Account;
use App\Models\BillingWallet;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class WalletAuthorizationMatrixTest extends TestCase
{
    #[Test]
    public function test_external_same_tenant_with_permission_can_view_wallet_balance(): void
    {
        if (!Schema::hasTable('billing_wallets')) {
            $this->markTestSkipped('billing_wallets table is not available in this environment.');
        }

        $account = $this->createAccount();
        $user = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        $this->grantExternalPermission((string) $user->id, (string) $account->id, 'wallet.balance');

        $wallet = BillingWallet::withoutGlobalScopes()->create([
            'account_id' => (string) $account->id,
            'currency' => 'SAR',
            'available_balance' => 100,
            'reserved_balance' => 0,
            'total_credited' => 100,
            'total_debited' => 0,
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/billing/wallets/' . $wallet->id . '/balance');

        $response->assertOk();
    }

    #[Test]
    public function test_external_same_tenant_missing_permission_gets_403(): void
    {
        if (!Schema::hasTable('billing_wallets')) {
            $this->markTestSkipped('billing_wallets table is not available in this environment.');
        }

        $account = $this->createAccount();
        $user = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        $wallet = BillingWallet::withoutGlobalScopes()->create([
            'account_id' => (string) $account->id,
            'currency' => 'SAR',
            'available_balance' => 100,
            'reserved_balance' => 0,
            'total_credited' => 100,
            'total_debited' => 0,
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/billing/wallets/' . $wallet->id . '/balance');

        $response->assertStatus(403);
    }

    #[Test]
    public function test_external_cross_tenant_wallet_returns_404_even_with_permission(): void
    {
        if (!Schema::hasTable('billing_wallets')) {
            $this->markTestSkipped('billing_wallets table is not available in this environment.');
        }

        $accountA = $this->createAccount();
        $accountB = $this->createAccount();

        $userB = $this->createUser([
            'account_id' => (string) $accountB->id,
            'user_type' => 'external',
        ]);

        $this->grantExternalPermission((string) $userB->id, (string) $accountB->id, 'wallet.balance');

        $walletA = BillingWallet::withoutGlobalScopes()->create([
            'account_id' => (string) $accountA->id,
            'currency' => 'SAR',
            'available_balance' => 100,
            'reserved_balance' => 0,
            'total_credited' => 100,
            'total_debited' => 0,
            'status' => 'active',
        ]);

        Sanctum::actingAs($userB);

        $response = $this->getJson('/api/v1/billing/wallets/' . $walletA->id . '/balance');

        $response->assertNotFound();
    }

    #[Test]
    public function test_internal_with_tenant_context_permission_can_resolve_context(): void
    {
        $tenant = $this->createAccount();

        $internalUser = $this->createUser([
            'account_id' => null,
            'user_type' => 'internal',
        ]);

        $this->grantInternalPermission((string) $internalUser->id, 'tenancy.context.select');

        Sanctum::actingAs($internalUser);

        $response = $this->withHeaders([
            'X-Tenant-Account-Id' => (string) $tenant->id,
        ])->getJson('/api/v1/internal/tenant-context/ping');

        $response->assertOk();
        $this->assertSame((string) $tenant->id, (string) $response->json('data.current_account_id'));
    }

    private function grantExternalPermission(string $userId, string $accountId, string $key): void
    {
        $permission = $this->upsertPermission($key, 'external');
        $roleId = (string) Str::uuid();

        DB::table('roles')->insert([
            'id' => $roleId,
            'account_id' => $accountId,
            'name' => 'ext_' . Str::random(8),
            'display_name' => 'External Matrix Role',
            'description' => 'External matrix role',
            'is_system' => false,
            'template' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        DB::table('role_permission')->insert([
            'role_id' => $roleId,
            'permission_id' => $permission->id,
            'granted_at' => now(),
        ]);

        DB::table('user_role')->insert([
            'user_id' => $userId,
            'role_id' => $roleId,
            'assigned_by' => null,
            'assigned_at' => now(),
        ]);
    }

    private function grantInternalPermission(string $userId, string $key): void
    {
        $permission = $this->upsertPermission($key, 'internal');
        $roleId = (string) Str::uuid();

        DB::table('internal_roles')->insert([
            'id' => $roleId,
            'name' => 'int_' . Str::random(8),
            'display_name' => 'Internal Matrix Role',
            'description' => 'Internal matrix role',
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        DB::table('internal_role_permission')->insert([
            'internal_role_id' => $roleId,
            'permission_id' => $permission->id,
            'granted_at' => now(),
        ]);

        DB::table('internal_user_role')->insert([
            'user_id' => $userId,
            'internal_role_id' => $roleId,
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
