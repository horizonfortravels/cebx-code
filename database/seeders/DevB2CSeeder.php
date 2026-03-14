<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * مستخدمون مخصصون لبوابة B2C (أفراد — individual).
 * تسجيل الدخول من /b2c/login فقط.
 *
 * المستخدم 1: user@individual.sa  / password
 * المستخدم 2: b2c@individual.sa    / password  (مخصص B2C)
 */
class DevB2CSeeder extends Seeder
{
    public function run(): void
    {
        $account = Account::firstOrCreate(
            ['slug' => 'demo-individual'],
            [
                'name' => 'مستخدم فردي تجريبي',
                'type' => 'individual',
                'status' => 'active',
            ]
        );

        $users = [
            ['email' => 'user@individual.sa', 'name' => 'مستخدم B2C', 'is_owner' => true],
            ['email' => 'b2c@individual.sa', 'name' => 'B2C مخصص', 'is_owner' => false],
        ];

        foreach ($users as $u) {
            User::firstOrCreate(
                ['account_id' => $account->id, 'email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => Hash::make('password'),
                    'status' => 'active',
                    'is_owner' => $u['is_owner'],
                    'locale' => 'ar',
                    'timezone' => 'Asia/Riyadh',
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
