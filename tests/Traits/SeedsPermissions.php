<?php

namespace Tests\Traits;

use Database\Seeders\PermissionsSeeder;

trait SeedsPermissions
{
    protected function seedPermissions(): void
    {
        $this->seed(PermissionsSeeder::class);
    }
}
