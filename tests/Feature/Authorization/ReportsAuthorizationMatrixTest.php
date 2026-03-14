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

class ReportsAuthorizationMatrixTest extends TestCase
{
    #[Test]
    public function same_tenant_with_reports_read_gets_2xx_on_a_get_report_endpoint(): void
    {
        $this->skipIfMissingTables(['saved_reports']);

        $account = $this->createAccount();
        $user = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        $this->grantExternalPermissions((string) $user->id, (string) $account->id, ['reports.read']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/saved')
            ->assertOk();
    }

    #[Test]
    public function same_tenant_missing_reports_read_gets_403(): void
    {
        $this->skipIfMissingTables(['saved_reports']);

        $account = $this->createAccount();
        $user = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/saved')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');
    }

    #[Test]
    public function export_endpoint_requires_reports_export_permission(): void
    {
        $this->skipIfMissingTables(['report_exports']);

        $account = $this->createAccount();
        $user = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        $payload = [
            'report_type' => 'shipment_summary',
            'format' => 'csv',
            'filters' => [],
            'columns' => [],
        ];

        $this->grantExternalPermissions((string) $user->id, (string) $account->id, ['reports.read']);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/reports/export', $payload)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');

        $this->grantExternalPermissions((string) $user->id, (string) $account->id, [
            'reports.read',
            'reports.export',
        ]);

        $this->postJson('/api/v1/reports/export', $payload)
            ->assertStatus(201)
            ->assertJsonPath('status', 'success');
    }

    #[Test]
    public function cross_tenant_schedule_id_access_returns_404(): void
    {
        $this->skipIfMissingTables(['scheduled_reports']);

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

        $this->grantExternalPermissions((string) $userA->id, (string) $accountA->id, ['reports.manage']);

        $scheduleId = $this->createScheduledReport((string) $accountB->id, (string) $userB->id);

        Sanctum::actingAs($userA);

        $this->deleteJson('/api/v1/reports/schedules/' . $scheduleId)
            ->assertNotFound();
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
            'name' => 'report_matrix_' . Str::random(8),
            'display_name' => 'Reports Matrix Role',
            'description' => 'Reports authorization matrix role',
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

    private function createScheduledReport(string $accountId, string $userId): string
    {
        $id = (string) Str::uuid();

        $payload = array_filter([
            'id' => $id,
            'account_id' => $accountId,
            'user_id' => $userId,
            'name' => Schema::hasColumn('scheduled_reports', 'name') ? 'Cross tenant schedule' : null,
            'report_type' => Schema::hasColumn('scheduled_reports', 'report_type') ? 'shipment_summary' : null,
            'filters' => Schema::hasColumn('scheduled_reports', 'filters') ? json_encode([]) : null,
            'columns' => Schema::hasColumn('scheduled_reports', 'columns') ? json_encode([]) : null,
            'frequency' => Schema::hasColumn('scheduled_reports', 'frequency') ? 'daily' : null,
            'time_of_day' => Schema::hasColumn('scheduled_reports', 'time_of_day') ? '08:00' : null,
            'day_of_week' => Schema::hasColumn('scheduled_reports', 'day_of_week') ? null : null,
            'day_of_month' => Schema::hasColumn('scheduled_reports', 'day_of_month') ? null : null,
            'timezone' => Schema::hasColumn('scheduled_reports', 'timezone') ? 'UTC' : null,
            'format' => Schema::hasColumn('scheduled_reports', 'format') ? 'csv' : null,
            'recipients' => Schema::hasColumn('scheduled_reports', 'recipients') ? json_encode(['ops@example.test']) : null,
            'is_active' => Schema::hasColumn('scheduled_reports', 'is_active') ? true : null,
            'last_sent_at' => Schema::hasColumn('scheduled_reports', 'last_sent_at') ? null : null,
            'next_send_at' => Schema::hasColumn('scheduled_reports', 'next_send_at') ? now()->addDay() : null,
            'created_at' => Schema::hasColumn('scheduled_reports', 'created_at') ? now() : null,
            'updated_at' => Schema::hasColumn('scheduled_reports', 'updated_at') ? now() : null,
        ], static fn ($value): bool => $value !== null);

        DB::table('scheduled_reports')->insert($payload);

        return $id;
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

