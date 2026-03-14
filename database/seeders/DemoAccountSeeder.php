<?php
namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use App\Models\Role;
use App\Models\Organization;
use App\Models\Wallet;
use App\Models\Address;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        $account = Account::where('slug', 'demo-company')->first() ?? Account::first();
        if (!$account) {
            $account = Account::firstOrCreate(
                ['slug' => 'demo-company'],
                ['name' => 'ط´ط±ظƒط© ط§ظ„ط´ط­ظ† ط§ظ„ط³ط±ظٹط¹', 'type' => 'organization', 'status' => 'active']
            );
        }

        Organization::firstOrCreate(
            ['account_id' => $account->id, 'legal_name' => 'ط´ط±ظƒط© ط§ظ„ط´ط­ظ† ط§ظ„ط³ط±ظٹط¹'],
            [
                'trade_name' => 'ط´ط±ظƒط© ط§ظ„ط´ط­ظ† ط§ظ„ط³ط±ظٹط¹',
                'country_code' => 'SA',
                'verification_status' => 'verified',
                'verified_at' => now(),
            ]
        );

        Wallet::firstOrCreate(
            ['account_id' => $account->id],
            [
                'currency' => 'SAR',
                'available_balance' => 45230.00,
                'locked_balance' => 3200.00,
                'status' => 'active',
            ]
        );

        $users = [
            ['name' => 'ط£ط­ظ…ط¯ ط§ظ„ظ…ط­ظ…ط¯ظٹ', 'email' => 'admin@company.sa', 'password' => Hash::make('password'), 'role_slug' => 'organization_owner', 'status' => 'active', 'phone' => '+966501234567', 'locale' => 'ar'],
            ['name' => 'ظپط§ط·ظ…ط© ط§ظ„ط¹ظ„ظٹ', 'email' => 'fatima@company.sa', 'password' => Hash::make('password'), 'role_slug' => 'organization_admin', 'status' => 'active', 'phone' => '+966507654321', 'locale' => 'ar'],
            ['name' => 'ط®ط§ظ„ط¯ ط§ظ„ط³ط¹ظٹط¯', 'email' => 'khalid@company.sa', 'password' => Hash::make('password'), 'role_slug' => 'organization_admin', 'status' => 'active', 'phone' => '+966509876543', 'locale' => 'ar'],
            ['name' => 'ظ†ظˆط±ط§ ط§ظ„ط´ظ…ط±ظٹ', 'email' => 'noura@company.sa', 'password' => Hash::make('password'), 'role_slug' => 'staff', 'status' => 'active', 'phone' => '+966503456789', 'locale' => 'ar'],
        ];

        foreach ($users as $u) {
            $roleSlug = $u['role_slug'] ?? null;
            unset($u['role_slug']);
            $user = User::firstOrCreate(
                ['account_id' => $account->id, 'email' => $u['email']],
                array_merge($u, ['account_id' => $account->id, 'email_verified_at' => now()])
            );

            if (!$roleSlug) {
                continue;
            }

            $role = Role::withoutGlobalScopes()
                ->where('account_id', $account->id)
                ->where('slug', $roleSlug)
                ->first();

            if ($role) {
                $user->roles()->syncWithoutDetaching([(string) $role->id => ['assigned_at' => now()]]);
            }
        }

        $addresses = [
            ['label' => 'ط§ظ„ظ…ظ‚ط± ط§ظ„ط±ط¦ظٹط³ظٹ', 'contact_name' => 'ط´ط±ظƒط© ط§ظ„ط´ط­ظ† ط§ظ„ط³ط±ظٹط¹', 'phone' => '+966501234567', 'address_line_1' => 'ط·ط±ظٹظ‚ ط§ظ„ظ…ظ„ظƒ ظپظ‡ط¯', 'city' => 'ط§ظ„ط±ظٹط§ط¶', 'postal_code' => '11564', 'country' => 'SA', 'is_default_sender' => true],
            ['label' => 'ظپط±ط¹ ط¬ط¯ط©', 'contact_name' => 'ظپط±ط¹ ط¬ط¯ط©', 'phone' => '+966507654321', 'address_line_1' => 'ط´ط§ط±ط¹ ظپظ„ط³ط·ظٹظ†', 'city' => 'ط¬ط¯ط©', 'postal_code' => '21462', 'country' => 'SA', 'is_default_sender' => false],
            ['label' => 'ظ…ط³طھظˆط¯ط¹ ط§ظ„ط¯ظ…ط§ظ…', 'contact_name' => 'ظ…ط³طھظˆط¯ط¹ ط§ظ„ط¯ظ…ط§ظ…', 'phone' => '+966509876543', 'address_line_1' => 'ط´ط§ط±ط¹ 15', 'city' => 'ط§ظ„ط¯ظ…ط§ظ…', 'postal_code' => '31473', 'country' => 'SA', 'is_default_sender' => false],
        ];

        foreach ($addresses as $a) {
            Address::firstOrCreate(
                ['account_id' => $account->id, 'label' => $a['label']],
                array_merge($a, ['account_id' => $account->id])
            );
        }
    }
}

