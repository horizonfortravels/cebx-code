<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UuidPhase1RemainingTablesCutoverSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_remaining_tables_are_cutover_to_uuid_on_mysql(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('PR4B cutover smoke is MySQL-only.');
        }

        $required = [
            ['accounts', 'id'],
            ['users', 'id'],
            ['shipments', 'id'],
            ['shipments', 'account_id'],
            ['wallets', 'id'],
            ['wallet_transactions', 'wallet_id'],
        ];

        foreach ($required as [$table, $column]) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                $this->markTestSkipped("Required {$table}.{$column} is not available in this environment.");
            }
        }

        $accountId = (string) Str::uuid();
        DB::table('accounts')->insert([
            'id' => $accountId,
            'name' => 'PR4B Smoke Account',
            'email' => 'pr4b-account-'.Str::lower(Str::random(8)).'@example.test',
            'type' => 'organization',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $userId,
            'account_id' => $accountId,
            'name' => 'PR4B Smoke User',
            'email' => 'pr4b-user-'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt('Password1!'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $shipmentId = (string) Str::uuid();
        DB::table('shipments')->insert([
            'id' => $shipmentId,
            'account_id' => $accountId,
            'user_id' => $userId,
            'reference_number' => 'PR4B-SHP-'.Str::upper(Str::random(10)),
            'sender_name' => 'Sender Name',
            'sender_city' => 'Riyadh',
            'recipient_name' => 'Recipient Name',
            'recipient_phone' => '+966500000000',
            'recipient_city' => 'Jeddah',
            'recipient_country' => 'SA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $walletId = (string) Str::uuid();
        DB::table('wallets')->insert([
            'id' => $walletId,
            'account_id' => $accountId,
            'available_balance' => 0,
            'pending_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $walletTxnId = (string) Str::uuid();
        DB::table('wallet_transactions')->insert([
            'id' => $walletTxnId,
            'wallet_id' => $walletId,
            'account_id' => $accountId,
            'type' => 'credit',
            'description' => 'PR4B smoke tx',
            'amount' => 10,
            'balance_after' => 10,
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (
            Schema::hasTable('stores') &&
            Schema::hasColumn('stores', 'id') &&
            Schema::hasColumn('orders', 'store_id')
        ) {
            $storeId = (string) Str::uuid();
            DB::table('stores')->insert([
                'id' => $storeId,
                'account_id' => $accountId,
                'name' => 'PR4B Smoke Store',
                'platform' => 'salla',
                'status' => 'connected',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('orders')->insert([
                'id' => (string) Str::uuid(),
                'account_id' => $accountId,
                'store_id' => $storeId,
                'order_number' => 'PR4B-ORD-'.Str::upper(Str::random(8)),
                'customer_name' => 'Smoke Customer',
                'customer_phone' => '+966511111111',
                'customer_city' => 'Riyadh',
                'items_count' => 1,
                'total_amount' => 10,
                'status' => 'new',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $storedOrderStoreId = (string) DB::table('orders')->where('store_id', $storeId)->value('store_id');
            $this->assertMatchesRegularExpression('/^[0-9a-fA-F-]{36}$/', $storedOrderStoreId);
        }

        $storedShipmentId = (string) DB::table('shipments')->where('id', $shipmentId)->value('id');
        $storedShipmentAccountId = (string) DB::table('shipments')->where('id', $shipmentId)->value('account_id');
        $storedWalletId = (string) DB::table('wallets')->where('id', $walletId)->value('id');
        $storedWalletTxnWalletId = (string) DB::table('wallet_transactions')->where('id', $walletTxnId)->value('wallet_id');

        $this->assertMatchesRegularExpression('/^[0-9a-fA-F-]{36}$/', $storedShipmentId);
        $this->assertMatchesRegularExpression('/^[0-9a-fA-F-]{36}$/', $storedShipmentAccountId);
        $this->assertMatchesRegularExpression('/^[0-9a-fA-F-]{36}$/', $storedWalletId);
        $this->assertMatchesRegularExpression('/^[0-9a-fA-F-]{36}$/', $storedWalletTxnWalletId);
        $this->assertSame($walletId, $storedWalletTxnWalletId);
    }
}
