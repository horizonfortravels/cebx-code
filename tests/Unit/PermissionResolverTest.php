<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\PermissionResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PermissionResolverTest extends TestCase
{
    #[Test]
    public function test_external_permissions_are_resolved_from_tenant_rbac_with_audience_enforced(): void
    {
        $resolver = app(PermissionResolver::class);

        $account = $this->createAccount();
        $user = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
            'is_owner' => true,
            'is_super_admin' => true,
            'role' => 'admin',
            'role_name' => 'admin',
        ]);

        $tenantRole = Role::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'name' => 'resolver_external_role',
            'display_name' => 'Resolver External Role',
            'description' => 'Test role',
            'is_system' => false,
            'template' => null,
        ]);

        $shipmentRead = $this->upsertPermission('shipments.read', 'both');
        $tenantContextSelect = $this->upsertPermission('tenancy.context.select', 'internal');

        DB::table('role_permission')->updateOrInsert(
            ['role_id' => $tenantRole->id, 'permission_id' => $shipmentRead->id],
            ['granted_at' => now()]
        );
        DB::table('role_permission')->updateOrInsert(
            ['role_id' => $tenantRole->id, 'permission_id' => $tenantContextSelect->id],
            ['granted_at' => now()]
        );
        DB::table('user_role')->updateOrInsert(
            ['user_id' => $user->id, 'role_id' => $tenantRole->id],
            ['assigned_by' => null, 'assigned_at' => now()]
        );

        $this->assertTrue($resolver->can($user, 'shipments.read'));
        $this->assertFalse($resolver->can($user, 'tenancy.context.select'));
    }

    #[Test]
    public function test_internal_permissions_are_resolved_from_internal_rbac_only(): void
    {
        $resolver = app(PermissionResolver::class);

        $account = $this->createAccount();
        $internalUser = $this->createUser([
            'account_id' => null,
            'user_type' => 'internal',
            'is_owner' => true,
            'is_super_admin' => true,
            'role' => 'admin',
            'role_name' => 'admin',
        ]);

        $tenantRole = Role::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'name' => 'resolver_tenant_only_role',
            'display_name' => 'Resolver Tenant Only Role',
            'description' => 'Test role',
            'is_system' => false,
            'template' => null,
        ]);

        $shipmentRead = $this->upsertPermission('shipments.read', 'external');
        $tenantContextSelect = $this->upsertPermission('tenancy.context.select', 'internal');

        DB::table('role_permission')->updateOrInsert(
            ['role_id' => $tenantRole->id, 'permission_id' => $shipmentRead->id],
            ['granted_at' => now()]
        );
        DB::table('user_role')->updateOrInsert(
            ['user_id' => $internalUser->id, 'role_id' => $tenantRole->id],
            ['assigned_by' => null, 'assigned_at' => now()]
        );

        $internalRoleId = (string) Str::uuid();
        DB::table('internal_roles')->insert([
            'id' => $internalRoleId,
            'name' => 'resolver_internal_role',
            'display_name' => 'Resolver Internal Role',
            'description' => 'Test role',
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        DB::table('internal_role_permission')->insert([
            'internal_role_id' => $internalRoleId,
            'permission_id' => $tenantContextSelect->id,
            'granted_at' => now(),
        ]);

        DB::table('internal_user_role')->insert([
            'user_id' => $internalUser->id,
            'internal_role_id' => $internalRoleId,
            'assigned_by' => null,
            'assigned_at' => now(),
        ]);

        $this->assertTrue($resolver->can($internalUser, 'tenancy.context.select'));
        $this->assertFalse($resolver->can($internalUser, 'shipments.read'));
    }

    #[Test]
    public function test_permission_resolver_denies_by_default_when_no_assignments_exist(): void
    {
        $resolver = app(PermissionResolver::class);

        $account = $this->createAccount();
        $externalUser = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
            'is_owner' => true,
            'is_super_admin' => true,
            'role' => 'admin',
            'role_name' => 'admin',
        ]);

        $this->upsertPermission('users.manage', 'both');

        $this->assertFalse($resolver->can($externalUser, 'users.manage'));
    }

    private function createAccount(): Account
    {
        $payload = [
            'name' => 'Account '.Str::random(8),
        ];

        if (Schema::hasColumn('accounts', 'slug')) {
            $payload['slug'] = 'acct-'.Str::lower(Str::random(8));
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
            'name' => 'User '.Str::random(8),
            'email' => Str::lower(Str::random(10)).'@example.test',
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
