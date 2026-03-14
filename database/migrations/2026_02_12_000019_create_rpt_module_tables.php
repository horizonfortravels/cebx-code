<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RPT Module — Reports & Analytics
 * FR-RPT-001→010 (10 requirements)
 *
 * Tables:
 *   1. saved_reports      — FR-RPT-001/003: User-saved report configs
 *   2. report_exports     — FR-RPT-002/003: Export history & files
 *   3. scheduled_reports  — FR-RPT-005: Scheduled email reports
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('saved_reports')) {
            return;
        }

        // ═══════════════════════════════════════════════════════════
        // 1. saved_reports — FR-RPT-001/003
        // ═══════════════════════════════════════════════════════════
        Schema::create('saved_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->uuid('user_id');

            $table->string('name', 200);
            $table->enum('report_type', [
                'shipment_summary', 'shipment_detail', 'profit_loss',
                'exception', 'financial', 'operational', 'wallet',
                'carrier_performance', 'store_performance', 'custom',
            ]);

            // ── Filters (FR-RPT-003) ─────────────────────────
            $table->json('filters')->nullable();        // {date_from, date_to, store_id, carrier, status...}
            $table->json('columns')->nullable();         // Selected columns
            $table->string('group_by', 50)->nullable();  // day, week, month, store, carrier
            $table->string('sort_by', 100)->nullable();
            $table->string('sort_direction', 4)->default('desc');

            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_shared')->default(false);

            $table->timestamps();

            $table->index(['account_id', 'user_id']);
            // FKs omitted: accounts.id, users.id may be bigint on server
        });

        // ═══════════════════════════════════════════════════════════
        // 2. report_exports — FR-RPT-002/003
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('report_exports')) {
            Schema::create('report_exports', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('account_id');
                $table->uuid('user_id');

                $table->string('report_type', 100);
                $table->enum('format', ['csv', 'excel', 'json', 'pdf'])->default('csv');

                $table->json('filters')->nullable();
                $table->json('columns')->nullable();

                $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
                $table->string('file_path', 500)->nullable();
                $table->integer('row_count')->nullable();
                $table->integer('file_size')->nullable();
                $table->text('failure_reason')->nullable();

                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['account_id', 'status']);
                // FKs omitted for server compatibility
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 3. scheduled_reports — FR-RPT-005
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('scheduled_reports')) {
            Schema::create('scheduled_reports', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('account_id');
                $table->uuid('user_id');

                $table->string('name', 200);
                $table->string('report_type', 100);
                $table->json('filters')->nullable();
                $table->json('columns')->nullable();

                $table->enum('frequency', ['daily', 'weekly', 'monthly']);
                $table->string('time_of_day', 5)->default('08:00');
                $table->string('day_of_week', 10)->nullable();
                $table->unsignedTinyInteger('day_of_month')->nullable();
                $table->string('timezone', 50)->default('Asia/Riyadh');

                $table->enum('format', ['csv', 'excel', 'pdf'])->default('csv');
                $table->json('recipients')->nullable();       // Email addresses

                $table->boolean('is_active')->default(true);
                $table->timestamp('last_sent_at')->nullable();
                $table->timestamp('next_send_at')->nullable();

                $table->timestamps();

                $table->index(['is_active', 'next_send_at']);
                // FKs omitted for server compatibility
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
        Schema::dropIfExists('report_exports');
        Schema::dropIfExists('saved_reports');
    }
};
