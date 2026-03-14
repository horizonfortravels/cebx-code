<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UuidPhase1PolymorphicAndPivotsCutoverSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_polymorphic_and_pivot_columns_are_uuid_compatible_on_mysql(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('PR5A smoke is MySQL-only.');
        }

        $this->assertPersonalAccessTokensAreUuidCompatibleWhenPresent();
        $this->assertUserRolePivotIsUuidCompatibleAndRelationallyValid();
        $this->assertRolePermissionPivotRemainsUuidCompatibleWhenPresent();
    }

    private function assertPersonalAccessTokensAreUuidCompatibleWhenPresent(): void
    {
        if (!Schema::hasTable('personal_access_tokens')) {
            return;
        }

        $this->assertTrue(Schema::hasColumn('personal_access_tokens', 'tokenable_id'));

        $type = $this->columnType('personal_access_tokens', 'tokenable_id');
        $this->assertNotNull($type);
        $this->assertFalse($this->looksNumericType($type), "personal_access_tokens.tokenable_id should be UUID-compatible, got {$type}");

        $count = DB::table('personal_access_tokens')->count();
        $this->assertGreaterThanOrEqual(0, $count);
    }

    private function assertUserRolePivotIsUuidCompatibleAndRelationallyValid(): void
    {
        $required = [
            ['accounts', 'id'],
            ['users', 'id'],
            ['roles', 'id'],
            ['user_role', 'user_id'],
            ['user_role', 'role_id'],
        ];

        foreach ($required as [$table, $column]) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                $this->markTestSkipped("Required {$table}.{$column} is not available in this environment.");
            }
        }

        $this->assertFalse($this->looksNumericType($this->columnType('user_role', 'user_id') ?? ''));
        $this->assertFalse($this->looksNumericType($this->columnType('user_role', 'role_id') ?? ''));

        $accountId = (string) Str::uuid();
        DB::table('accounts')->insert([
            'id' => $accountId,
            'name' => 'PR5A Smoke Account',
            'email' => 'pr5a-account-'.Str::lower(Str::random(8)).'@example.test',
            'type' => 'organization',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $userId,
            'account_id' => $accountId,
            'name' => 'PR5A Smoke User',
            'email' => 'pr5a-user-'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt('Password1!'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleId = (string) Str::uuid();
        DB::table('roles')->insert([
            'id' => $roleId,
            'account_id' => $accountId,
            'name' => 'pr5a-role-'.Str::lower(Str::random(8)),
            'display_name' => 'PR5A Smoke Role',
            'is_system' => false,
            'template' => null,
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        DB::table('user_role')->insert([
            'user_id' => $userId,
            'role_id' => $roleId,
            'assigned_by' => $userId,
            'assigned_at' => now(),
        ]);

        $storedUserId = (string) DB::table('user_role')
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->value('user_id');

        $this->assertMatchesRegularExpression('/^[0-9a-fA-F-]{36}$/', $storedUserId);
        $this->assertSame($userId, $storedUserId);

        $joinExists = DB::table('user_role as ur')
            ->join('users as u', 'ur.user_id', '=', 'u.id')
            ->join('roles as r', 'ur.role_id', '=', 'r.id')
            ->where('ur.user_id', $userId)
            ->where('ur.role_id', $roleId)
            ->exists();

        $this->assertTrue($joinExists);
    }

    private function assertRolePermissionPivotRemainsUuidCompatibleWhenPresent(): void
    {
        if (!Schema::hasTable('role_permission')) {
            return;
        }

        if (!Schema::hasColumn('role_permission', 'role_id') || !Schema::hasColumn('role_permission', 'permission_id')) {
            return;
        }

        $this->assertFalse($this->looksNumericType($this->columnType('role_permission', 'role_id') ?? ''));
        $this->assertFalse($this->looksNumericType($this->columnType('role_permission', 'permission_id') ?? ''));
    }

    private function columnType(string $table, string $column): ?string
    {
        $row = DB::selectOne(
            <<<'SQL'
            SELECT COLUMN_TYPE AS column_type
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
            SQL,
            [$table, $column]
        );

        if ($row === null || !isset($row->column_type)) {
            return null;
        }

        return strtolower((string) $row->column_type);
    }

    private function looksNumericType(string $columnType): bool
    {
        $columnType = strtolower($columnType);

        foreach (['int', 'decimal', 'double', 'float', 'numeric', 'real'] as $needle) {
            if (str_contains($columnType, $needle)) {
                return true;
            }
        }

        return false;
    }
}
