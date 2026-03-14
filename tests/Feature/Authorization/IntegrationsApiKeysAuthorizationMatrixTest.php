<?php

namespace Tests\Feature\Authorization;

use App\Models\Account;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntegrationsApiKeysAuthorizationMatrixTest extends TestCase
{
    #[Test]
    public function external_same_tenant_with_integrations_read_permission_gets_success(): void
    {
        $account = $this->createAccount();
        $user = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        $this->grantTenantPermissions($user, ['integrations.read'], 'test_integrations_reader');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/integrations')
            ->assertOk();
    }

    #[Test]
    public function external_same_tenant_without_integrations_read_permission_gets_403(): void
    {
        $account = $this->createAccount();
        $user = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/integrations')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');
    }

    #[Test]
    public function webhook_config_requires_both_integrations_read_and_webhooks_read(): void
    {
        $account = $this->createAccount();
        $user = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        $this->grantTenantPermissions($user, ['integrations.read'], 'test_integrations_only');

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/integrations/webhook-config')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');

        $this->grantTenantPermissions($user, ['integrations.read', 'webhooks.read'], 'test_integrations_webhooks');

        $this->getJson('/api/v1/integrations/webhook-config')
            ->assertOk();
    }

    #[Test]
    public function external_missing_integrations_manage_permission_on_test_endpoint_gets_403(): void
    {
        $account = $this->createAccount();
        $user = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        $this->grantTenantPermissions($user, ['integrations.read'], 'test_integrations_read_only');

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/integrations/dhl/test')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');
    }

    #[Test]
    public function external_cross_tenant_portal_api_key_revoke_returns_404(): void
    {
        if (!Schema::hasTable('customer_api_keys')) {
            $this->markTestSkipped('customer_api_keys table is missing in this environment.');
        }

        $accountA = $this->createAccount();
        $accountB = $this->createAccount();

        $userA = $this->createUser([
            'account_id' => (string) $accountA->id,
            'user_type' => 'external',
        ]);
        $this->grantTenantPermissions($userA, ['api_keys.manage'], 'test_api_keys_manager');

        $userB = $this->createUser([
            'account_id' => (string) $accountB->id,
            'user_type' => 'external',
        ]);

        $keyId = (string) Str::uuid();
        $payload = [
            'id' => $keyId,
            'account_id' => (string) $accountB->id,
            'user_id' => (string) $userB->id,
            'name' => 'B key',
            'key_hash' => hash('sha256', 'dummy-key-' . Str::random(20)),
            'key_prefix' => 'cbex_' . Str::lower(Str::random(6)),
            'permissions' => json_encode(['shipments.read']),
            'rate_limit_per_minute' => 60,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('customer_api_keys', 'deleted_at')) {
            $payload['deleted_at'] = null;
        }

        DB::table('customer_api_keys')->insert($payload);

        Sanctum::actingAs($userA);

        $this->deleteJson('/api/v1/portal/api-keys/' . $keyId)
            ->assertStatus(404);
    }

    #[Test]
    public function internal_admin_api_keys_require_tenant_context_select_and_permissions(): void
    {
        if (!Schema::hasTable('api_keys')) {
            $this->markTestSkipped('api_keys table is missing in this environment.');
        }

        $tenant = $this->createAccount();

        $internalWithoutSelect = $this->createUser([
            'account_id' => null,
            'user_type' => 'internal',
        ]);
        $this->grantInternalPermission((string) $internalWithoutSelect->id, 'admin.access', 'internal');
        $this->grantInternalPermission((string) $internalWithoutSelect->id, 'api_keys.read', 'internal');

        Sanctum::actingAs($internalWithoutSelect);

        $this->getJson('/api/v1/admin/api-keys')
            ->assertStatus(400)
            ->assertJsonPath('error_code', 'ERR_TENANT_CONTEXT_REQUIRED');

        $this->withHeaders([
            'X-Tenant-Account-Id' => (string) $tenant->id,
        ])->getJson('/api/v1/admin/api-keys')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_TENANT_CONTEXT_FORBIDDEN');

        $internalWithSelect = $this->createUser([
            'account_id' => null,
            'user_type' => 'internal',
        ]);
        $this->grantInternalPermission((string) $internalWithSelect->id, 'admin.access', 'internal');
        $this->grantInternalPermission((string) $internalWithSelect->id, 'api_keys.read', 'internal');
        $this->grantInternalPermission((string) $internalWithSelect->id, 'tenancy.context.select', 'internal');

        Sanctum::actingAs($internalWithSelect);

        $this->withHeaders([
            'X-Tenant-Account-Id' => (string) $tenant->id,
        ])->getJson('/api/v1/admin/api-keys')
            ->assertOk();
    }

    private function grantInternalPermission(string $userId, string $key, string $audience): void
    {
        $permission = $this->upsertPermission($key, $audience);
        $roleId = (string) Str::uuid();

        DB::table('internal_roles')->insert([
            'id' => $roleId,
            'name' => 'internal_matrix_' . Str::random(8),
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
