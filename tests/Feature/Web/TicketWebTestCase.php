<?php

namespace Tests\Feature\Web;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use PDO;
use Symfony\Component\Process\Process;
use Tests\TestCase;

abstract class TicketWebTestCase extends TestCase
{
    public function refreshDatabase(): void
    {
        if (! RefreshDatabaseState::$migrated) {
            $connectionName = config('database.default', 'mysql');

            DB::disconnect($connectionName);
            DB::purge($connectionName);

            $this->recreateTestingDatabase();
            $this->runTicketSuiteBootstrapCommand([PHP_BINARY, 'artisan', 'migrate']);
            $this->runTicketSuiteBootstrapCommand([
                PHP_BINARY,
                'artisan',
                'db:seed',
                '--class=Database\\Seeders\\E2EUserMatrixSeeder',
            ]);

            DB::purge($connectionName);
            DB::reconnect($connectionName);

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    private function recreateTestingDatabase(): void
    {
        $connection = config('database.connections.'.config('database.default', 'mysql'));
        $database = (string) ($connection['database'] ?? '');
        $host = (string) ($connection['host'] ?? '127.0.0.1');
        $port = (string) ($connection['port'] ?? '3306');
        $username = (string) ($connection['username'] ?? 'root');
        $password = (string) ($connection['password'] ?? '');
        $charset = (string) ($connection['charset'] ?? 'utf8mb4');
        $collation = (string) ($connection['collation'] ?? 'utf8mb4_unicode_ci');

        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;charset=%s', $host, $port, $charset),
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $quotedDatabase = str_replace('`', '``', $database);

        $pdo->exec("DROP DATABASE IF EXISTS `{$quotedDatabase}`");
        $pdo->exec("CREATE DATABASE `{$quotedDatabase}` CHARACTER SET {$charset} COLLATE {$collation}");
    }

    /**
     * @param array<int, string> $command
     */
    private function runTicketSuiteBootstrapCommand(array $command): void
    {
        $connection = config('database.connections.'.config('database.default', 'mysql'));

        $process = new Process($command, base_path(), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => (string) config('database.default', 'mysql'),
            'DB_HOST' => (string) ($connection['host'] ?? '127.0.0.1'),
            'DB_PORT' => (string) ($connection['port'] ?? '3306'),
            'DB_DATABASE' => (string) ($connection['database'] ?? ''),
            'DB_USERNAME' => (string) ($connection['username'] ?? 'root'),
            'DB_PASSWORD' => (string) ($connection['password'] ?? ''),
            'DB_CHARSET' => (string) ($connection['charset'] ?? 'utf8mb4'),
            'DB_COLLATION' => (string) ($connection['collation'] ?? 'utf8mb4_unicode_ci'),
            'SEED_E2E_MATRIX' => 'true',
        ]);
        $process->setTimeout(1200);
        $process->mustRun();
    }
}
