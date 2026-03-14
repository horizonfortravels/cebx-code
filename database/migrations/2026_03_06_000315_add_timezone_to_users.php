<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        if (!Schema::hasColumn('users', 'timezone')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('timezone', 64)->default('UTC')->after('locale');
            });
        }

        DB::table('users')
            ->where(function ($query): void {
                $query->whereNull('timezone')
                    ->orWhere('timezone', '')
                    ->orWhereRaw("TRIM(timezone) = ''");
            })
            ->update(['timezone' => 'UTC']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'timezone')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });
    }
};
