<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('addresses')) {
            return;
        }

        Schema::table('addresses', function (Blueprint $table) {
            if (! Schema::hasColumn('addresses', 'type')) {
                $table->enum('type', ['sender', 'recipient', 'both'])->default('both')->after('account_id');
            }

            if (! Schema::hasColumn('addresses', 'is_default_sender')) {
                $table->boolean('is_default_sender')->default(false)->after('type');
            }

            if (! Schema::hasColumn('addresses', 'contact_name')) {
                $table->string('contact_name', 200)->nullable()->after('label');
            }

            if (! Schema::hasColumn('addresses', 'company_name')) {
                $table->string('company_name', 200)->nullable()->after('contact_name');
            }

            if (! Schema::hasColumn('addresses', 'email')) {
                $table->string('email', 255)->nullable()->after('phone');
            }

            if (! Schema::hasColumn('addresses', 'address_line_1')) {
                $table->string('address_line_1', 300)->nullable()->after('email');
            }

            if (! Schema::hasColumn('addresses', 'address_line_2')) {
                $table->string('address_line_2', 300)->nullable()->after('address_line_1');
            }

            if (! Schema::hasColumn('addresses', 'state')) {
                $table->string('state', 100)->nullable()->after('city');
            }
        });

        if (! $this->hasIndex('addresses', 'addresses_account_id_type_index')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->index(['account_id', 'type']);
            });
        }

        DB::table('addresses')->update([
            'type' => DB::raw("COALESCE(type, 'both')"),
            'is_default_sender' => DB::raw('CASE WHEN is_default = 1 THEN 1 ELSE is_default_sender END'),
            'contact_name' => DB::raw('COALESCE(NULLIF(contact_name, \'\'), NULLIF(name, \'\'))'),
            'address_line_1' => DB::raw('COALESCE(NULLIF(address_line_1, \'\'), NULLIF(street, \'\'), NULLIF(district, \'\'))'),
            'address_line_2' => DB::raw('COALESCE(NULLIF(address_line_2, \'\'), NULLIF(district, \'\'))'),
        ]);
    }

    public function down(): void
    {
    }

    private function hasIndex(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
