<?php

namespace Tests\Feature\Authorization;

use App\Models\Account;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UserRoleAuthorizationMatrixTest extends TestCase
{
    #[Test]
    public function test_external_same_tenant_with_permissions_can_view_users_roles_and_assign_revoke_role(): void
    {
        if (!$this->hasRequiredTables(['users', 'roles', 'permissions', 'role_permission', 'user_role'])) {
            $this->markTestSkipped('Required RBAC tables are not available in this environment.');
        }

        $this->ensureAuditLogsAccountIdColumn();

        $account = $this->createAccount();
        $actor = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);
        $targetUser = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        $managedRoleId = $this->createRole((string) $account->id);
        $this->grantExternalPermissions((string) $actor->id, (string) $account->id, [
            'users.read',
            'users.manage',
            'roles.read',
            'roles.manage',
            'roles.assign',
        ]);

        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/users/' . $targetUser->id)->assertOk();
        $this->getJson('/api/v1/roles/' . $managedRoleId)->assertOk();

        $this->postJson('/api/v1/roles/' . $managedRoleId . '/assign/' . $targetUser->id)->assertOk();
        $this->deleteJson('/api/v1/roles/' . $managedRoleId . '/revoke/' . $targetUser->id)->assertOk();
    }

    #[Test]
    public function test_external_same_tenant_without_permission_gets_403(): void
    {
        $account = $this->createAccount();
        $actor = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);
        $targetUser = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        Sanctum::actingAs($actor);

        $this->getJson('/api/v1/users/' . $targetUser->id)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');
    }

    #[Test]
    public function test_external_cross_tenant_requests_return_404_first(): void
    {
        if (!$this->hasRequiredTables(['users', 'roles', 'permissions', 'role_permission', 'user_role'])) {
            $this->markTestSkipped('Required RBAC tables are not available in this environment.');
        }

        $accountA = $this->createAccount();
        $accountB = $this->createAccount();

        $actorB = $this->createUser([
            'account_id' => (string) $accountB->id,
            'user_type' => 'external',
        ]);

        $userA = $this->createUser([
            'account_id' => (string) $accountA->id,
            'user_type' => 'external',
        ]);
        $roleA = $this->createRole((string) $accountA->id);

        $this->grantExternalPermissions((string) $actorB->id, (string) $accountB->id, [
            'users.read',
            'users.manage',
            'roles.read',
            'roles.manage',
            'roles.assign',
        ]);

        Sanctum::actingAs($actorB);

        $this->getJson('/api/v1/users/' . $userA->id)->assertNotFound();
        $this->getJson('/api/v1/roles/' . $roleA)->assertNotFound();
        $this->postJson('/api/v1/roles/' . $roleA . '/assign/' . $userA->id)->assertNotFound();
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

    private function ensureAuditLogsAccountIdColumn(): void
    {
        if (!Schema::hasTable('audit_logs')) {
            $this->createMinimalAuditLogsTable();
            return;
        }

        $this->ensureSqliteCompatibleAuditLogsTable();

        $missing = [];
        foreach ([
            'account_id',
            'user_id',
            'event',
            'action',
            'entity_type',
            'entity_id',
            'old_values',
            'new_values',
            'ip_address',
            'user_agent',
            'created_at',
            'updated_at',
        ] as $column) {
            if (!Schema::hasColumn('audit_logs', $column)) {
                $missing[] = $column;
            }
        }

        if ($missing === []) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) use ($missing): void {
            foreach ($missing as $column) {
                switch ($column) {
                    case 'account_id':
                    case 'user_id':
                    case 'action':
                    case 'entity_type':
                    case 'entity_id':
                    case 'ip_address':
                        $table->string($column)->nullable();
                        break;
                    case 'old_values':
                    case 'new_values':
                    case 'user_agent':
                        $table->text($column)->nullable();
                        break;
                    case 'created_at':
                    case 'updated_at':
                        $table->timestamp($column)->nullable();
                        break;
                }
            }
        });
    }

    private function ensureSqliteCompatibleAuditLogsTable(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        if (!Schema::hasTable('audit_logs') || !Schema::hasColumn('audit_logs', 'event')) {
            return;
        }

        $temporaryTable = 'audit_logs_sqlite_compat_tmp';
        Schema::dropIfExists($temporaryTable);

        Schema::create($temporaryTable, function (Blueprint $table): void {
            $table->increments('id');
            $table->string('account_id')->nullable();
            $table->string('user_id')->nullable();
            $table->string('event')->nullable();
            $table->string('action')->nullable();
            $table->string('entity_type')->nullable();
            $table->string('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        $copyableColumns = [];
        foreach ([
            'account_id',
            'user_id',
            'event',
            'action',
            'entity_type',
            'entity_id',
            'old_values',
            'new_values',
            'ip_address',
            'user_agent',
            'created_at',
            'updated_at',
        ] as $column) {
            if (Schema::hasColumn('audit_logs', $column)) {
                $copyableColumns[] = $column;
            }
        }

        if ($copyableColumns !== []) {
            DB::table($temporaryTable)->insertUsing(
                $copyableColumns,
                DB::table('audit_logs')->select($copyableColumns)
            );
        }

        Schema::drop('audit_logs');
        Schema::rename($temporaryTable, 'audit_logs');
    }

    private function createMinimalAuditLogsTable(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('account_id')->nullable();
            $table->string('user_id')->nullable();
            $table->string('event')->nullable();
            $table->string('action')->nullable();
            $table->string('entity_type')->nullable();
            $table->string('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * @param array<int, string> $permissions
     */
    private function grantExternalPermissions(string $userId, string $accountId, array $permissions): void
    {
        $roleId = $this->createRole($accountId);

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

    private function createRole(string $accountId): string
    {
        $payload = [
            'name' => 'role_' . Str::lower(Str::random(8)),
        ];

        if (Schema::hasColumn('roles', 'account_id')) {
            $payload['account_id'] = $accountId;
        }
        if (Schema::hasColumn('roles', 'display_name')) {
            $payload['display_name'] = 'Role ' . Str::random(4);
        }
        if (Schema::hasColumn('roles', 'description')) {
            $payload['description'] = 'Matrix role';
        }
        if (Schema::hasColumn('roles', 'is_system')) {
            $payload['is_system'] = false;
        }
        if (Schema::hasColumn('roles', 'template')) {
            $payload['template'] = null;
        }
        if (Schema::hasColumn('roles', 'created_at')) {
            $payload['created_at'] = now();
        }
        if (Schema::hasColumn('roles', 'updated_at')) {
            $payload['updated_at'] = now();
        }
        if (Schema::hasColumn('roles', 'deleted_at')) {
            $payload['deleted_at'] = null;
        }

        $roleId = $this->insertRowAndReturnId('roles', $payload);

        return (string) $roleId;
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
