<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\SystemSetting;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Throwable;

class SmtpSettingsService
{
    public const GROUP = 'smtp';

    private const KEY_ENABLED = 'enabled';
    private const KEY_HOST = 'host';
    private const KEY_PORT = 'port';
    private const KEY_ENCRYPTION = 'encryption';
    private const KEY_USERNAME = 'username';
    private const KEY_PASSWORD = 'password';
    private const KEY_FROM_NAME = 'from_name';
    private const KEY_FROM_ADDRESS = 'from_address';
    private const KEY_REPLY_TO_NAME = 'reply_to_name';
    private const KEY_REPLY_TO_ADDRESS = 'reply_to_address';
    private const KEY_TIMEOUT = 'timeout';

    public function __construct(private MailManager $mailManager)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        $username = $this->secret(self::KEY_USERNAME);
        $password = $this->secret(self::KEY_PASSWORD);

        return [
            'enabled' => $this->boolean(self::KEY_ENABLED, false),
            'host' => $this->string(self::KEY_HOST),
            'port' => $this->integer(self::KEY_PORT, 587),
            'encryption' => $this->normalizeEncryption($this->string(self::KEY_ENCRYPTION, 'tls')),
            'username_masked' => $this->maskCredential($username),
            'username_configured' => $username !== null,
            'password_configured' => $password !== null,
            'from_name' => $this->string(self::KEY_FROM_NAME, (string) config('mail.from.name', 'CBEX Shipping Gateway')),
            'from_address' => $this->string(self::KEY_FROM_ADDRESS, (string) config('mail.from.address', 'noreply@example.com')),
            'reply_to_name' => $this->string(self::KEY_REPLY_TO_NAME),
            'reply_to_address' => $this->string(self::KEY_REPLY_TO_ADDRESS),
            'timeout' => $this->integer(self::KEY_TIMEOUT, 15),
            'stored_config_complete' => $this->hasStoredConfiguration(),
            'using_stored_transport' => $this->usesStoredTransport(),
            'default_mailer' => (string) config('mail.default', 'smtp'),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function updateSettings(array $data, ?string $updatedBy = null): array
    {
        $this->setBoolean(self::KEY_ENABLED, (bool) ($data['enabled'] ?? false), $updatedBy);
        $this->setString(self::KEY_HOST, $this->normalizeHost($data['host'] ?? null), $updatedBy);
        $this->setInteger(self::KEY_PORT, (int) ($data['port'] ?? 587), $updatedBy);
        $this->setString(self::KEY_ENCRYPTION, $this->normalizeEncryption((string) ($data['encryption'] ?? 'tls')), $updatedBy);
        $this->setEncryptedIfProvided(self::KEY_USERNAME, $data['smtp_username'] ?? null, $updatedBy);
        $this->setEncryptedIfProvided(self::KEY_PASSWORD, $data['smtp_password'] ?? null, $updatedBy);
        $this->setString(self::KEY_FROM_NAME, trim((string) ($data['from_name'] ?? '')), $updatedBy);
        $this->setString(self::KEY_FROM_ADDRESS, $this->normalizeEmail($data['from_address'] ?? null), $updatedBy);
        $this->setOptionalString(self::KEY_REPLY_TO_NAME, trim((string) ($data['reply_to_name'] ?? '')), $updatedBy);
        $this->setOptionalString(self::KEY_REPLY_TO_ADDRESS, $this->normalizeEmail($data['reply_to_address'] ?? null), $updatedBy);
        $this->setInteger(self::KEY_TIMEOUT, (int) ($data['timeout'] ?? 15), $updatedBy);

        return $this->getSettings();
    }

    public function usesStoredTransport(): bool
    {
        return $this->boolean(self::KEY_ENABLED, false) && $this->hasStoredConfiguration();
    }

    public function providerName(): string
    {
        return $this->usesStoredTransport()
            ? 'smtp'
            : (string) config('mail.default', 'smtp');
    }

    public function testStoredConnection(): void
    {
        $transport = $this->buildStoredMailer()->getSymfonyTransport();

        if (! $transport instanceof SmtpTransport) {
            throw new RuntimeException('The saved mail transport is not using SMTP.');
        }

        try {
            $transport->start();
            $transport->stop();
        } catch (Throwable $exception) {
            throw $this->sanitizedTransportException(
                $exception,
                'SMTP connection test failed. Verify host, port, encryption, and credentials.'
            );
        }
    }

    public function sendStoredTestEmail(string $destination, string $actorName): ?string
    {
        $body = [
            'This is a CBEX SMTP test email.',
            'Sent at: ' . now()->toIso8601String(),
        ];

        if (trim($actorName) !== '') {
            $body[] = 'Requested by: ' . trim($actorName);
        }

        try {
            $sent = $this->buildStoredMailer()->raw(
                implode("\n", $body),
                function ($message) use ($destination): void {
                    $message->to($destination);
                    $message->subject('CBEX SMTP Test');
                }
            );
        } catch (Throwable $exception) {
            throw $this->sanitizedTransportException(
                $exception,
                'SMTP test email failed. Verify the saved settings and recipient address.'
            );
        }

        return $sent?->getMessageId();
    }

    public function sendNotification(Notification $notification): ?string
    {
        $destination = trim((string) ($notification->destination ?? ''));
        if ($destination === '') {
            throw new RuntimeException('Notification destination is missing.');
        }

        $subject = trim((string) ($notification->subject ?? 'CBEX Notification'));
        $body = trim((string) ($notification->body ?? 'Notification'));

        try {
            $sent = $this->resolveMailer()->raw(
                $body,
                function ($message) use ($destination, $subject): void {
                    $message->to($destination);
                    $message->subject($subject !== '' ? $subject : 'CBEX Notification');
                }
            );
        } catch (Throwable $exception) {
            throw $this->sanitizedTransportException(
                $exception,
                'Email transport failed. Verify SMTP settings and connectivity.'
            );
        }

        return $sent?->getMessageId();
    }

    public function buildStoredMailer(): Mailer
    {
        if (! $this->hasStoredConfiguration()) {
            throw new RuntimeException('Saved SMTP settings are incomplete.');
        }

        $mailer = $this->mailManager->build([
            'name' => 'stored-smtp',
            'transport' => 'smtp',
            'host' => $this->string(self::KEY_HOST),
            'port' => $this->integer(self::KEY_PORT, 587),
            'username' => $this->secret(self::KEY_USERNAME),
            'password' => $this->secret(self::KEY_PASSWORD),
            'timeout' => $this->integer(self::KEY_TIMEOUT, 15),
            'scheme' => $this->schemeFor($this->normalizeEncryption($this->string(self::KEY_ENCRYPTION, 'tls'))),
            'auto_tls' => $this->normalizeEncryption($this->string(self::KEY_ENCRYPTION, 'tls')) !== 'none',
            'require_tls' => $this->normalizeEncryption($this->string(self::KEY_ENCRYPTION, 'tls')) === 'tls',
        ]);

        $mailer->alwaysFrom(
            $this->string(self::KEY_FROM_ADDRESS),
            $this->string(self::KEY_FROM_NAME)
        );

        $replyTo = $this->replyToConfig();
        if ($replyTo !== null) {
            $mailer->alwaysReplyTo($replyTo['address'], $replyTo['name']);
        }

        return $mailer;
    }

    private function resolveMailer(): Mailer
    {
        return $this->usesStoredTransport()
            ? $this->buildStoredMailer()
            : $this->mailManager->mailer();
    }

    private function hasStoredConfiguration(): bool
    {
        return $this->string(self::KEY_HOST) !== ''
            && $this->integer(self::KEY_PORT, 0) > 0
            && $this->string(self::KEY_FROM_NAME) !== ''
            && $this->string(self::KEY_FROM_ADDRESS) !== '';
    }

    /**
     * @return array{address: string, name: string}|null
     */
    private function replyToConfig(): ?array
    {
        $address = $this->string(self::KEY_REPLY_TO_ADDRESS);
        if ($address === '') {
            return null;
        }

        return [
            'address' => $address,
            'name' => $this->string(self::KEY_REPLY_TO_NAME),
        ];
    }

    private function normalizeEncryption(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'ssl' => 'ssl',
            'none' => 'none',
            default => 'tls',
        };
    }

    private function schemeFor(string $encryption): string
    {
        return $encryption === 'ssl' ? 'smtps' : 'smtp';
    }

    private function setting(string $key): ?SystemSetting
    {
        return SystemSetting::query()
            ->where('group', self::GROUP)
            ->where('key', $key)
            ->first();
    }

    private function string(string $key, string $default = ''): string
    {
        return trim((string) SystemSetting::getValue(self::GROUP, $key, $default));
    }

    private function integer(string $key, int $default = 0): int
    {
        return (int) SystemSetting::getValue(self::GROUP, $key, $default);
    }

    private function boolean(string $key, bool $default = false): bool
    {
        return (bool) SystemSetting::getValue(self::GROUP, $key, $default);
    }

    private function secret(string $key): ?string
    {
        $setting = $this->setting($key);
        if (! $setting instanceof SystemSetting) {
            return null;
        }

        $value = trim((string) $setting->getTypedValue());

        return $value === '' ? null : $value;
    }

    private function setString(string $key, string $value, ?string $updatedBy): void
    {
        SystemSetting::setValue(self::GROUP, $key, $value, 'string', $updatedBy);
    }

    private function setOptionalString(string $key, string $value, ?string $updatedBy): void
    {
        if ($value === '') {
            SystemSetting::query()
                ->where('group', self::GROUP)
                ->where('key', $key)
                ->delete();

            return;
        }

        SystemSetting::setValue(self::GROUP, $key, $value, 'string', $updatedBy);
    }

    private function setInteger(string $key, int $value, ?string $updatedBy): void
    {
        SystemSetting::setValue(self::GROUP, $key, $value, 'integer', $updatedBy);
    }

    private function setBoolean(string $key, bool $value, ?string $updatedBy): void
    {
        SystemSetting::setValue(self::GROUP, $key, $value ? 'true' : 'false', 'boolean', $updatedBy);
    }

    private function setEncryptedIfProvided(string $key, mixed $value, ?string $updatedBy): void
    {
        $resolved = trim((string) $value);
        if ($resolved === '') {
            return;
        }

        SystemSetting::setValue(self::GROUP, $key, $resolved, 'encrypted', $updatedBy);
    }

    private function normalizeHost(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }

    private function normalizeEmail(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }

    private function maskCredential(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (str_contains($value, '@')) {
            return DataMaskingService::maskEmail($value);
        }

        if (mb_strlen($value) <= 2) {
            return mb_substr($value, 0, 1) . '**';
        }

        return mb_substr($value, 0, 2) . str_repeat('*', max(2, mb_strlen($value) - 2));
    }

    private function sanitizedTransportException(Throwable $exception, string $safeMessage): RuntimeException
    {
        Log::warning('smtp.transport_failed', [
            'host' => $this->string(self::KEY_HOST),
            'port' => $this->integer(self::KEY_PORT, 0),
            'encryption' => $this->string(self::KEY_ENCRYPTION, 'tls'),
            'exception_class' => $exception::class,
            'exception_code' => (string) $exception->getCode(),
        ]);

        return new RuntimeException($safeMessage, 0, $exception);
    }
}
