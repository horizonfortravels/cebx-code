<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users') || Schema::hasColumn('users', 'email_verified_at')) {
            return;
        }

        $hasEmailColumn = Schema::hasColumn('users', 'email');

        Schema::table('users', function (Blueprint $table) use ($hasEmailColumn): void {
            $column = $table->timestamp('email_verified_at')->nullable();

            if ($hasEmailColumn) {
                $column->after('email');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'email_verified_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('email_verified_at');
        });
    }
};

