<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('accounts', 'slug')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->string('slug', 100)->unique()->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('accounts', 'slug')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->dropColumn('slug');
            });
        }
    }
};
