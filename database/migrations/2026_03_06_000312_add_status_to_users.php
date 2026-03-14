<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATUS_INDEX = 'users_status_index';

    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        if (!Schema::hasColumn('users', 'status')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('status', 32)->default('active');
                $table->index('status', self::STATUS_INDEX);
            });
        } elseif (!$this->hasIndex(self::STATUS_INDEX)) {
            Schema::table('users', function (Blueprint $table): void {
                $table->index('status', self::STATUS_INDEX);
            });
        }

        DB::table('users')
            ->where(function ($query): void {
                $query->whereNull('status')
                    ->orWhereRaw("TRIM(status) = ''");
            })
            ->update(['status' => 'active']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'status')) {
            return;
        }

        if ($this->hasIndex(self::STATUS_INDEX)) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex(self::STATUS_INDEX);
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('status');
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

