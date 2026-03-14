<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dg_audit_logs')) {
            return;
        }

        Schema::table('dg_audit_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('dg_audit_logs', 'account_id')) {
                $table->uuid('account_id')->nullable()->after('action');
            }

            if (! Schema::hasColumn('dg_audit_logs', 'shipment_id')) {
                $table->string('shipment_id', 100)->nullable()->after('declaration_id');
            }

            if (! Schema::hasColumn('dg_audit_logs', 'actor_id')) {
                $table->uuid('actor_id')->nullable()->after('account_id');
            }

            if (! Schema::hasColumn('dg_audit_logs', 'actor_role')) {
                $table->string('actor_role', 100)->nullable()->after('actor_id');
            }

            if (! Schema::hasColumn('dg_audit_logs', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('actor_role');
            }

            if (! Schema::hasColumn('dg_audit_logs', 'old_values')) {
                $table->json('old_values')->nullable()->after('payload');
            }

            if (! Schema::hasColumn('dg_audit_logs', 'new_values')) {
                $table->json('new_values')->nullable()->after('old_values');
            }

            if (! Schema::hasColumn('dg_audit_logs', 'notes')) {
                $table->text('notes')->nullable()->after('new_values');
            }
        });

        Schema::table('dg_audit_logs', function (Blueprint $table) {
            $table->index(['account_id', 'created_at'], 'dg_audit_logs_account_created_idx');
            $table->index(['shipment_id', 'created_at'], 'dg_audit_logs_shipment_created_idx');
            $table->index(['action', 'created_at'], 'dg_audit_logs_action_created_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dg_audit_logs')) {
            return;
        }

        Schema::table('dg_audit_logs', function (Blueprint $table) {
            if (Schema::hasColumn('dg_audit_logs', 'account_id')) {
                $table->dropIndex('dg_audit_logs_account_created_idx');
            }

            if (Schema::hasColumn('dg_audit_logs', 'shipment_id')) {
                $table->dropIndex('dg_audit_logs_shipment_created_idx');
            }

            if (Schema::hasColumn('dg_audit_logs', 'action')) {
                $table->dropIndex('dg_audit_logs_action_created_idx');
            }
        });
    }
};
