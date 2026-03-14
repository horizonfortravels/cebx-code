<?php

namespace Database\Seeders;

use App\Models\WaiverVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class WaiverVersionSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('waiver_versions')) {
            return;
        }

        $definitions = [
            [
                'version' => '2026.03',
                'locale' => 'en',
                'waiver_text' => 'I declare that this shipment does not contain dangerous goods. I accept full legal responsibility if this declaration is inaccurate.',
            ],
            [
                'version' => '2026.03',
                'locale' => 'ar',
                'waiver_text' => 'أقر بأن هذه الشحنة لا تحتوي على مواد خطرة، وأتحمل المسؤولية القانونية الكاملة إذا كانت هذه الإفادة غير صحيحة.',
            ],
        ];

        foreach ($definitions as $definition) {
            WaiverVersion::query()
                ->where('locale', $definition['locale'])
                ->where('is_active', true)
                ->update(['is_active' => false]);

            WaiverVersion::query()->updateOrCreate(
                [
                    'version' => $definition['version'],
                    'locale' => $definition['locale'],
                ],
                [
                    'waiver_text' => $definition['waiver_text'],
                    'waiver_hash' => hash('sha256', $definition['waiver_text']),
                    'is_active' => true,
                    'created_by' => null,
                ]
            );
        }
    }
}
