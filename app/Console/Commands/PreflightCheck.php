<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PreflightCheck extends Command
{
    protected $signature = 'preflight:check
        {--strict : Treat warnings as failures}
        {--json : Output as JSON}';

    protected $description = 'Run pre-flight checks before go-live';

    private array $results = [];
    private int $passed = 0;
    private int $warnings = 0;
    private int $failed = 0;

    public function handle(): int
    {
        $this->line('');
        $this->line(str_repeat('=', 48));
        $this->line('  PRE-FLIGHT CHECK');
        $this->line(str_repeat('=', 48));
        $this->line('');

        $this->check('ENV: APP_ENV', function () {
            $env = (string) config('app.env');

            if ($env === 'production') {
                return [true, 'APP_ENV = production'];
            }

            return [false, "APP_ENV = {$env} (expected: production)", 'critical'];
        });

        $this->check('ENV: APP_DEBUG', function () {
            $debug = (bool) config('app.debug');

            if (! $debug) {
                return [true, 'APP_DEBUG = false'];
            }

            return [false, 'APP_DEBUG = true (must be false in production)', 'critical'];
        });

        $this->check('ENV: APP_KEY', function () {
            $key = $this->normalizeConfigValue(config('app.key'));

            if ($key !== null && strlen($key) >= 32) {
                return [true, 'APP_KEY is set'];
            }

            return [false, 'APP_KEY is missing or too short', 'critical'];
        });

        $this->check('Database Connection', function () {
            try {
                DB::connection()->getPdo();

                return [true, 'Connected to: ' . DB::connection()->getDatabaseName()];
            } catch (\Throwable $e) {
                return [false, 'Cannot connect: ' . substr($e->getMessage(), 0, 120), 'critical'];
            }
        });

        foreach (['DB_HOST', 'DB_DATABASE', 'DB_USERNAME'] as $key) {
            $this->check("ENV: {$key}", function () use ($key) {
                $value = $this->configuredCriticalValue($key);

                if ($value !== null) {
                    return [true, "{$key} is set"];
                }

                return [false, "{$key} is not set", 'critical'];
            });
        }

        foreach (['MAIL_MAILER', 'QUEUE_CONNECTION', 'CACHE_DRIVER'] as $key) {
            $this->check("ENV: {$key} (optional)", function () use ($key) {
                $value = $this->configuredOptionalValue($key);

                if ($value !== null) {
                    return [true, "{$key} = {$value}"];
                }

                return [false, "{$key} is not set (recommended)", 'warning'];
            });
        }

        $this->check('Feature Flags Config', function () {
            $flags = config('features', []);

            if (! is_array($flags) || $flags === []) {
                return [false, 'config/features.php is empty or unavailable', 'warning'];
            }

            return [true, count($flags) . ' flags loaded, ' . count(array_filter($flags)) . ' enabled'];
        });

        $this->check('Sandbox Mode', function () {
            $sandbox = config('features.sandbox_mode');
            $environment = (string) config('app.env');

            if ($sandbox === true && $environment === 'production') {
                return [false, 'sandbox_mode is enabled in production', 'warning'];
            }

            return [true, 'Sandbox mode is appropriate for the current environment'];
        });

        $this->check('Maintenance Mode', function () {
            if (app()->isDownForMaintenance()) {
                return [false, 'Application is in maintenance mode', 'warning'];
            }

            return [true, 'Application is not in maintenance mode'];
        });

        $this->check('Recent Backup (24h)', function () {
            $backupDir = storage_path('app/backups');

            if (! is_dir($backupDir)) {
                return [false, "No backups directory found at {$backupDir}", 'warning'];
            }

            $files = glob($backupDir . DIRECTORY_SEPARATOR . 'backup_*.sql*') ?: [];
            if ($files === []) {
                return [false, 'No backup files found', 'warning'];
            }

            $latestFile = null;
            $latestTimestamp = 0;

            foreach ($files as $file) {
                $timestamp = (int) filemtime($file);

                if ($timestamp > $latestTimestamp) {
                    $latestTimestamp = $timestamp;
                    $latestFile = basename($file);
                }
            }

            $hoursAgo = round((time() - $latestTimestamp) / 3600, 1);

            if ($hoursAgo <= 24) {
                return [true, "Latest backup: {$latestFile} ({$hoursAgo}h ago)"];
            }

            return [false, "Latest backup is {$hoursAgo}h old (max: 24h)", 'warning'];
        });

        $this->check('Circuit Breakers', function () {
            $circuitBreakerClass = 'App\\Services\\Observability\\CircuitBreaker';

            if (! class_exists($circuitBreakerClass) || ! method_exists($circuitBreakerClass, 'allStatuses')) {
                return [false, 'Circuit breaker service is not available in this build', 'warning'];
            }

            try {
                $statuses = $circuitBreakerClass::allStatuses();
                $tripped = array_filter($statuses, static fn ($status) => ($status['state'] ?? null) === 'open');

                if ($tripped !== []) {
                    return [false, 'Tripped circuit breakers: ' . implode(', ', array_keys($tripped)), 'warning'];
                }

                return [true, count($statuses) . ' services monitored, all healthy'];
            } catch (\Throwable $e) {
                return [false, 'Cannot inspect circuit breakers: ' . $e->getMessage(), 'warning'];
            }
        });

        $this->check('Storage Writable', function () {
            $directories = [
                storage_path('logs'),
                storage_path('app'),
                storage_path('framework/cache'),
            ];

            $unwritable = [];

            foreach ($directories as $directory) {
                if (! is_writable($directory)) {
                    $unwritable[] = $directory;
                }
            }

            if ($unwritable !== []) {
                return [false, 'Not writable: ' . implode(', ', $unwritable), 'critical'];
            }

            return [true, 'All storage directories are writable'];
        });

        $this->check('Queue Connection', function () {
            $driver = (string) config('queue.default');

            if ($driver === 'sync' && config('app.env') === 'production') {
                return [false, "Queue driver is 'sync' in production", 'warning'];
            }

            return [true, "Queue driver: {$driver}"];
        });

        $payload = [
            'passed' => $this->passed,
            'warnings' => $this->warnings,
            'failed' => $this->failed,
            'go_live' => $this->failed === 0 && (! $this->option('strict') || $this->warnings === 0),
            'checks' => $this->results,
        ];

        $this->line('');
        $this->line(str_repeat('=', 48));
        $this->line("  RESULTS: {$this->passed} passed, {$this->warnings} warnings, {$this->failed} failed");
        $this->line(str_repeat('=', 48));

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        if ($this->failed > 0) {
            $this->error('');
            $this->error('GO-LIVE BLOCKED: fix critical failures first.');

            return self::FAILURE;
        }

        if ($this->warnings > 0 && $this->option('strict')) {
            $this->warn('');
            $this->warn('GO-LIVE BLOCKED (strict mode): resolve all warnings.');

            return self::FAILURE;
        }

        if ($this->warnings > 0) {
            $this->warn('');
            $this->warn('GO-LIVE POSSIBLE WITH WARNINGS: review before deploying.');
        } else {
            $this->info('');
            $this->info('ALL CHECKS PASSED: ready for go-live.');
        }

        return self::SUCCESS;
    }

    private function check(string $name, callable $callback): void
    {
        try {
            [$passed, $message, $severity] = array_pad($callback(), 3, null);
        } catch (\Throwable $e) {
            $passed = false;
            $message = 'Exception: ' . $e->getMessage();
            $severity = 'critical';
        }

        $severity = $severity ?? ($passed ? 'ok' : 'critical');

        if ($passed) {
            $this->passed++;
            $prefix = '[OK]';
        } elseif ($severity === 'warning') {
            $this->warnings++;
            $prefix = '[WARN]';
        } else {
            $this->failed++;
            $prefix = '[FAIL]';
        }

        $this->line("{$prefix} {$name}: {$message}");

        $this->results[] = [
            'check' => $name,
            'passed' => $passed,
            'severity' => $severity,
            'message' => $message,
        ];
    }

    private function configuredCriticalValue(string $key): ?string
    {
        $connection = (string) config('database.default');

        $map = [
            'DB_HOST' => config("database.connections.{$connection}.host"),
            'DB_DATABASE' => config("database.connections.{$connection}.database"),
            'DB_USERNAME' => config("database.connections.{$connection}.username"),
        ];

        return $this->normalizeConfigValue($map[$key] ?? null);
    }

    private function configuredOptionalValue(string $key): ?string
    {
        $map = [
            'MAIL_MAILER' => config('mail.default'),
            'QUEUE_CONNECTION' => config('queue.default'),
            'CACHE_DRIVER' => config('cache.default'),
        ];

        return $this->normalizeConfigValue($map[$key] ?? null);
    }

    private function normalizeConfigValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
