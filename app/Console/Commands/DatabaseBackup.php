<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DatabaseBackup — B-1: Backup & Recovery Layer
 *
 * Artisan command for automated database backups.
 * Supports MySQL/MariaDB with gzip compression.
 * Non-destructive, additive only — does NOT modify any existing data.
 *
 * Usage:
 *   php artisan db:backup                       # Full backup
 *   php artisan db:backup --tables=shipments,orders  # Specific tables
 *   php artisan db:backup --retention=14         # Keep 14 days
 *
 * Schedule (add to app/Console/Kernel.php):
 *   $schedule->command('db:backup')->dailyAt('02:00');
 *   $schedule->command('db:backup --retention=7')->weeklyOn(0, '03:00');
 *
 * ═══════════════════════════════════════════
 *  RPO / RTO Documentation
 * ═══════════════════════════════════════════
 *
 *  RPO (Recovery Point Objective): 24 hours
 *    → With daily backups, maximum data loss = 1 day.
 *    → Reduce to 1 hour by scheduling hourly.
 *
 *  RTO (Recovery Target Objective): 30 minutes
 *    → Restore via: php artisan db:restore {filename}
 *    → Compressed backups ≈ 10-30 seconds to import.
 *
 *  Storage: storage/app/backups/
 *  Naming: backup_YYYY-MM-DD_HH-MM-SS.sql.gz
 *  Retention: Configurable (default 7 days)
 * ═══════════════════════════════════════════
 */
class DatabaseBackup extends Command
{
    protected $signature = 'db:backup
        {--tables= : Comma-separated table names (empty = all)}
        {--retention=7 : Days to keep old backups}
        {--no-compress : Skip gzip compression}';

    protected $description = 'Create a database backup (MySQL/MariaDB)';

    public function handle(): int
    {
        $this->info('═══ Database Backup Started ═══');

        // ── Validate environment ─────────────────────────────
        $driver = config('database.default');
        $dbConfig = config("database.connections.{$driver}");

        if (!in_array($driver, ['mysql', 'mariadb'])) {
            $this->error("Unsupported database driver: {$driver}. Only mysql/mariadb supported.");
            return self::FAILURE;
        }

        // ── Prepare backup directory ─────────────────────────
        $backupDir = storage_path('app/backups');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $compress = !$this->option('no-compress');
        $extension = $compress ? 'sql.gz' : 'sql';
        $filename = "backup_{$timestamp}.{$extension}";
        $filepath = "{$backupDir}/{$filename}";

        // ── Build mysqldump command ──────────────────────────
        $host     = $dbConfig['host'] ?? '127.0.0.1';
        $port     = $dbConfig['port'] ?? '3306';
        $database = $dbConfig['database'] ?? '';
        $username = $dbConfig['username'] ?? '';
        $password = $dbConfig['password'] ?? '';

        if (!$database || !$username) {
            $this->error('Database name and username are required.');
            return self::FAILURE;
        }

        // Tables filter
        $tables = $this->option('tables')
            ? implode(' ', array_map('trim', explode(',', $this->option('tables'))))
            : '';

        // Build command (password via env to avoid shell exposure)
        $dumpCmd = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --single-transaction --routines --triggers --quick %s %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($database),
            $tables
        );

        if ($compress) {
            $dumpCmd .= ' | gzip';
        }

        $dumpCmd .= ' > ' . escapeshellarg($filepath);

        // ── Execute backup ───────────────────────────────────
        $this->info("Backing up database: {$database}");
        $this->info("Destination: {$filepath}");

        $startTime = microtime(true);

        // Pass password via environment (secure — never in CLI args)
        $env = ['MYSQL_PWD' => $password];
        $process = proc_open(
            $dumpCmd,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $env + $_ENV
        );

        if (!is_resource($process)) {
            $this->error('Failed to start mysqldump process.');
            Log::error('Database backup failed: could not start mysqldump');
            return self::FAILURE;
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $duration = round(microtime(true) - $startTime, 2);

        if ($exitCode !== 0) {
            $this->error("mysqldump failed (exit code: {$exitCode})");
            if ($stderr) {
                $this->error("Error: " . substr($stderr, 0, 500));
            }
            Log::error('Database backup failed', [
                'exit_code' => $exitCode,
                'duration'  => $duration,
            ]);
            // Clean up partial file
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            return self::FAILURE;
        }

        $fileSize = file_exists($filepath) ? filesize($filepath) : 0;
        $sizeHuman = $this->humanFileSize($fileSize);

        $this->info("✅ Backup complete: {$filename} ({$sizeHuman}) in {$duration}s");

        Log::info('Database backup completed', [
            'file'     => $filename,
            'size'     => $fileSize,
            'duration' => $duration,
            'tables'   => $this->option('tables') ?: 'all',
        ]);

        // ── Cleanup old backups ──────────────────────────────
        $this->cleanupOldBackups($backupDir, (int) $this->option('retention'));

        return self::SUCCESS;
    }

    /**
     * Remove backups older than retention days.
     */
    private function cleanupOldBackups(string $dir, int $retentionDays): void
    {
        $cutoff = now()->subDays($retentionDays)->timestamp;
        $removed = 0;

        foreach (glob("{$dir}/backup_*.sql*") as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->info("🗑️ Removed {$removed} backup(s) older than {$retentionDays} days");
        }
    }

    private function humanFileSize(int $bytes): string
    {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
        return $bytes . ' B';
    }
}
