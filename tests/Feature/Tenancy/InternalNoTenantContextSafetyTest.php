<?php

namespace Tests\Feature\Tenancy;

use App\Models\Account;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class InternalNoTenantContextSafetyTest extends TestCase
{
    #[Test]
    public function test_internal_route_requires_tenant_context_header_when_marked_required(): void
    {
        $internalUser = $this->createUser([
            'account_id' => null,
            'user_type' => 'internal',
        ]);

        Sanctum::actingAs($internalUser);

        $this->getJson('/api/v1/internal/tenant-context/ping')
            ->assertStatus(400)
            ->assertJsonPath('error_code', 'ERR_TENANT_CONTEXT_REQUIRED');
    }

    #[Test]
    public function test_internal_route_with_header_but_without_select_permission_returns_forbidden(): void
    {
        $tenant = $this->createAccount();
        $internalUser = $this->createUser([
            'account_id' => null,
            'user_type' => 'internal',
        ]);

        Sanctum::actingAs($internalUser);

        $this->withHeaders([
            'X-Tenant-Account-Id' => (string) $tenant->id,
        ])->getJson('/api/v1/internal/tenant-context/ping')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_TENANT_CONTEXT_FORBIDDEN');
    }

    #[Test]
    public function test_internal_non_tenant_endpoint_is_accessible_without_tenant_context(): void
    {
        $internalUser = $this->createUser([
            'account_id' => null,
            'user_type' => 'internal',
        ]);

        Sanctum::actingAs($internalUser);

        $this->getJson('/api/v1/internal/ping')
            ->assertOk()
            ->assertJsonPath('data.user_type', 'internal');
    }

    #[Test]
    public function test_internal_route_with_header_and_permission_allows_context_resolution(): void
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

    private function grantInternalPermission(string $userId, string $key): void
    {
        $permission = $this->upsertPermission($key, 'internal');
        $roleId = (string) Str::uuid();

        DB::table('internal_roles')->insert([
            'id' => $roleId,
            'name' => 'int_' . Str::random(8),
            'display_name' => 'Internal Safety Role',
            'description' => 'Internal safety role',
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
