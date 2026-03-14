<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * حساب منظمة + مستخدمون مخصصون لبوابة B2B.
 * تسجيل الدخول من /b2b/login مع معرّف المنظمة: demo-company
 *
 * المستخدم 1: admin@company.sa  / password  (مدير النظام)
 * المستخدم 2: b2b@company.sa     / password  (مخصص B2B)
 */
class DevLoginSeeder extends Seeder
{
    public function run(): void
    {
        $account = Account::firstOrCreate(
            ['slug' => 'demo-company'],
            [
                'name' => 'شركة الشحن السريع',
                'type' => 'organization',
                'status' => 'active',
            ]
        );

        $users = [
            ['email' => 'admin@company.sa', 'name' => 'مدير النظام', 'is_owner' => true],
            ['email' => 'b2b@company.sa', 'name' => 'B2B مخصص', 'is_owner' => false],
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
