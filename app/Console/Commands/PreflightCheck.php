<?php

namespace App\Console\Commands;

use App\Services\Observability\CircuitBreaker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * PreflightCheck — F-1: Go-Live Safeguards
 *
 * Runs pre-flight validation before going live.
 * Aborts with clear message if any critical check fails.
 *
 * Usage:
 *   php artisan preflight:check              # Run all checks
 *   php artisan preflight:check --strict     # Fail on warnings too
 *   php artisan preflight:check --json       # Output as JSON
 *
 * Checks performed:
 *   1. Environment sanity (APP_ENV, APP_DEBUG, APP_KEY)
 *   2. Database connectivity
 *   3. Critical ENV variables
 *   4. Feature flags state
 *   5. Backup existence (last 24h)
 *   6. Circuit breaker states
 *   7. Storage permissions
 *   8. Queue health
 *
 * Returns exit code 0 (pass) or 1 (fail).
 */
class PreflightCheck extends Command
{
    protected $signature = 'preflight:check
        {--strict : Treat warnings as failures}
        {--json : Output as JSON}';

    protected $description = 'Run pre-flight checks before go-live';

    private array $results = [];
    private int $passed  = 0;
    private int $warnings = 0;
    private int $failed  = 0;

    public function handle(): int
    {
        $this->info('');
        $this->info('═══════════════════════════════════════');
        $this->info('  PRE-FLIGHT CHECK — Go-Live Safeguards');
        $this->info('═══════════════════════════════════════');
        $this->info('');

        // ── 1. Environment Sanity ────────────────────────────
        $this->check('ENV: APP_ENV', function () {
            $env = config('app.env');
            if ($env === 'production') return [true, "APP_ENV = production ✓"];
            return [false, "APP_ENV = {$env} (expected: production)", 'critical'];
        });

        $this->check('ENV: APP_DEBUG', function () {
            $debug = config('app.debug');
            if (!$debug) return [true, "APP_DEBUG = false ✓"];
            return [false, "APP_DEBUG = true — MUST be false in production", 'critical'];
        });

        $this->check('ENV: APP_KEY', function () {
            $key = config('app.key');
            if (!empty($key) && strlen($key) >= 32) return [true, "APP_KEY is set ✓"];
            return [false, "APP_KEY is missing or too short", 'critical'];
        });

        // ── 2. Database Connectivity ─────────────────────────
        $this->check('Database Connection', function () {
            try {
                DB::connection()->getPdo();
                $dbName = DB::connection()->getDatabaseName();
                return [true, "Connected to: {$dbName} ✓"];
            } catch (\Throwable $e) {
                return [false, "Cannot connect: " . substr($e->getMessage(), 0, 100), 'critical'];
            }
        });

        // ── 3. Critical ENV Variables ────────────────────────
        $criticalEnvs = [
            'DB_HOST', 'DB_DATABASE', 'DB_USERNAME',
        ];

        foreach ($criticalEnvs as $envKey) {
            $this->check("ENV: {$envKey}", function () use ($envKey) {
                $val = env($envKey);
                if (!empty($val)) return [true, "{$envKey} is set ✓"];
                return [false, "{$envKey} is not set", 'critical'];
            });
        }

        // Optional but recommended
        $optionalEnvs = ['MAIL_MAILER', 'QUEUE_CONNECTION', 'CACHE_DRIVER'];
        foreach ($optionalEnvs as $envKey) {
            $this->check("ENV: {$envKey} (optional)", function () use ($envKey) {
                $val = env($envKey);
                if (!empty($val)) return [true, "{$envKey} = {$val} ✓"];
                return [false, "{$envKey} is not set (recommended)", 'warning'];
            });
        }

        // ── 4. Feature Flags State ───────────────────────────
        $this->check('Feature Flags Config', function () {
            $flags = config('features', []);
            if (empty($flags)) {
                return [false, "config/features.php not loaded or empty", 'warning'];
            }
            $count = count($flags);
            $enabled = count(array_filter($flags));
            return [true, "{$count} flags loaded, {$enabled} enabled ✓"];
        });

        $this->check('Sandbox Mode', function () {
            $sandbox = config('features.sandbox_mode', null);
            if ($sandbox === true && config('app.env') === 'production') {
                return [false, "sandbox_mode is ON in production — disable for go-live", 'warning'];
            }
            return [true, "Sandbox mode appropriate for environment ✓"];
        });

        $this->check('Maintenance Mode', function () {
            if (app()->isDownForMaintenance()) {
                return [false, "Application is in maintenance mode", 'warning'];
            }
            return [true, "Not in maintenance mode ✓"];
        });

        // ── 5. Backup Existence (last 24h) ───────────────────
        $this->check('Recent Backup (24h)', function () {
            $backupDir = storage_path('app/backups');

            if (!is_dir($backupDir)) {
                return [false, "No backups directory found at {$backupDir}", 'warning'];
            }

            $files = glob("{$backupDir}/backup_*.sql*");
            if (empty($files)) {
                return [false, "No backup files found", 'warning'];
            }

            // Check most recent
            $latestTime = 0;
            $latestFile = '';
            foreach ($files as $file) {
                $mtime = filemtime($file);
                if ($mtime > $latestTime) {
                    $latestTime = $mtime;
                    $latestFile = basename($file);
                }
            }

            $hoursAgo = round((time() - $latestTime) / 3600, 1);

            if ($hoursAgo <= 24) {
                return [true, "Latest backup: {$latestFile} ({$hoursAgo}h ago) ✓"];
            }

            return [false, "Latest backup is {$hoursAgo}h old (max: 24h). Run: php artisan db:backup", 'warning'];
        });

        // ── 6. Circuit Breaker States ────────────────────────
        $this->check('Circuit Breakers', function () {
            try {
                $statuses = CircuitBreaker::allStatuses();
                $tripped = array_filter($statuses, fn($s) => $s['state'] === 'open');

                if (!empty($tripped)) {
                    $names = implode(', ', array_keys($tripped));
                    return [false, "Tripped circuit breakers: {$names}", 'warning'];
                }

                return [true, count($statuses) . " services monitored, all healthy ✓"];
            } catch (\Throwable $e) {
                return [false, "Cannot check circuit breakers: " . $e->getMessage(), 'warning'];
            }
        });

        // ── 7. Storage Permissions ───────────────────────────
        $this->check('Storage Writable', function () {
            $dirs = [
                storage_path('logs'),
                storage_path('app'),
                storage_path('framework/cache'),
            ];

            $unwritable = [];
            foreach ($dirs as $dir) {
                if (!is_writable($dir)) {
                    $unwritable[] = $dir;
                }
            }

            if (!empty($unwritable)) {
                return [false, "Not writable: " . implode(', ', $unwritable), 'critical'];
            }

            return [true, "All storage directories writable ✓"];
        });

        // ── 8. Queue Health ──────────────────────────────────
        $this->check('Queue Connection', function () {
            $driver = config('queue.default');

            if ($driver === 'sync') {
                if (config('app.env') === 'production') {
                    return [false, "Queue driver is 'sync' — not suitable for production", 'warning'];
                }
            }

            return [true, "Queue driver: {$driver} ✓"];
        });

        // ── OUTPUT ───────────────────────────────────────────
        $this->info('');
        $this->info('═══════════════════════════════════════');
        $this->info("  RESULTS: ✅ {$this->passed} passed  ⚠️ {$this->warnings} warnings  ❌ {$this->failed} failed");
        $this->info('═══════════════════════════════════════');

        if ($this->option('json')) {
            $this->line(json_encode([
                'passed'   => $this->passed,
                'warnings' => $this->warnings,
                'failed'   => $this->failed,
                'go_live'  => $this->failed === 0 && (!$this->option('strict') || $this->warnings === 0),
                'checks'   => $this->results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        if ($this->failed > 0) {
            $this->error('');
            $this->error('❌ GO-LIVE BLOCKED — Fix critical failures before proceeding.');
            return self::FAILURE;
        }

        if ($this->warnings > 0 && $this->option('strict')) {
            $this->warn('');
            $this->warn('⚠️ GO-LIVE BLOCKED (strict mode) — Resolve all warnings.');
            return self::FAILURE;
        }

        if ($this->warnings > 0) {
            $this->warn('');
            $this->warn('⚠️ GO-LIVE POSSIBLE with warnings — Review before proceeding.');
        } else {
            $this->info('');
            $this->info('✅ ALL CHECKS PASSED — Ready for go-live!');
        }

        return self::SUCCESS;
    }

    // ═════════════════════════════════════════════════════════
    // CHECK RUNNER
    // ═════════════════════════════════════════════════════════

    private function check(string $name, callable $fn): void
    {
        try {
            [$passed, $message, $severity] = array_pad($fn(), 3, null);
        } catch (\Throwable $e) {
            $passed   = false;
            $message  = 'Exception: ' . $e->getMessage();
            $severity = 'critical';
        }

        $severity = $severity ?? ($passed ? 'ok' : 'critical');

        if ($passed) {
            $this->passed++;
            $icon = '  ✅';
        } elseif ($severity === 'warning') {
            $this->warnings++;
            $icon = '  ⚠️';
        } else {
            $this->failed++;
            $icon = '  ❌';
        }

        $this->line("{$icon} {$name}: {$message}");

        $this->results[] = [
            'check'    => $name,
            'passed'   => $passed,
            'severity' => $severity,
            'message'  => $message,
        ];
    }
}
