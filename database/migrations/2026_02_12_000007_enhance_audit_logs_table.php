<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FR-IAM-006 + FR-IAM-013: Comprehensive Audit Log Enhancement
 *
 * Adds:
 * - severity (info/warning/critical) for event classification
 * - category (auth/users/roles/account/invitation/...) for grouping
 * - request_id (correlation ID) for tracing
 * - metadata (additional context beyond old/new values)
 * - Composite indexes for efficient filtering
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs') || ! Schema::hasColumn('audit_logs', 'action')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('audit_logs', 'severity')) {
                $table->string('severity', 20)->default('info')->after('action');
            }
            if (! Schema::hasColumn('audit_logs', 'category')) {
                $table->string('category', 50)->nullable()->after('severity');
            }
            if (Schema::hasColumn('audit_logs', 'user_agent') && ! Schema::hasColumn('audit_logs', 'request_id')) {
                $table->string('request_id', 64)->nullable()->after('user_agent');
            }
            if (Schema::hasColumn('audit_logs', 'new_values') && ! Schema::hasColumn('audit_logs', 'metadata')) {
                $table->json('metadata')->nullable()->after('new_values');
            }
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            if (Schema::hasColumn('audit_logs', 'category')) {
                $table->index(['account_id', 'category', 'created_at'], 'idx_audit_account_category_time');
            }
            if (Schema::hasColumn('audit_logs', 'severity')) {
                $table->index(['account_id', 'severity', 'created_at'], 'idx_audit_account_severity_time');
            }
            $table->index(['account_id', 'user_id', 'created_at'], 'idx_audit_account_user_time');
            if (Schema::hasColumn('audit_logs', 'request_id')) {
                $table->index('request_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_account_category_time');
            $table->dropIndex('idx_audit_account_severity_time');
            $table->dropIndex('idx_audit_account_user_time');
            $table->dropIndex(['request_id']);

            $table->dropColumn(['severity', 'category', 'request_id', 'metadata']);
        });
    }
};
