<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_declarations') || Schema::hasColumn('content_declarations', 'dg_flag_declared')) {
            return;
        }

        Schema::table('content_declarations', function (Blueprint $table) {
            $table->boolean('dg_flag_declared')->default(false)->after('contains_dangerous_goods');
            $table->index('dg_flag_declared');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('content_declarations') || ! Schema::hasColumn('content_declarations', 'dg_flag_declared')) {
            return;
        }

        Schema::table('content_declarations', function (Blueprint $table) {
            $table->dropIndex(['dg_flag_declared']);
            $table->dropColumn('dg_flag_declared');
        });
    }
};
