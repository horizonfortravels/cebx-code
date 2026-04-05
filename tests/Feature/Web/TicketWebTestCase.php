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
            $this->runTicketSuiteBootstrapCommand([PHP_BINARY, 'artisan', 'migrate:fresh', '--seed', '--env=testing']);

            DB::purge($connectionName);
            DB::reconnect($connectionName);

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    private function recreateTestingDatabase(): void
    {
        $connection = $this->testingConnectionConfig();
        $database = $connection['database'];
        $host = $connection['host'];
        $port = $connection['port'];
        $username = $connection['username'];
        $password = $connection['password'];
        $charset = $connection['charset'];
        $collation = $connection['collation'];

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
     * @param  array<int, string>  $command
     */
    private function runTicketSuiteBootstrapCommand(array $command): void
    {
        $connection = $this->testingConnectionConfig();

        $process = new Process($command, base_path(), [
            'APP_ENV' => 'testing',
            'DATABASE_URL' => '',
            'DB_CONNECTION' => $connection['name'],
            'DB_HOST' => $connection['host'],
            'DB_PORT' => $connection['port'],
            'DB_DATABASE' => $connection['database'],
            'DB_USERNAME' => $connection['username'],
            'DB_PASSWORD' => $connection['password'],
            'DB_CHARSET' => $connection['charset'],
            'DB_COLLATION' => $connection['collation'],
            'SEED_E2E_MATRIX' => 'true',
        ]);
        $process->setTimeout(1200);
        $process->mustRun();
    }

    /**
     * @return array{name: string, host: string, port: string, database: string, username: string, password: string, charset: string, collation: string}
     */
    private function testingConnectionConfig(): array
    {
        $defaultConnection = (string) env('DB_CONNECTION', (string) config('database.default', 'mysql'));
        $configuredConnection = config('database.connections.'.$defaultConnection, []);

        return [
            'name' => $defaultConnection,
            'host' => (string) env('DB_HOST', (string) ($configuredConnection['host'] ?? '127.0.0.1')),
            'port' => (string) env('DB_PORT', (string) ($configuredConnection['port'] ?? '3306')),
            'database' => (string) env('DB_DATABASE', (string) ($configuredConnection['database'] ?? '')),
            'username' => (string) env('DB_USERNAME', (string) ($configuredConnection['username'] ?? 'root')),
            'password' => (string) env('DB_PASSWORD', (string) ($configuredConnection['password'] ?? '')),
            'charset' => (string) env('DB_CHARSET', (string) ($configuredConnection['charset'] ?? 'utf8mb4')),
            'collation' => (string) env('DB_COLLATION', (string) ($configuredConnection['collation'] ?? 'utf8mb4_unicode_ci')),
        ];
    }
}
