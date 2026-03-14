<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UuidPhase1ShadowUuidForeignKeysSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_shadow_uuid_foreign_keys_are_backfilled_for_core_edges(): void
    {
        if (
            !Schema::hasTable('accounts') ||
            !Schema::hasColumn('accounts', 'id') ||
            !Schema::hasColumn('accounts', 'id_uuid')
        ) {
            $this->markTestSkipped('accounts.id/id_uuid are required for this smoke test.');
        }

        $accountId = DB::table('accounts')->insertGetId([
            'name' => 'Shadow FK Smoke Account',
            'email' => 'shadow-fk-'.Str::lower(Str::random(8)).'@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = null;
        if (
            Schema::hasTable('users') &&
            Schema::hasColumn('users', 'id') &&
            Schema::hasColumn('users', 'account_id') &&
            Schema::hasColumn('users', 'account_id_uuid')
        ) {
            $userId = DB::table('users')->insertGetId([
                'account_id' => $accountId,
                'name' => 'Shadow FK Smoke User',
                'email' => 'shadow-fk-user-'.Str::lower(Str::random(8)).'@example.test',
                'password' => bcrypt('Password1!'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $shipmentId = null;
        if (
            Schema::hasTable('shipments') &&
            Schema::hasColumn('shipments', 'id') &&
            Schema::hasColumn('shipments', 'account_id') &&
            Schema::hasColumn('shipments', 'account_id_uuid')
        ) {
            $shipmentId = DB::table('shipments')->insertGetId([
                'account_id' => $accountId,
                'user_id' => $userId,
                'reference_number' => 'SHP-SHADOWFK-'.Str::upper(Str::random(10)),
                'sender_name' => 'Sender Name',
                'sender_city' => 'Riyadh',
                'recipient_name' => 'Recipient Name',
                'recipient_phone' => '+966500000000',
                'recipient_city' => 'Jeddah',
                'recipient_country' => 'SA',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $walletId = null;
        if (
            Schema::hasTable('wallets') &&
            Schema::hasColumn('wallets', 'id') &&
            Schema::hasColumn('wallets', 'account_id') &&
            Schema::hasColumn('wallets', 'account_id_uuid')
        ) {
            $walletId = (string) Str::uuid();
            DB::table('wallets')->insert([
                'id' => $walletId,
                'account_id' => $accountId,
                'available_balance' => 0,
                'pending_balance' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $walletTransactionId = null;
        if (
            $walletId !== null &&
            Schema::hasTable('wallet_transactions') &&
            Schema::hasColumn('wallet_transactions', 'id') &&
            Schema::hasColumn('wallet_transactions', 'wallet_id') &&
            Schema::hasColumn('wallet_transactions', 'wallet_id_uuid')
        ) {
            $walletTransactionId = (string) Str::uuid();
            DB::table('wallet_transactions')->insert([
                'id' => $walletTransactionId,
                'wallet_id' => $walletId,
                'account_id' => $accountId,
                'type' => 'credit',
                'description' => 'Shadow FK smoke transaction',
                'amount' => 10,
                'balance_after' => 10,
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $pkBackfillMigration = require database_path('migrations/2026_03_05_000120_uuid_phase1_backfill_shadow_uuid_primary_keys.php');
        $pkBackfillMigration->up();

        $fkBackfillMigration = require database_path('migrations/2026_03_05_000140_uuid_phase1_backfill_shadow_uuid_foreign_keys.php');
        $fkBackfillMigration->up();

        $accountUuid = DB::table('accounts')->where('id', $accountId)->value('id_uuid');
        $this->assertNotNull($accountUuid, 'accounts.id_uuid should be populated before FK shadow backfill assertions.');

        $assertions = 0;

        if ($userId !== null) {
            $userAccountUuid = DB::table('users')->where('id', $userId)->value('account_id_uuid');
            $this->assertSame($accountUuid, $userAccountUuid, 'users.account_id_uuid must match accounts.id_uuid.');
            $assertions++;
        }

        if ($shipmentId !== null) {
            $shipmentAccountUuid = DB::table('shipments')->where('id', $shipmentId)->value('account_id_uuid');
            $this->assertSame($accountUuid, $shipmentAccountUuid, 'shipments.account_id_uuid must match accounts.id_uuid.');
            $assertions++;
        }

        if ($walletId !== null) {
            $walletAccountUuid = DB::table('wallets')->where('id', $walletId)->value('account_id_uuid');
            $this->assertSame($accountUuid, $walletAccountUuid, 'wallets.account_id_uuid must match accounts.id_uuid.');
            $assertions++;
        }

        if (
            $walletTransactionId !== null &&
            Schema::hasColumn('wallets', 'id_uuid') &&
            Schema::hasColumn('wallet_transactions', 'wallet_id_uuid')
        ) {
            $walletIdUuid = DB::table('wallets')->where('id', $walletId)->value('id_uuid');
            $walletTransactionWalletUuid = DB::table('wallet_transactions')
                ->where('id', $walletTransactionId)
                ->value('wallet_id_uuid');

            $this->assertSame(
                $walletIdUuid,
                $walletTransactionWalletUuid,
                'wallet_transactions.wallet_id_uuid must match wallets.id_uuid.'
            );
            $assertions++;
        }

        if ($assertions === 0) {
            $this->markTestSkipped('No shadow UUID FK assertions were applicable in this environment.');
        }
    }
}
