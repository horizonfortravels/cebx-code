<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('accounts') || !Schema::hasColumn('accounts', 'type')) {
            return;
        }

        DB::table('accounts')
            ->where('type', 'admin')
            ->update(['type' => 'organization']);
    }

    public function down(): void
    {
        // Intentional no-op for pre-launch normalization migration.
    }
};
