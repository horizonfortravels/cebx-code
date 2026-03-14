<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DG Module — Dangerous Goods Compliance & Content Declaration
 * FR-DG-001→009 (9 requirements)
 *
 * Tables:
 *   1. content_declarations   — FR-DG-001/002: Per-shipment content declaration record
 *   2. waiver_versions        — FR-DG-006: Versioned liability waiver texts (AR/EN)
 *   3. dg_audit_logs          — FR-DG-005: Append-only audit trail for declarations
 *   4. dg_metadata            — FR-DG-009: Optional DG details (UN number, class, qty)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('waiver_versions')) {
            return;
        }

        // ═══════════════════════════════════════════════════════════
        // 1. waiver_versions — FR-DG-006 (created first for FK)
        // ═══════════════════════════════════════════════════════════
        Schema::create('waiver_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('version', 20);                        // e.g. "1.0", "1.1"
            $table->string('locale', 5);                          // ar, en
            $table->text('waiver_text');                           // Full legal text
            $table->string('waiver_hash', 64);                    // SHA-256 hash of text
            $table->boolean('is_active')->default(true);          // Current active version
            $table->string('created_by', 100)->nullable();        // Admin who published

            $table->timestamps();

            $table->unique(['version', 'locale']);
            $table->index(['locale', 'is_active']);
        });

        // ═══════════════════════════════════════════════════════════
        // 2. content_declarations — FR-DG-001/002/003/004/007
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('content_declarations')) {
            Schema::create('content_declarations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('account_id');
                $table->string('shipment_id', 100)->index();          // Linked shipment

                // ── FR-DG-002: DG Flag (mandatory) ──────────────
                $table->boolean('contains_dangerous_goods');           // Yes/No

                // ── FR-DG-003: Status when DG=Yes ───────────────
                $table->enum('status', [
                    'pending',          // Declaration started
                    'completed',        // DG=No + waiver accepted → ready
                    'hold_dg',          // DG=Yes → blocked in MVP
                    'requires_action',  // Needs more info
                    'expired',          // Declaration expired
                ])->default('pending');

                $table->string('hold_reason', 500)->nullable();       // FR-DG-003: Why blocked

                // ── FR-DG-004: Liability Waiver ─────────────────
                $table->boolean('waiver_accepted')->default(false);
                $table->foreignUuid('waiver_version_id')->nullable()
                      ->constrained('waiver_versions')->nullOnDelete();
                $table->string('waiver_hash_snapshot', 64)->nullable(); // FR-DG-006: Hash at time of acceptance
                $table->text('waiver_text_snapshot')->nullable();       // FR-DG-006: Text snapshot
                $table->timestamp('waiver_accepted_at')->nullable();

                // ── Proof / Evidence ─────────────────────────────
                $table->string('declared_by', 100);                   // user_id
                $table->string('ip_address', 45)->nullable();         // FR-DG-002/005: IP for evidence
                $table->string('user_agent', 500)->nullable();        // FR-DG-002: User-Agent
                $table->string('locale', 5)->default('ar');           // AR/EN

                $table->timestamp('declared_at')->useCurrent();

                $table->timestamps();

                $table->index(['account_id', 'status']);
                $table->index(['shipment_id', 'status']);
                // FK account_id omitted for server compatibility
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 3. dg_metadata — FR-DG-009: Optional DG details
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('dg_metadata')) {
            Schema::create('dg_metadata', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('declaration_id')->constrained('content_declarations')->cascadeOnDelete();

                $table->string('un_number', 10)->nullable();          // UN classification number
                $table->string('dg_class', 20)->nullable();           // Hazard class (1-9)
                $table->string('packing_group', 10)->nullable();      // I, II, III
                $table->string('proper_shipping_name', 300)->nullable();
                $table->decimal('quantity', 10, 3)->nullable();
                $table->string('quantity_unit', 20)->nullable();      // kg, L, pieces
                $table->text('description')->nullable();
                $table->json('additional_info')->nullable();

                $table->timestamps();

                $table->index(['un_number']);
            });
        }

        // ═══════════════════════════════════════════════════════════
        // 4. dg_audit_logs — FR-DG-005: Append-only audit
        // ═══════════════════════════════════════════════════════════
        if (! Schema::hasTable('dg_audit_logs')) {
            Schema::create('dg_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('declaration_id')
                    ->constrained('content_declarations')
                    ->cascadeOnDelete();

                $table->string('action');
                $table->json('payload')->nullable();

                $table->timestamps();

                $table->index(['declaration_id', 'created_at']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('dg_audit_logs');
        Schema::dropIfExists('dg_metadata');
        Schema::dropIfExists('content_declarations');
        Schema::dropIfExists('waiver_versions');
    }
};
