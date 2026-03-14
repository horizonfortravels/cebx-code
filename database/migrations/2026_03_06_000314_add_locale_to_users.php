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

        if (!Schema::hasColumn('users', 'locale')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('locale', 10)->default('en')->after('is_owner');
            });
        }

        DB::table('users')
            ->where(function ($query): void {
                $query->whereNull('locale')
                    ->orWhere('locale', '');
            })
            ->update(['locale' => 'en']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'locale')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
