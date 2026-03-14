<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AccountService
{
    public function __construct(
        private readonly AccountTypeService $accountTypeService = new AccountTypeService()
    ) {}

    /**
     * Create a new account with its owner user inside a DB transaction.
     *
     * @param  array $data  Validated data from RegisterAccountRequest
     * @return array{account: Account, user: User}
     *
     * @throws \Throwable
     */
    public function createAccount(array $data): array
    {
        return DB::transaction(function () use ($data) {

            // 1. Create the Account
            $account = Account::create([
                'name'     => $data['account_name'],
                'type'     => $data['account_type'] ?? 'individual',
                'status'   => 'active',
                'slug'     => Account::generateSlug($data['account_name']),
                'settings' => $this->defaultSettings($data),
            ]);

            // 2. Create the Owner User (bypass global scope by setting account_id explicitly)
            $user = User::withoutGlobalScopes()->create([
                'account_id' => $account->id,
                'name'       => $data['name'],
                'email'      => $data['email'],
                'password'   => Hash::make($data['password']), // Explicitly hashed for security
                'phone'      => $data['phone'] ?? null,
                'is_owner'   => true,
                'status'     => 'active',
                'locale'     => $data['locale'] ?? 'en',
                'timezone'   => $data['timezone'] ?? 'UTC',
            ]);

            // 3. Initialize account type (org profile + KYC) — FR-IAM-010
            $this->accountTypeService->initializeAccountType($account, $data);

            // 4. Log the action
            AuditLog::withoutGlobalScopes()->create([
                'account_id'  => $account->id,
                'user_id'     => $user->id,
                'action'      => 'account.created',
                'entity_type' => 'Account',
                'entity_id'   => $account->id,
                'new_values'  => [
                    'name'   => $account->name,
                    'type'   => $account->type,
                    'status' => $account->status,
                    'slug'   => $account->slug,
                ],
                'ip_address'  => request()->ip(),
                'user_agent'  => request()->userAgent(),
            ]);

            return compact('account', 'user');
        });
    }

    /**
     * Build default settings for a new account.
     */
    private function defaultSettings(array $data): array
    {
        return [
            'currency' => 'USD',
            'timezone' => $data['timezone'] ?? 'UTC',
            'locale'   => $data['locale'] ?? 'en',
            'default_sender_address' => null,
        ];
    }
}
