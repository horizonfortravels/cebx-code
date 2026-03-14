<?php
namespace Database\Seeders;

use App\Models\FeatureFlag;
use Illuminate\Database\Seeder;

class FeatureFlagSeeder extends Seeder
{
    public function run(): void
    {
        $flags = [
            ['key' => 'bulk_import', 'name' => 'الشحن الجماعي', 'is_enabled' => true],
            ['key' => 'dg_compliance', 'name' => 'المواد الخطرة', 'is_enabled' => true],
            ['key' => 'ai_risk_scoring', 'name' => 'تقييم المخاطر AI', 'is_enabled' => true],
            ['key' => 'multi_currency', 'name' => 'العملات المتعددة', 'is_enabled' => false],
            ['key' => 'route_optimization', 'name' => 'تحسين المسارات', 'is_enabled' => true],
            ['key' => 'live_tracking', 'name' => 'التتبع المباشر', 'is_enabled' => true],
            ['key' => 'auto_customs', 'name' => 'التخليص الآلي', 'is_enabled' => false],
            ['key' => 'webhook_events', 'name' => 'Webhooks', 'is_enabled' => true],
            ['key' => 'sms_notifications', 'name' => 'إشعارات SMS', 'is_enabled' => true],
            ['key' => 'api_rate_limiting', 'name' => 'حدود API', 'is_enabled' => true],
        ];

        foreach ($flags as $f) {
            FeatureFlag::firstOrCreate(['key' => $f['key']], $f);
        }
    }
}
