<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasColumn('permissions', 'key')) {
            return;
        }

        $rows = DB::table('permissions')
            ->select(['id', 'key'])
            ->orderBy('id')
            ->get();

        $groups = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row->key ?? ''));
            if ($key === '') {
                continue;
            }

            $normalized = str_replace(':', '.', $key);
            $groups[$normalized][] = [
                'id' => (string) $row->id,
                'key' => $key,
            ];
        }

        foreach ($groups as $normalized => $items) {
            $colonRows = array_values(array_filter(
                $items,
                static fn (array $item): bool => $item['key'] !== $normalized
            ));

            if ($colonRows === []) {
                continue;
            }

            $dotRows = array_values(array_filter(
                $items,
                static fn (array $item): bool => $item['key'] === $normalized
            ));

            $canonical = $dotRows[0] ?? $items[0];
            $canonicalId = $canonical['id'];

            if ($canonical['key'] !== $normalized) {
                DB::table('permissions')
                    ->where('id', $canonicalId)
                    ->update(['key' => $normalized]);
            }

            foreach ($items as $item) {
                if ($item['id'] === $canonicalId) {
                    continue;
                }

                $this->remapPermissionPivot('role_permission', 'role_id', 'permission_id', $item['id'], $canonicalId);
                $this->remapPermissionPivot('internal_role_permission', 'internal_role_id', 'permission_id', $item['id'], $canonicalId);

                DB::table('permissions')->where('id', $item['id'])->delete();
            }
        }

        $remainingCount = DB::table('permissions')
            ->where('key', 'like', '%:%')
            ->count();

        if ($remainingCount > 0) {
            $samples = DB::table('permissions')
                ->where('key', 'like', '%:%')
                ->orderBy('key')
                ->limit(10)
                ->pluck('key')
                ->map(static fn (string $key): string => $key)
                ->all();

            throw new RuntimeException(
                sprintf(
                    'Phase 2B2 permission normalization failed: %d colon-style key(s) remain. Samples: %s',
                    $remainingCount,
                    implode(', ', $samples)
                )
            );
        }
    }

    public function down(): void
    {
        // Forward-only cutover.
    }

    private function remapPermissionPivot(
        string $table,
        string $roleColumn,
        string $permissionColumn,
        string $oldPermissionId,
        string $canonicalPermissionId
    ): void {
        if (
            !Schema::hasTable($table) ||
            !Schema::hasColumn($table, $roleColumn) ||
            !Schema::hasColumn($table, $permissionColumn)
        ) {
            return;
        }

        $rows = DB::table($table)
            ->where($permissionColumn, $oldPermissionId)
            ->get([$roleColumn, 'granted_at']);

        foreach ($rows as $row) {
            $roleId = (string) $row->{$roleColumn};

            $exists = DB::table($table)
                ->where($roleColumn, $roleId)
                ->where($permissionColumn, $canonicalPermissionId)
                ->exists();

            if (!$exists) {
                DB::table($table)->insert([
                    $roleColumn => $roleId,
                    $permissionColumn => $canonicalPermissionId,
                    'granted_at' => $row->granted_at ?? now(),
                ]);
            }
        }

        DB::table($table)
            ->where($permissionColumn, $oldPermissionId)
            ->delete();
    }
};
