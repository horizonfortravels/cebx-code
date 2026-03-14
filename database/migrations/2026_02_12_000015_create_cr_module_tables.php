<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CR Module — Carrier Integration & Labels
 * FR-CR-001→008 (8 requirements)
 *
 * Tables:
 *   1. carrier_shipments  — FR-CR-001/003/004/006: Carrier-side shipment records
 *   2. carrier_documents  — FR-CR-002/005/007/008: Labels & documents storage
 *   3. carrier_errors     — FR-CR-004: Normalized error log
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('carrier_shipments')) {
            return;
        }

        // ═══════════════════════════════════════════════════════════════
        // 1. carrier_shipments — FR-CR-001/003/006
        //    Records from the carrier side (DHL) after shipment creation
        // ═══════════════════════════════════════════════════════════════
        Schema::create('carrier_shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('shipment_id');
            $table->uuid('account_id');

            // ── Carrier Identification ───────────────────────────
            $table->string('carrier_code', 50)->default('dhl');
            $table->string('carrier_name', 100)->default('DHL Express');

            // ── Carrier References (FR-CR-001) ───────────────────
            $table->string('carrier_shipment_id', 100)->nullable();
            $table->string('tracking_number', 100)->nullable()->index();
            $table->string('awb_number', 50)->nullable();
            $table->string('dispatch_confirmation_number', 100)->nullable();

            // ── Service Details ──────────────────────────────────
            $table->string('service_code', 50)->nullable();
            $table->string('service_name', 200)->nullable();
            $table->string('product_code', 50)->nullable();

            // ── Status ──────────────────────────────────────────
            $table->enum('status', [
                'pending',           // Before API call
                'creating',          // API call in progress
                'created',           // Successfully created at carrier
                'label_pending',     // Created but label not yet received
                'label_ready',       // Label available
                'cancel_pending',    // Cancel request sent
                'cancelled',         // Confirmed cancelled at carrier
                'cancel_failed',     // Cancel request rejected
                'failed',            // Creation failed
            ])->default('pending');

            // ── Idempotency (FR-CR-003) ─────────────────────────
            $table->string('idempotency_key', 100)->unique();
            $table->integer('attempt_count')->default(0);
            $table->timestamp('last_attempt_at')->nullable();

            // ── Label Format (FR-CR-007) ─────────────────────────
            $table->enum('label_format', ['pdf', 'zpl', 'png', 'epl'])->default('pdf');
            $table->enum('label_size', ['4x6', '4x8', 'A4', 'A5'])->default('4x6');

            // ── Cancellation (FR-CR-006) ─────────────────────────
            $table->boolean('is_cancellable')->default(true);
            $table->timestamp('cancellation_deadline')->nullable();
            $table->string('cancellation_id', 100)->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // ── Carrier Raw Data ─────────────────────────────────
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->json('carrier_metadata')->nullable();

            // ── Correlation ──────────────────────────────────────
            $table->string('correlation_id', 64)->index();

            $table->timestamps();

            $table->index(['shipment_id', 'status']);
            $table->index(['carrier_code', 'tracking_number']);
            $table->index(['account_id', 'status']);
            // FKs omitted: shipments.id, accounts.id may be bigint on server
        });

        // ═══════════════════════════════════════════════════════════════
        // 2. carrier_documents — FR-CR-002/005/007/008
        //    Stored labels and shipping documents
        // ═══════════════════════════════════════════════════════════════
        if (! Schema::hasTable('carrier_documents')) {
        Schema::create('carrier_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('carrier_shipment_id');
            $table->uuid('shipment_id');

            // ── Document Type ────────────────────────────────────
            $table->enum('type', [
                'label',                // Shipping label
                'commercial_invoice',   // CI for international
                'customs_declaration',  // CN23/CN22
                'waybill',              // Air waybill
                'receipt',              // Shipment receipt
                'return_label',         // Return label
                'other',
            ]);

            // ── Format (FR-CR-007) ───────────────────────────────
            $table->enum('format', ['pdf', 'zpl', 'png', 'epl', 'html'])->default('pdf');
            $table->string('mime_type', 100)->default('application/pdf');

            // ── Storage ──────────────────────────────────────────
            $table->string('storage_path', 500)->nullable();
            $table->string('storage_disk', 50)->default('local');
            $table->string('original_filename', 300)->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('checksum', 64)->nullable(); // SHA-256

            // ── Content (for small docs, base64 in DB) ───────────
            $table->longText('content_base64')->nullable();
            $table->string('download_url', 1000)->nullable();
            $table->timestamp('download_url_expires_at')->nullable();

            // ── Access Control (FR-CR-008) ───────────────────────
            $table->integer('print_count')->default(0);
            $table->timestamp('last_printed_at')->nullable();
            $table->integer('download_count')->default(0);
            $table->timestamp('last_downloaded_at')->nullable();

            // ── Fetch Tracking (FR-CR-005) ───────────────────────
            $table->integer('fetch_attempts')->default(0);
            $table->timestamp('last_fetch_at')->nullable();
            $table->boolean('is_available')->default(true);

            $table->timestamps();

            $table->index(['shipment_id', 'type']);
            $table->index(['carrier_shipment_id', 'type']);
            // FKs omitted for server compatibility
        });
        }

        // ═══════════════════════════════════════════════════════════════
        // 3. carrier_errors — FR-CR-004
        //    Normalized error log for carrier API calls
        // ═══════════════════════════════════════════════════════════════
        if (! Schema::hasTable('carrier_errors')) {
        Schema::create('carrier_errors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('shipment_id')->nullable();
            $table->uuid('carrier_shipment_id')->nullable();

            $table->string('carrier_code', 50)->default('dhl');
            $table->string('correlation_id', 64)->index();

            // ── Error Classification ─────────────────────────────
            $table->string('operation', 50); // create_shipment, fetch_label, cancel, fetch_rates
            $table->string('internal_code', 50)->index(); // ERR_CR_xxx
            $table->string('carrier_error_code', 100)->nullable();
            $table->text('carrier_error_message')->nullable();
            $table->text('internal_message');

            // ── HTTP Details ─────────────────────────────────────
            $table->integer('http_status')->nullable();
            $table->string('http_method', 10)->nullable();
            $table->string('endpoint_url', 500)->nullable();

            // ── Retry Info ───────────────────────────────────────
            $table->boolean('is_retriable')->default(false);
            $table->integer('retry_attempt')->default(0);
            $table->integer('max_retries')->default(3);
            $table->timestamp('next_retry_at')->nullable();
            $table->boolean('was_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();

            // ── Context ──────────────────────────────────────────
            $table->json('request_context')->nullable();
            $table->json('response_body')->nullable();

            $table->timestamps();

            $table->index(['shipment_id', 'operation']);
            $table->index(['is_retriable', 'was_resolved']);
            // FKs omitted for server compatibility
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_errors');
        Schema::dropIfExists('carrier_documents');
        Schema::dropIfExists('carrier_shipments');
    }
};
