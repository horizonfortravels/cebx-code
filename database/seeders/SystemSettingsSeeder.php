<?php
namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'company_name', 'value' => 'شركة الشحن السريع', 'group' => 'general'],
            ['key' => 'company_email', 'value' => 'info@shipping-gateway.sa', 'group' => 'general'],
            ['key' => 'default_country', 'value' => 'SA', 'group' => 'general'],
            ['key' => 'default_currency', 'value' => 'SAR', 'group' => 'general'],
            ['key' => 'default_language', 'value' => 'ar', 'group' => 'general'],
            ['key' => 'default_timezone', 'value' => 'Asia/Riyadh', 'group' => 'general'],
            ['key' => 'default_weight_unit', 'value' => 'kg', 'group' => 'shipment'],
            ['key' => 'default_dimension_unit', 'value' => 'cm', 'group' => 'shipment'],
            ['key' => 'auto_tracking_interval', 'value' => '30', 'group' => 'tracking'],
            ['key' => 'wallet_min_balance_alert', 'value' => '500', 'group' => 'billing'],
            ['key' => 'kyc_auto_approve', 'value' => 'false', 'group' => 'kyc'],
            ['key' => 'max_bulk_import_rows', 'value' => '5000', 'group' => 'shipment'],
            ['key' => 'audit_log_retention_days', 'value' => '365', 'group' => 'security'],
            ['key' => 'session_timeout_minutes', 'value' => '30', 'group' => 'security'],
            ['key' => 'mfa_required', 'value' => 'false', 'group' => 'security'],
        ];

        foreach ($settings as $s) {
            SystemSetting::firstOrCreate(['key' => $s['key']], $s);
        }
    }
}
