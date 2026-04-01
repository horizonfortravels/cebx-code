<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('accounts') || Schema::hasColumn('accounts', 'settings')) {
            return;
        }

        Schema::table('accounts', function (Blueprint $table): void {
            $table->json('settings')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('accounts') || !Schema::hasColumn('accounts', 'settings')) {
            return;
        }

        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropColumn('settings');
        });
    }
};
