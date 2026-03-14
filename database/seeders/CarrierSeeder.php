<?php
namespace Database\Seeders;

use App\Models\StatusMapping;
use Illuminate\Database\Seeder;

class CarrierSeeder extends Seeder
{
    public function run(): void
    {
        // DHL Status Mappings are in DhlStatusMappingSeeder
        // Aramex Status Mappings
        $aramexMappings = [
            ['IN' => 'processing', 'label_ar' => 'قيد المعالجة'],
            ['OUT' => 'shipped', 'label_ar' => 'تم الشحن'],
            ['TR' => 'in_transit', 'label_ar' => 'في الطريق'],
            ['DL' => 'delivered', 'label_ar' => 'تم التسليم'],
            ['RT' => 'returned', 'label_ar' => 'مرتجع'],
        ];

        foreach ($aramexMappings as $i => $m) {
            $key = array_keys($m)[0];
            StatusMapping::firstOrCreate([
                'carrier_code' => 'aramex',
                'carrier_status' => $key,
                'carrier_status_code' => null,
            ], [
                'unified_status' => $m[$key],
                'unified_description' => $m['label_ar'],
                'notify_store' => in_array($key, ['DL', 'RT']),
                'store_status' => $key === 'DL' ? 'fulfilled' : ($key === 'RT' ? 'returned' : null),
                'is_terminal' => in_array($key, ['DL', 'RT']),
                'is_exception' => $key === 'RT',
                'requires_action' => false,
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }
    }
}
