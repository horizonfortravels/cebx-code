<?php

namespace Tests\Feature\Authorization;

use App\Models\Account;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ApiKeysWebhooksAuthorizationTest extends TestCase
{
    #[Test]
    public function test_external_portal_api_keys_endpoints_require_api_key_permissions(): void
    {
        $account = $this->createAccount();

        $externalUser = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        Sanctum::actingAs($externalUser);

        $this->getJson('/api/v1/portal/api-keys')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');

        $this->postJson('/api/v1/portal/api-keys', [
            'name' => 'Portal Key',
            'permissions' => ['shipments.read'],
        ])->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');
    }

    #[Test]
    public function test_admin_api_keys_endpoint_is_internal_only_and_requires_tenant_context_permissions(): void
    {
        $tenant = $this->createAccount();

        $externalUser = $this->createUser([
            'account_id' => (string) $tenant->id,
            'user_type' => 'external',
        ]);

        Sanctum::actingAs($externalUser);

        $this->getJson('/api/v1/admin/api-keys')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_USER_TYPE_FORBIDDEN');

        $internalNoSelect = $this->createUser([
            'account_id' => null,
            'user_type' => 'internal',
        ]);

        $this->grantInternalPermission((string) $internalNoSelect->id, 'admin.access', 'internal');

        Sanctum::actingAs($internalNoSelect);

        $this->getJson('/api/v1/admin/api-keys')
            ->assertStatus(400)
            ->assertJsonPath('error_code', 'ERR_TENANT_CONTEXT_REQUIRED');

        $this->withHeaders([
            'X-Tenant-Account-Id' => (string) $tenant->id,
        ])->getJson('/api/v1/admin/api-keys')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_TENANT_CONTEXT_FORBIDDEN');
    }

    #[Test]
    public function test_admin_api_keys_endpoint_allows_internal_user_with_header_and_permissions(): void
    {
        if (!Schema::hasTable('api_keys')) {
            $this->markTestSkipped('api_keys table is not available in this environment.');
        }

        $tenant = $this->createAccount();

        $internalUser = $this->createUser([
            'account_id' => null,
            'user_type' => 'internal',
        ]);

        $this->grantInternalPermission((string) $internalUser->id, 'admin.access', 'internal');
        $this->grantInternalPermission((string) $internalUser->id, 'tenancy.context.select', 'internal');
        $this->grantInternalPermission((string) $internalUser->id, 'api_keys.read', 'internal');

        Sanctum::actingAs($internalUser);

        $this->withHeaders([
            'X-Tenant-Account-Id' => (string) $tenant->id,
        ])->getJson('/api/v1/admin/api-keys')
            ->assertOk();
    }

    #[Test]
    public function test_public_webhook_tracking_endpoint_remains_public(): void
    {
        Log::spy();

        $response = $this->getJson('/api/v1/webhooks/track/NO-SUCH-TRACKING-NUMBER');

        $this->assertNotContains($response->status(), [401, 403]);
        $this->assertNotEquals(500, $response->status());
        Log::shouldNotHaveReceived('error');
    }

    private function grantInternalPermission(string $userId, string $key, string $audience): void
    {
        $permission = $this->upsertPermission($key, $audience);
        $roleId = (string) Str::uuid();

        DB::table('internal_roles')->insert([
            'id' => $roleId,
            'name' => 'internal_api_' . Str::random(8),
            'display_name' => 'Internal API Role',
            'description' => 'Internal API role',
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
