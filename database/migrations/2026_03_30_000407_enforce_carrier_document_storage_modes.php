<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('carrier_documents')) {
            return;
        }

        DB::table('carrier_documents')
            ->where(function ($query): void {
                $query->whereNull('storage_path')
                    ->orWhere('storage_path', '');
            })
            ->update(['storage_disk' => null]);

        DB::table('carrier_documents')
            ->whereNotNull('storage_path')
            ->where('storage_path', '!=', '')
            ->update(['retrieval_mode' => 'stored_object']);

        DB::table('carrier_documents')
            ->where(function ($query): void {
                $query->whereNull('storage_path')
                    ->orWhere('storage_path', '');
            })
            ->whereNotNull('content_base64')
            ->where('content_base64', '!=', '')
            ->update(['retrieval_mode' => 'inline']);

        DB::table('carrier_documents')
            ->where(function ($query): void {
                $query->whereNull('storage_path')
                    ->orWhere('storage_path', '');
            })
            ->where(function ($query): void {
                $query->whereNull('content_base64')
                    ->orWhere('content_base64', '');
            })
            ->whereNotNull('download_url')
            ->where('download_url', '!=', '')
            ->update(['retrieval_mode' => 'url']);

        DB::statement("ALTER TABLE `carrier_documents` MODIFY `storage_disk` VARCHAR(50) NULL");
    }

    public function down(): void
    {
        if (! Schema::hasTable('carrier_documents')) {
            return;
        }

        DB::table('carrier_documents')
            ->whereNull('storage_disk')
            ->update(['storage_disk' => 'local']);

        DB::statement("ALTER TABLE `carrier_documents` MODIFY `storage_disk` VARCHAR(50) NOT NULL DEFAULT 'local'");
    }
};
