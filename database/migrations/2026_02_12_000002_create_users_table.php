<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            return;
        }

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->string('name', 150);
            $table->string('email', 255);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone', 20)->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->boolean('is_owner')->default(false)->comment('Account owner flag');
            $table->string('locale', 10)->default('en');
            $table->string('timezone', 50)->default('UTC');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Composite unique: same email can exist in different accounts
            $table->unique(['account_id', 'email']);
            $table->index('email');
            $table->index('status');

            $table->foreign('account_id')
                  ->references('id')
                  ->on('accounts')
                  ->onDelete('cascade');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ENABLE ROW LEVEL SECURITY;');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DISABLE ROW LEVEL SECURITY;');
        }
        Schema::dropIfExists('users');
    }
};
