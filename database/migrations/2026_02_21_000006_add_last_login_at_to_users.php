<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * عمود last_login_at مطلوب من AuthWebController و User model ($casts).
 * جدول users (2026_02_12_000002) لا يتضمنه.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'last_login_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_login_at');
        });
    }
};
