<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'user_type')) {
            return;
        }

        if (Schema::hasColumn('users', 'account_id')) {
            DB::table('users')
                ->whereNotNull('account_id')
                ->update(['user_type' => 'external']);

            DB::table('users')
                ->whereNull('account_id')
                ->update(['user_type' => 'internal']);
        } else {
            DB::table('users')->update(['user_type' => 'internal']);
        }

        if (Schema::hasColumn('users', 'is_super_admin')) {
            DB::table('users')
                ->where('is_super_admin', true)
                ->update(['user_type' => 'internal']);
        }

        if (Schema::hasColumn('users', 'role')) {
            DB::table('users')
                ->whereIn('role', ['admin', 'super-admin', 'super_admin'])
                ->update(['user_type' => 'internal']);
        }

        if (Schema::hasColumn('users', 'role_name')) {
            DB::table('users')
                ->whereIn(DB::raw('LOWER(role_name)'), ['admin', 'super admin', 'platform admin'])
                ->update(['user_type' => 'internal']);
        }
    }

    public function down(): void
    {
        // Forward-only data backfill.
    }
};
