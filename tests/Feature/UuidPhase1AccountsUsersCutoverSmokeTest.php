<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UuidPhase1AccountsUsersCutoverSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounts_and_users_use_uuid_ids_after_cutover(): void
    {
        if (
            !Schema::hasTable('accounts') ||
            !Schema::hasTable('users') ||
            !Schema::hasColumn('accounts', 'id_legacy') ||
            !Schema::hasColumn('users', 'id_legacy') ||
            !Schema::hasColumn('users', 'account_id_legacy')
        ) {
            $this->markTestSkipped('PR4A cutover schema is not active in this environment.');
        }

        $accountId = (string) Str::uuid();
        $userId = (string) Str::uuid();

        DB::table('accounts')->insert([
            'id' => $accountId,
            'id_legacy' => 100001,
            'name' => 'UUID Cutover Account',
            'type' => 'organization',
            'email' => 'cutover-account-'.Str::lower(Str::random(8)).'@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->insert([
            'id' => $userId,
            'id_legacy' => 200001,
            'account_id' => $accountId,
            'account_id_legacy' => 100001,
            'name' => 'UUID Cutover User',
            'email' => 'cutover-user-'.Str::lower(Str::random(8)).'@example.test',
            'password' => bcrypt('Password1!'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $storedAccountId = (string) DB::table('accounts')->where('id', $accountId)->value('id');
        $storedUserId = (string) DB::table('users')->where('id', $userId)->value('id');
        $storedUserAccountId = (string) DB::table('users')->where('id', $userId)->value('account_id');

        $this->assertMatchesRegularExpression('/^[0-9a-fA-F-]{36}$/', $storedAccountId);
        $this->assertMatchesRegularExpression('/^[0-9a-fA-F-]{36}$/', $storedUserId);
        $this->assertMatchesRegularExpression('/^[0-9a-fA-F-]{36}$/', $storedUserAccountId);
    }
}
