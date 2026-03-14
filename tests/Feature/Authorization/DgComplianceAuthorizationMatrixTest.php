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

class DgComplianceAuthorizationMatrixTest extends TestCase
{
    #[Test]
    public function external_same_tenant_with_permission_gets_2xx(): void
    {
        $this->skipIfMissingTables(['content_declarations']);

        $account = $this->createAccount();
        $user = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        $this->grantExternalPermissions((string) $user->id, (string) $account->id, ['dg.read']);

        $declarationId = $this->createDeclaration((string) $account->id, (string) $user->id);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/dg/declarations/' . $declarationId . '/hold-info')
            ->assertOk();
    }

    #[Test]
    public function external_same_tenant_missing_permission_gets_403(): void
    {
        $this->skipIfMissingTables(['content_declarations']);

        $account = $this->createAccount();
        $user = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        $declarationId = $this->createDeclaration((string) $account->id, (string) $user->id);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/dg/declarations/' . $declarationId . '/hold-info')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');
    }

    #[Test]
    public function external_cross_tenant_declaration_returns_404_even_with_permission(): void
    {
        $this->skipIfMissingTables(['content_declarations']);

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

        $this->grantExternalPermissions((string) $userA->id, (string) $accountA->id, ['dg.read']);

        $declarationId = $this->createDeclaration((string) $accountB->id, (string) $userB->id);

        Sanctum::actingAs($userA);

        $this->getJson('/api/v1/dg/declarations/' . $declarationId . '/hold-info')
            ->assertNotFound();
    }

    #[Test]
    public function dg_manage_endpoints_require_manage_permission(): void
    {
        $this->skipIfMissingTables(['content_declarations']);

        $account = $this->createAccount();
        $user = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        $this->grantExternalPermissions((string) $user->id, (string) $account->id, ['dg.read']);

        $declarationId = $this->createDeclaration((string) $account->id, (string) $user->id);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/dg/declarations/' . $declarationId . '/metadata', [
            'un_number' => 'UN3480',
            'dg_class' => '9',
        ])->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');
    }

    /**
     * @param array<int, string> $tables
     */
    private function skipIfMissingTables(array $tables): void
    {
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped(sprintf('%s table is not available in this environment.', $table));
            }
        }
    }

    /**
     * @param array<int, string> $permissions
     */
    private function grantExternalPermissions(string $userId, string $accountId, array $permissions): void
    {
        $roleId = (string) Str::uuid();

        $rolePayload = [
            'id' => $roleId,
            'account_id' => $accountId,
            'name' => 'dg_matrix_' . Str::random(8),
            'display_name' => 'DG Matrix Role',
            'description' => 'DG authorization matrix role',
            'is_system' => false,
            'template' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('roles', 'slug')) {
            $rolePayload['slug'] = Str::slug($rolePayload['name']);
        }
        if (Schema::hasColumn('roles', 'deleted_at')) {
            $rolePayload['deleted_at'] = null;
        }

        DB::table('roles')->insert($rolePayload);

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

    private function createDeclaration(string $accountId, string $userId): string
    {
        $declarationId = (string) Str::uuid();

        DB::table('content_declarations')->insert(array_filter([
            'id' => $declarationId,
            'account_id' => $accountId,
            'shipment_id' => 'SHIP-' . strtoupper(Str::random(10)),
            'contains_dangerous_goods' => false,
            'status' => 'completed',
            'hold_reason' => null,
            'waiver_accepted' => true,
            'waiver_version_id' => Schema::hasColumn('content_declarations', 'waiver_version_id') ? null : null,
            'waiver_hash_snapshot' => Schema::hasColumn('content_declarations', 'waiver_hash_snapshot') ? hash('sha256', (string) Str::uuid()) : null,
            'waiver_text_snapshot' => Schema::hasColumn('content_declarations', 'waiver_text_snapshot') ? 'Waiver text' : null,
            'waiver_accepted_at' => Schema::hasColumn('content_declarations', 'waiver_accepted_at') ? now() : null,
            'declared_by' => $userId,
            'ip_address' => Schema::hasColumn('content_declarations', 'ip_address') ? '127.0.0.1' : null,
            'user_agent' => Schema::hasColumn('content_declarations', 'user_agent') ? 'phpunit' : null,
            'locale' => Schema::hasColumn('content_declarations', 'locale') ? 'en' : null,
            'declared_at' => Schema::hasColumn('content_declarations', 'declared_at') ? now() : null,
            'created_at' => Schema::hasColumn('content_declarations', 'created_at') ? now() : null,
            'updated_at' => Schema::hasColumn('content_declarations', 'updated_at') ? now() : null,
        ], static fn ($value): bool => $value !== null));

        return $declarationId;
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
