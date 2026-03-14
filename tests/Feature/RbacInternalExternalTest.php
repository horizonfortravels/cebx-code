<?php

namespace Tests\Feature;

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

class RbacInternalExternalTest extends TestCase
{
    #[Test]
    public function test_internal_permissions_do_not_leak_to_external_realm(): void
    {
        $resolver = app(PermissionResolver::class);

        $account = $this->createAccount();

        $internalUser = $this->createUser([
            'account_id' => null,
            'user_type' => 'internal',
        ]);

        $externalUser = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        $permission = $this->upsertPermission('tenancy.context.select', 'internal');

        $internalRoleId = (string) Str::uuid();
        DB::table('internal_roles')->insert([
            'id' => $internalRoleId,
            'name' => 'realm_internal_role',
            'display_name' => 'Realm Internal Role',
            'description' => 'Test role',
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        DB::table('internal_role_permission')->insert([
            'internal_role_id' => $internalRoleId,
            'permission_id' => $permission->id,
            'granted_at' => now(),
        ]);

        DB::table('internal_user_role')->insert([
            'user_id' => $internalUser->id,
            'internal_role_id' => $internalRoleId,
            'assigned_by' => null,
            'assigned_at' => now(),
        ]);

        $tenantRole = Role::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'name' => 'realm_external_role',
            'display_name' => 'Realm External Role',
            'description' => 'Test role',
            'is_system' => false,
            'template' => null,
        ]);

        DB::table('role_permission')->insert([
            'role_id' => $tenantRole->id,
            'permission_id' => $permission->id,
            'granted_at' => now(),
        ]);

        DB::table('user_role')->insert([
            'user_id' => $externalUser->id,
            'role_id' => $tenantRole->id,
            'assigned_by' => null,
            'assigned_at' => now(),
        ]);

        $this->assertTrue($resolver->can($internalUser, 'tenancy.context.select'));
        $this->assertFalse($resolver->can($externalUser, 'tenancy.context.select'));
    }

    #[Test]
    public function test_external_permissions_do_not_leak_to_internal_realm(): void
    {
        $resolver = app(PermissionResolver::class);

        $account = $this->createAccount();

        $internalUser = $this->createUser([
            'account_id' => null,
            'user_type' => 'internal',
        ]);

        $externalUser = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        $permission = $this->upsertPermission('shipments.create', 'external');

        $tenantRole = Role::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'name' => 'realm_external_only_role',
            'display_name' => 'Realm External Only Role',
            'description' => 'Test role',
            'is_system' => false,
            'template' => null,
        ]);

        DB::table('role_permission')->insert([
            'role_id' => $tenantRole->id,
            'permission_id' => $permission->id,
            'granted_at' => now(),
        ]);

        DB::table('user_role')->insert([
            'user_id' => $externalUser->id,
            'role_id' => $tenantRole->id,
            'assigned_by' => null,
            'assigned_at' => now(),
        ]);

        // Attach same tenant role to internal user intentionally; resolver must still deny.
        DB::table('user_role')->insert([
            'user_id' => $internalUser->id,
            'role_id' => $tenantRole->id,
            'assigned_by' => null,
            'assigned_at' => now(),
        ]);

        $this->assertTrue($resolver->can($externalUser, 'shipments.create'));
        $this->assertFalse($resolver->can($internalUser, 'shipments.create'));
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
