<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const COMPOSITE_INDEX = 'roles_account_id_slug_unique';
    private const SINGLE_INDEX = 'roles_slug_unique';

    public function up(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        if (!Schema::hasColumn('roles', 'slug')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->string('slug', 191)->nullable()->after('name');
            });
        }

        $this->backfillSlugValues();
        $this->ensureUniqueSlugIndex();
    }

    public function down(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        $this->dropIndexIfExists(self::COMPOSITE_INDEX);
        $this->dropIndexIfExists(self::SINGLE_INDEX);

        if (Schema::hasColumn('roles', 'slug')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->dropColumn('slug');
            });
        }
    }

    private function backfillSlugValues(): void
    {
        if (!Schema::hasColumn('roles', 'slug')) {
            return;
        }

        $hasAccountId = Schema::hasColumn('roles', 'account_id');
        $columns = ['id', 'name', 'slug'];

        if ($hasAccountId) {
            $columns[] = 'account_id';
        }

        $rows = DB::table('roles')->select($columns)->orderBy('id')->get();
        $usedByScope = [];

        foreach ($rows as $row) {
            $scope = $this->scopeKey($row, $hasAccountId);
            $existing = trim((string) ($row->slug ?? ''));

            if ($existing !== '') {
                $usedByScope[$scope][mb_strtolower($existing)] = true;
            }
        }

        foreach ($rows as $row) {
            $current = trim((string) ($row->slug ?? ''));
            if ($current !== '') {
                continue;
            }

            $scope = $this->scopeKey($row, $hasAccountId);
            $base = Str::slug((string) ($row->name ?? ''));
            if ($base === '') {
                $base = 'role';
            }

            $candidate = Str::limit($base, 191, '');

            while (isset($usedByScope[$scope][mb_strtolower($candidate)])) {
                $suffix = '-' . Str::lower(Str::random(4));
                $candidate = Str::limit($base, 191 - strlen($suffix), '') . $suffix;
            }

            DB::table('roles')
                ->where('id', $row->id)
                ->update(['slug' => $candidate]);

            $usedByScope[$scope][mb_strtolower($candidate)] = true;
        }
    }

    private function ensureUniqueSlugIndex(): void
    {
        if (!Schema::hasColumn('roles', 'slug')) {
            return;
        }

        if (Schema::hasColumn('roles', 'account_id')) {
            if ($this->hasIndex(self::COMPOSITE_INDEX)) {
                return;
            }

            Schema::table('roles', function (Blueprint $table): void {
                $table->unique(['account_id', 'slug'], self::COMPOSITE_INDEX);
            });

            return;
        }

        if ($this->hasIndex(self::SINGLE_INDEX)) {
            return;
        }

        Schema::table('roles', function (Blueprint $table): void {
            $table->unique('slug', self::SINGLE_INDEX);
        });
    }

    private function hasIndex(string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $rows = DB::select("SHOW INDEX FROM `roles` WHERE Key_name = ?", [$indexName]);
            return count($rows) > 0;
        }

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('roles')");
            foreach ($rows as $row) {
                if (($row->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    private function dropIndexIfExists(string $indexName): void
    {
        if (!$this->hasIndex($indexName)) {
            return;
        }

        Schema::table('roles', function (Blueprint $table) use ($indexName): void {
            $table->dropUnique($indexName);
        });
    }

    private function scopeKey(object $row, bool $hasAccountId): string
    {
        if (!$hasAccountId) {
            return '__global__';
        }

        return (string) ($row->account_id ?? '__null__');
    }
};

