<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UuidPhase1ShadowUuidPrimaryKeysSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_shadow_uuid_columns_are_backfilled_for_main_numeric_tables(): void
    {
        $createdRows = [];

        if (Schema::hasTable('accounts') && Schema::hasColumn('accounts', 'id_uuid')) {
            $accountId = DB::table('accounts')->insertGetId([
                'name' => 'Shadow UUID Test Account',
                'email' => 'shadow-'.Str::lower(Str::random(8)).'@example.test',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $createdRows['accounts'] = $accountId;
        }

        if (
            isset($createdRows['accounts']) &&
            Schema::hasTable('users') &&
            Schema::hasColumn('users', 'id_uuid')
        ) {
            $userId = DB::table('users')->insertGetId([
                'account_id' => $createdRows['accounts'],
                'name' => 'Shadow UUID User',
                'email' => 'shadow-user-'.Str::lower(Str::random(8)).'@example.test',
                'password' => bcrypt('Password1!'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $createdRows['users'] = $userId;
        }

        if (
            isset($createdRows['accounts']) &&
            Schema::hasTable('wallets') &&
            Schema::hasColumn('wallets', 'id_uuid')
        ) {
            $walletId = DB::table('wallets')->insertGetId([
                'account_id' => $createdRows['accounts'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $createdRows['wallets'] = $walletId;
        }

        if (
            isset($createdRows['accounts']) &&
            Schema::hasTable('shipments') &&
            Schema::hasColumn('shipments', 'id_uuid')
        ) {
            $shipmentId = DB::table('shipments')->insertGetId([
                'account_id' => $createdRows['accounts'],
                'user_id' => $createdRows['users'] ?? null,
                'reference_number' => 'SHP-SHADOW-'.Str::upper(Str::random(8)),
                'sender_name' => 'Sender Name',
                'sender_city' => 'Riyadh',
                'recipient_name' => 'Recipient Name',
                'recipient_phone' => '+966500000000',
                'recipient_city' => 'Jeddah',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $createdRows['shipments'] = $shipmentId;
        }

        if ($createdRows === []) {
            $this->markTestSkipped('No main numeric tables are available in this environment.');
        }

        foreach ($createdRows as $table => $id) {
            $this->assertDatabaseHas($table, [
                'id' => $id,
                'id_uuid' => null,
            ]);
        }

        $migration = require database_path('migrations/2026_03_05_000120_uuid_phase1_backfill_shadow_uuid_primary_keys.php');
        $migration->up();

        foreach ($createdRows as $table => $id) {
            $uuid = DB::table($table)->where('id', $id)->value('id_uuid');
            $this->assertNotNull($uuid, "{$table}.id_uuid should be populated after backfill.");
        }
    }
}
