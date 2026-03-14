<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PermissionKeyNormalizationTest extends TestCase
{
    public function test_migration_normalizes_colon_keys_repoints_pivots_and_removes_duplicates(): void
    {
        if (!Schema::hasTable('permissions')) {
            $this->markTestSkipped('RBAC tables are not available in this environment.');
        }

        $colonKey = 'phase2b2:example.manage';
        $dotKey = 'phase2b2.example.manage';
        $colonPermissionId = (string) Str::uuid();
        $dotPermissionId = (string) Str::uuid();

        $colonPermissionPayload = [
            'id' => $colonPermissionId,
            'key' => $colonKey,
            'group' => 'phase2b2',
            'display_name' => 'Legacy colon permission',
            'description' => 'legacy',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $dotPermissionPayload = [
            'id' => $dotPermissionId,
            'key' => $dotKey,
            'group' => 'phase2b2',
            'display_name' => 'Canonical dot permission',
            'description' => 'canonical',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('permissions', 'audience')) {
            $colonPermissionPayload['audience'] = 'both';
            $dotPermissionPayload['audience'] = 'both';
        }

        DB::table('permissions')->insert([$colonPermissionPayload, $dotPermissionPayload]);

        $internalRoleId = null;
        if (Schema::hasTable('internal_roles') && Schema::hasTable('internal_role_permission')) {
            $internalRoleId = (string) Str::uuid();
            DB::table('internal_roles')->insert([
                'id' => $internalRoleId,
                'name' => 'phase2b2_norm_role_'.Str::lower(Str::random(8)),
                'display_name' => 'Phase2B2 Norm Role',
                'description' => 'test',
                'is_system' => false,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);

            DB::table('internal_role_permission')->insert([
                'internal_role_id' => $internalRoleId,
                'permission_id' => $colonPermissionId,
                'granted_at' => now(),
            ]);
        }

        $migration = require database_path('migrations/2026_03_06_000221_phase2b2_normalize_permission_keys_to_dot_notation.php');
        $migration->up();

        $this->assertSame(0, DB::table('permissions')->where('key', $colonKey)->count());
        $this->assertSame(1, DB::table('permissions')->where('key', $dotKey)->count());

        $canonicalId = (string) DB::table('permissions')->where('key', $dotKey)->value('id');
        $this->assertSame($dotPermissionId, $canonicalId);

        if ($internalRoleId) {
            $this->assertTrue(DB::table('internal_role_permission')
                ->where('internal_role_id', $internalRoleId)
                ->where('permission_id', $dotPermissionId)
                ->exists());

            $this->assertFalse(DB::table('internal_role_permission')
                ->where('internal_role_id', $internalRoleId)
                ->where('permission_id', $colonPermissionId)
                ->exists());
        }
    }
}
