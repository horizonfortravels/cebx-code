<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            if (! Schema::hasColumn('shipments', 'sender_state')) {
                $table->string('sender_state', 100)->nullable()->after('sender_city');
            }

            if (! Schema::hasColumn('shipments', 'recipient_state')) {
                $table->string('recipient_state', 100)->nullable()->after('recipient_city');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            if (Schema::hasColumn('shipments', 'sender_state')) {
                $table->dropColumn('sender_state');
            }

            if (Schema::hasColumn('shipments', 'recipient_state')) {
                $table->dropColumn('recipient_state');
            }
        });
    }
};
