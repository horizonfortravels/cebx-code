<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UserTypeMiddlewareTest extends TestCase
{
    #[Test]
    public function test_external_user_cannot_access_internal_routes(): void
    {
        $externalUser = $this->createUser('external', true);

        Sanctum::actingAs($externalUser);

        $response = $this->getJson('/api/v1/internal/ping');

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_USER_TYPE_FORBIDDEN');
    }

    #[Test]
    public function test_internal_user_cannot_access_external_routes(): void
    {
        $internalUser = $this->createUser('internal', false);

        Sanctum::actingAs($internalUser);

        $response = $this->getJson('/api/v1/account');

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_USER_TYPE_FORBIDDEN');
    }

    private function createUser(string $userType, bool $withAccount): User
    {
        $account = $withAccount ? $this->createAccount() : null;
        $timestamps = now();

        $payload = [
            'name' => 'User '.Str::random(8),
            'email' => Str::lower(Str::random(10)).'@example.test',
            'password' => Hash::make('Password1!'),
        ];

        if (Schema::hasColumn('users', 'account_id')) {
            $payload['account_id'] = $account?->id;
        }
        if (Schema::hasColumn('users', 'user_type')) {
            $payload['user_type'] = $userType;
        }
        if (Schema::hasColumn('users', 'status')) {
            $payload['status'] = 'active';
        }
        if (Schema::hasColumn('users', 'is_active')) {
            $payload['is_active'] = true;
        }
        if (Schema::hasColumn('users', 'role')) {
            $payload['role'] = 'operator';
        }
        if (Schema::hasColumn('users', 'role_name')) {
            $payload['role_name'] = 'operator';
        }
        if (Schema::hasColumn('users', 'locale')) {
            $payload['locale'] = 'en';
        }
        if (Schema::hasColumn('users', 'timezone')) {
            $payload['timezone'] = 'UTC';
        }
        if (Schema::hasColumn('users', 'created_at')) {
            $payload['created_at'] = $timestamps;
        }
        if (Schema::hasColumn('users', 'updated_at')) {
            $payload['updated_at'] = $timestamps;
        }

        $userId = $this->insertRowAndReturnId('users', $payload);

        /** @var User $user */
        $user = User::withoutGlobalScopes()->where('id', $userId)->firstOrFail();

        return $user;
    }

    private function createAccount(): Account
    {
        $timestamps = now();
        $payload = [
            'name' => 'Account '.Str::random(8),
        ];

        if (Schema::hasColumn('accounts', 'type')) {
            $payload['type'] = 'individual';
        }
        if (Schema::hasColumn('accounts', 'status')) {
            $payload['status'] = 'active';
        }
        if (Schema::hasColumn('accounts', 'slug')) {
            $payload['slug'] = 'acct-'.Str::lower(Str::random(8));
        }
        if (Schema::hasColumn('accounts', 'kyc_status')) {
            $payload['kyc_status'] = 'not_submitted';
        }
        if (Schema::hasColumn('accounts', 'settings')) {
            $payload['settings'] = [];
        }
        if (Schema::hasColumn('accounts', 'created_at')) {
            $payload['created_at'] = $timestamps;
        }
        if (Schema::hasColumn('accounts', 'updated_at')) {
            $payload['updated_at'] = $timestamps;
        }

        $accountId = $this->insertRowAndReturnId('accounts', $payload);

        /** @var Account $account */
        $account = Account::withoutGlobalScopes()->where('id', $accountId)->firstOrFail();

        return $account;
    }

    private function insertRowAndReturnId(string $table, array $payload): string|int
    {
        $idIsNumeric = $this->isNumericId($table);

        if ($idIsNumeric) {
            unset($payload['id']);

            return DB::table($table)->insertGetId($payload);
        }

        if (!array_key_exists('id', $payload)) {
            $payload['id'] = (string) Str::uuid();
        }

        DB::table($table)->insert($payload);

        return $payload['id'];
    }

    private function isNumericId(string $table): bool
    {
        if (!Schema::hasColumn($table, 'id')) {
            return false;
        }

        $type = strtolower((string) Schema::getColumnType($table, 'id'));

        return in_array($type, [
            'integer',
            'int',
            'tinyint',
            'smallint',
            'mediumint',
            'bigint',
            'biginteger',
            'unsignedinteger',
            'unsignedbiginteger',
        ], true);
    }
}
