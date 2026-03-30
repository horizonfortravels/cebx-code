<?php

namespace Tests\Support;

use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class LocalSmtpSink
{
    private Process $process;
    private string $outFile;
    private int $port;
    private string $buffer = '';

    public static function start(): self
    {
        $sink = new self();
        $sink->boot();

        return $sink;
    }

    public function port(): int
    {
        return $this->port;
    }

    /**
     * @return array<int, string>
     */
    public function messages(): array
    {
        if (! is_file($this->outFile)) {
            return [];
        }

        $lines = file($this->outFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (! is_array($lines)) {
            return [];
        }

        return array_values(array_filter(array_map(function (string $line): ?string {
            $decoded = json_decode($line, true);

            return is_array($decoded) && is_string($decoded['message'] ?? null)
                ? $decoded['message']
                : null;
        }, $lines)));
    }

    /**
     * @return array<int, string>
     */
    public function waitForMessages(int $count, int $timeoutMs = 5000): array
    {
        $deadline = microtime(true) + ($timeoutMs / 1000);

        do {
            $messages = $this->messages();
            if (count($messages) >= $count) {
                return $messages;
            }

            usleep(50_000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException("Timed out waiting for {$count} SMTP message(s).");
    }

    public function close(): void
    {
        if (isset($this->process) && $this->process->isRunning()) {
            $this->process->stop(2);
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    private function boot(): void
    {
        $this->outFile = storage_path('framework/testing/' . Str::uuid() . '-smtp-sink.jsonl');
        $script = base_path('tests/Support/local-smtp-sink.js');

        $this->process = new Process(['node', $script, $this->outFile], base_path());
        $this->process->start();

        $deadline = microtime(true) + 10;

        do {
            $this->buffer .= $this->process->getIncrementalOutput();
            $this->buffer .= $this->process->getIncrementalErrorOutput();

            $port = $this->extractPort($this->buffer);
            if ($port !== null) {
                $this->port = $port;

                return;
            }

            if (! $this->process->isRunning()) {
                break;
            }

            usleep(50_000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('Failed to start the local SMTP sink process.');
    }

    private function extractPort(string $output): ?int
    {
        $lines = preg_split("/\r\n|\n|\r/", $output) ?: [];

        foreach ($lines as $line) {
            $decoded = json_decode(trim($line), true);
            if (is_array($decoded) && isset($decoded['port'])) {
                return (int) $decoded['port'];
            }
        }

        return null;
    }
}
