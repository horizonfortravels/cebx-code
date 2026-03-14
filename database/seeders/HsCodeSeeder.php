<?php
namespace Database\Seeders;

use App\Models\HsCode;
use Illuminate\Database\Seeder;

class HsCodeSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            ['code' => '8471.30.00', 'description' => 'Portable computers (laptops)', 'description_ar' => 'حواسيب محمولة', 'duty_rate' => 5.00, 'chapter' => '84', 'is_restricted' => false],
            ['code' => '8517.13.00', 'description' => 'Smartphones', 'description_ar' => 'هواتف ذكية', 'duty_rate' => 5.00, 'chapter' => '85', 'is_restricted' => false],
            ['code' => '6110.20.00', 'description' => 'Cotton sweaters/pullovers', 'description_ar' => 'سترات قطنية', 'duty_rate' => 12.00, 'chapter' => '61', 'is_restricted' => false],
            ['code' => '0901.11.00', 'description' => 'Coffee, not roasted', 'description_ar' => 'بن غير محمص', 'duty_rate' => 0.00, 'chapter' => '09', 'is_restricted' => false],
            ['code' => '2710.12.10', 'description' => 'Motor gasoline', 'description_ar' => 'بنزين', 'duty_rate' => 5.00, 'chapter' => '27', 'is_restricted' => true],
            ['code' => '3004.90.00', 'description' => 'Medicaments', 'description_ar' => 'أدوية', 'duty_rate' => 0.00, 'chapter' => '30', 'is_restricted' => true],
            ['code' => '8703.24.00', 'description' => 'Motor vehicles > 3000cc', 'description_ar' => 'سيارات > 3000 سي سي', 'duty_rate' => 7.00, 'chapter' => '87', 'is_restricted' => false],
            ['code' => '7113.19.00', 'description' => 'Gold jewelry', 'description_ar' => 'مجوهرات ذهبية', 'duty_rate' => 5.00, 'chapter' => '71', 'is_restricted' => false],
            ['code' => '2402.20.00', 'description' => 'Cigarettes', 'description_ar' => 'سجائر', 'duty_rate' => 100.00, 'chapter' => '24', 'is_restricted' => true],
            ['code' => '9504.50.00', 'description' => 'Video game consoles', 'description_ar' => 'أجهزة ألعاب', 'duty_rate' => 5.00, 'chapter' => '95', 'is_restricted' => false],
        ];

        foreach ($codes as $c) {
            $digits = preg_replace('/\D/', '', $c['code']);
            $c['heading'] = substr($digits . '0000', 0, 4);
            $c['subheading'] = substr($digits . '000000', 0, 6);
            HsCode::firstOrCreate(['code' => $c['code']], $c);
        }
    }
}
