<?php

namespace App\Listeners;

use Illuminate\Database\Events\MigrationEnded;
use Illuminate\Support\Facades\DB;

class RefreshDatabaseConnectionAfterMigration
{
    public function handle(MigrationEnded $event): void
    {
        if (app()->runningUnitTests() || app()->environment('testing')) {
            return;
        }

        $defaultConnection = config('database.default');

        if (! is_string($defaultConnection) || $defaultConnection === '') {
            return;
        }

        if (config("database.connections.{$defaultConnection}.driver") !== 'mysql') {
            return;
        }

        DB::purge($defaultConnection);
        DB::reconnect($defaultConnection);
    }
}
