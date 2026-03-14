<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Rbac\PermissionsCatalog;
use Illuminate\Database\Seeder;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionsCatalog::all() as $group => $permissions) {
            foreach ($permissions as $key => $displayName) {
                if (str_contains($key, ':')) {
                    throw new \RuntimeException(
                        sprintf('Phase 2B2 requires dot-notation permission keys only. Invalid key: %s', $key)
                    );
                }

                Permission::updateOrCreate(
                    ['key' => $key],
                    [
                        'group' => $group,
                        'display_name' => $displayName,
                        'description' => $displayName,
                    ]
                );
            }
        }

        $this->command?->info('Seeded '.count(PermissionsCatalog::keys()).' permissions.');
    }
}
