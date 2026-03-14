<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounts')) {
            return;
        }

        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->enum('type', ['individual', 'organization'])->default('individual');
            $table->enum('status', ['active', 'suspended', 'pending', 'closed'])->default('pending');
            $table->string('slug', 100)->unique();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('type');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE accounts ENABLE ROW LEVEL SECURITY;');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE accounts DISABLE ROW LEVEL SECURITY;');
        }
        Schema::dropIfExists('accounts');
    }
};
