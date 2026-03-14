<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * مستخدمان تجريبيان للمنصة الرئيسية (Admin — تسجيل الدخول من /login).
 * يظهر لهما كل الشاشات: لوحة التحكم، الشحنات، الطلبات، المتاجر، المحفظة،
 * المستخدمين، الدعم، التتبع، الأدوار، الدعوات، الإشعارات، العناوين، الإعدادات،
 * التقارير، المالية، التدقيق، التسعير، الإدارة، KYC، DG، المنظمات، الحاويات،
 * الجمارك، السائقين، المطالبات، السفن، الجداول، الفروع، الشركات، أكواد HS، المخاطر.
 *
 * المستخدم 1: admin@platform.sa  / password
 * المستخدم 2: admin@gateway.sa    / password  (اسم العرض: Admin)
 */
class DevPlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        $account = Account::firstOrCreate(
            ['slug' => 'demo-platform-admin'],
            [
                'name' => 'إدارة المنصة',
                'type' => 'organization',
                'status' => 'active',
            ]
        );

        $users = [
            ['email' => 'admin@platform.sa', 'name' => 'مدير المنصة', 'is_owner' => true],
            ['email' => 'admin@gateway.sa', 'name' => 'Admin', 'is_owner' => false],
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
