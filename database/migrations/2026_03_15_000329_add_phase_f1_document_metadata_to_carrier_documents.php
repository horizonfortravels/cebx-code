<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('carrier_documents')) {
            return;
        }

        Schema::table('carrier_documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('carrier_documents', 'carrier_code')) {
                $table->string('carrier_code', 50)->nullable()->after('shipment_id');
            }

            if (! Schema::hasColumn('carrier_documents', 'source')) {
                $table->string('source', 50)->default('carrier')->after('mime_type');
            }

            if (! Schema::hasColumn('carrier_documents', 'retrieval_mode')) {
                $table->string('retrieval_mode', 30)->default('inline')->after('source');
            }

            if (! Schema::hasColumn('carrier_documents', 'carrier_metadata')) {
                $table->json('carrier_metadata')->nullable()->after('download_url_expires_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('carrier_documents')) {
            return;
        }

        Schema::table('carrier_documents', function (Blueprint $table): void {
            if (Schema::hasColumn('carrier_documents', 'carrier_metadata')) {
                $table->dropColumn('carrier_metadata');
            }

            if (Schema::hasColumn('carrier_documents', 'retrieval_mode')) {
                $table->dropColumn('retrieval_mode');
            }

            if (Schema::hasColumn('carrier_documents', 'source')) {
                $table->dropColumn('source');
            }

            if (Schema::hasColumn('carrier_documents', 'carrier_code')) {
                $table->dropColumn('carrier_code');
            }
        });
    }
};
