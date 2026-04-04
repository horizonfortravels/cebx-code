<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if ($this->shouldSeedE2EMatrix()) {
            $this->call([
                DemoSeeder::class,
                NotificationTemplateSeeder::class,
                WaiverVersionSeeder::class,
                E2EUserMatrixSeeder::class,
            ]);

            return;
        }

        $this->call([
            DemoSeeder::class,
            RolesAndPermissionsSeeder::class,
            NotificationTemplateSeeder::class,
            WaiverVersionSeeder::class,
        ]);
    }

    private function shouldSeedE2EMatrix(): bool
    {
        return filter_var((string) env('SEED_E2E_MATRIX', false), FILTER_VALIDATE_BOOLEAN);
    }
}
