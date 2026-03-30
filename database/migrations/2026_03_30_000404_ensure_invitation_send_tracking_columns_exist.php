<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invitations')) {
            return;
        }

        Schema::table('invitations', function (Blueprint $table): void {
            if (! Schema::hasColumn('invitations', 'last_sent_at')) {
                $table->timestamp('last_sent_at')->nullable();
            }

            if (! Schema::hasColumn('invitations', 'send_count')) {
                $table->unsignedInteger('send_count')->default(1);
            }
        });
    }

    public function down(): void
    {
        // Forward-only safety: legacy environments may rely on this convergence
        // to align the historical invitations table with current resend semantics.
    }
};
