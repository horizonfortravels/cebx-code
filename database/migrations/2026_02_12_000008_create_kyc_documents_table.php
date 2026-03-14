<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FR-IAM-014 + FR-IAM-016: KYC Document Storage & Access Control
 *
 * Adds:
 * - kyc_documents: Secure document storage with access tracking
 * - Enhances kyc_verifications with review notes and level
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kyc_documents')) {
            return;
        }

        // ── KYC Documents (individual file records) ──────────────
        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->uuid('kyc_verification_id');
            $table->string('document_type', 100);              // national_id, commercial_registration, etc.
            $table->string('original_filename', 255);
            $table->string('stored_path', 500);                // Encrypted storage path
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');           // Bytes
            $table->string('file_hash', 128)->nullable();      // SHA-512 integrity hash
            $table->uuid('uploaded_by');
            $table->boolean('is_sensitive')->default(true);    // Contains PII (ID photos, etc.)
            $table->boolean('is_purged')->default(false);      // Soft-purge: content removed, metadata kept
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'document_type']);
            $table->index(['kyc_verification_id']);
            $table->index('uploaded_by');
            // FKs omitted: accounts.id and users.id may be bigint on server
        });

        // ── Enhance kyc_verifications ────────────────────────────
        if (Schema::hasTable('kyc_verifications') && Schema::hasColumn('kyc_verifications', 'verification_type')) {
            Schema::table('kyc_verifications', function (Blueprint $table) {
                if (! Schema::hasColumn('kyc_verifications', 'verification_level')) {
                    $table->string('verification_level', 30)->default('basic')->after('verification_type');
                }
                if (! Schema::hasColumn('kyc_verifications', 'review_notes')) {
                    $table->text('review_notes')->nullable()->after('rejection_reason');
                }
                if (! Schema::hasColumn('kyc_verifications', 'review_count')) {
                    $table->unsignedSmallInteger('review_count')->default(0)->after('review_notes');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('kyc_verifications', function (Blueprint $table) {
            $table->dropColumn(['verification_level', 'review_notes', 'review_count']);
        });
        Schema::dropIfExists('kyc_documents');
    }
};
