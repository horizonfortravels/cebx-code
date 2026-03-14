<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OWNER_INDEX = 'users_is_owner_index';

    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        if (!Schema::hasColumn('users', 'is_owner')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('is_owner')->default(false);
                $table->index('is_owner', self::OWNER_INDEX);
            });
        } elseif (!$this->hasIndex(self::OWNER_INDEX)) {
            Schema::table('users', function (Blueprint $table): void {
                $table->index('is_owner', self::OWNER_INDEX);
            });
        }

        DB::table('users')
            ->whereNull('is_owner')
            ->update(['is_owner' => false]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'is_owner')) {
            return;
        }

        if ($this->hasIndex(self::OWNER_INDEX)) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex(self::OWNER_INDEX);
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_owner');
        });
    }

    private function hasIndex(string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $rows = DB::select('SHOW INDEX FROM `users` WHERE Key_name = ?', [$indexName]);
            return count($rows) > 0;
        }

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('users')");
            foreach ($rows as $row) {
                if (($row->name ?? null) === $indexName) {
                    return true;
                }
            }
        }

        return false;
    }
};

