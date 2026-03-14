<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FR-IAM-011: آلية الدعوات للمستخدمين
 *
 * Creates the invitations table to support:
 * - Invite users via email with a unique secure token
 * - Assign a role upon acceptance
 * - TTL-based expiration (configurable, default 72h)
 * - Status lifecycle: pending → accepted | expired | cancelled
 * - Resend capability (only when pending)
 * - Tenant-scoped (account_id)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invitations')) {
            return;
        }

        Schema::create('invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->string('email');
            $table->string('name')->nullable();          // Optional: pre-set display name
            $table->uuid('role_id')->nullable();          // Role to assign upon acceptance
            $table->string('token', 128)->unique();       // Secure unique invitation token
            $table->enum('status', ['pending', 'accepted', 'expired', 'cancelled'])
                  ->default('pending')
                  ->index();
            $table->uuid('invited_by');                   // User who created the invitation
            $table->uuid('accepted_by')->nullable();      // User who accepted (once created)
            $table->timestamp('expires_at');              // TTL expiration timestamp
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_sent_at')->nullable(); // Last email send timestamp
            $table->unsignedInteger('send_count')->default(1); // How many times sent/resent
            $table->timestamps();

            $table->index('account_id');
            $table->index('role_id');
            $table->index('invited_by');
            $table->index('accepted_by');
            // FKs omitted: accounts.id and users.id may be bigint on server

            // Note: uniqueness of pending invitation per email+account enforced in InvitationService
            $table->index(['account_id', 'email']);
            $table->index(['account_id', 'status']);
            $table->index('token');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
