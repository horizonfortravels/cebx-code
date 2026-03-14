<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('permissions')) {
            return;
        }

        // ── Permissions Catalog (system-wide, not tenant-scoped) ──
        if (! Schema::hasTable('permissions')) {
        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key', 100)->unique();         // e.g. "users:manage", "shipments:create"
            $table->string('group', 50);                   // e.g. "users", "shipments", "financial"
            $table->string('display_name', 150);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('group');
        });
        }

        // ── Roles (tenant-scoped) ─────────────────────────────────
        if (! Schema::hasTable('roles')) {
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->string('name', 100);
            $table->string('display_name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false)->comment('System roles cannot be deleted');
            $table->string('template', 50)->nullable()->comment('Template used: owner, admin, accountant, warehouse');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['account_id', 'name']);
            $table->index('account_id');
            // FK omitted: accounts.id may be bigint (0001 migration) or uuid (2026 migration); types must match
        });
        }

        // ── Role ↔ Permission pivot ───────────────────────────────
        if (! Schema::hasTable('role_permission')) {
        Schema::create('role_permission', function (Blueprint $table) {
            $table->uuid('role_id');
            $table->uuid('permission_id');
            $table->timestamp('granted_at')->useCurrent();

            $table->primary(['role_id', 'permission_id']);

            $table->foreign('role_id')
                  ->references('id')
                  ->on('roles')
                  ->onDelete('cascade');

            $table->foreign('permission_id')
                  ->references('id')
                  ->on('permissions')
                  ->onDelete('cascade');
        });
        }

        // ── User ↔ Role pivot ─────────────────────────────────────
        if (! Schema::hasTable('user_role')) {
        Schema::create('user_role', function (Blueprint $table) {
            $table->uuid('user_id');
            $table->uuid('role_id');
            $table->uuid('assigned_by')->nullable();
            $table->timestamp('assigned_at')->useCurrent();

            $table->primary(['user_id', 'role_id']);
            $table->index('user_id');
            $table->index('role_id');
            $table->index('assigned_by');
            // FKs to users/roles omitted: users.id may be bigint or uuid depending on which migration created the table
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role');
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
