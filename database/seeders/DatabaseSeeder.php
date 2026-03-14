<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoSeeder::class,
            RolesAndPermissionsSeeder::class,
            WaiverVersionSeeder::class,
        ]);

        if ($this->shouldSeedE2EMatrix()) {
            $this->call([
                E2EUserMatrixSeeder::class,
            ]);
        }
    }

    private function shouldSeedE2EMatrix(): bool
    {
        return filter_var((string) env('SEED_E2E_MATRIX', false), FILTER_VALIDATE_BOOLEAN);
    }
}
