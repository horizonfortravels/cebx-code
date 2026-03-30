<?php

namespace Tests\Unit;

use App\Models\Notification;
use App\Models\SystemSetting;
use App\Services\SmtpSettingsService;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Tests\TestCase;

class SmtpSettingsServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function enabled_settings_without_auth_still_count_as_a_complete_stored_transport(): void
    {
        SystemSetting::setValue('smtp', 'enabled', 'true', 'boolean');
        SystemSetting::setValue('smtp', 'host', '127.0.0.1');
        SystemSetting::setValue('smtp', 'port', 2525, 'integer');
        SystemSetting::setValue('smtp', 'encryption', 'none');
        SystemSetting::setValue('smtp', 'from_name', 'CBEX Ops');
        SystemSetting::setValue('smtp', 'from_address', 'ops@example.test');

        $service = $this->app->make(SmtpSettingsService::class);

        $this->assertTrue($service->usesStoredTransport());
        $this->assertSame('smtp', $service->providerName());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function send_notification_uses_the_runtime_stored_mailer_when_it_is_enabled(): void
    {
        SystemSetting::setValue('smtp', 'enabled', 'true', 'boolean');
        SystemSetting::setValue('smtp', 'host', '127.0.0.1');
        SystemSetting::setValue('smtp', 'port', 2525, 'integer');
        SystemSetting::setValue('smtp', 'encryption', 'none');
        SystemSetting::setValue('smtp', 'from_name', 'CBEX Ops');
        SystemSetting::setValue('smtp', 'from_address', 'ops@example.test');

        $sentMessage = Mockery::mock(SentMessage::class);
        $sentMessage->shouldReceive('getMessageId')->andReturn('notification-message-id');

        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('alwaysFrom')->once()->with('ops@example.test', 'CBEX Ops');
        $mailer->shouldReceive('raw')->once()->withArgs(function (string $body, $callback): bool {
            return $body === 'Shipment delivered' && is_callable($callback);
        })->andReturn($sentMessage);

        $mailManager = Mockery::mock(MailManager::class);
        $mailManager->shouldReceive('build')->once()->andReturn($mailer);

        $this->app->instance(MailManager::class, $mailManager);

        $service = $this->app->make(SmtpSettingsService::class);
        $notification = new Notification([
            'destination' => 'customer@example.test',
            'subject' => 'Shipment update',
            'body' => 'Shipment delivered',
        ]);

        $this->assertSame('notification-message-id', $service->sendNotification($notification));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function build_stored_mailer_applies_from_and_reply_to_addresses(): void
    {
        $this->seedStoredSettings();

        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('alwaysFrom')
            ->once()
            ->with('noreply@cbex.test', 'CBEX Platform');
        $mailer->shouldReceive('alwaysReplyTo')
            ->once()
            ->with('support@cbex.test', 'Support Desk');

        $mailManager = Mockery::mock(MailManager::class);
        $mailManager->shouldReceive('build')
            ->once()
            ->with(Mockery::on(function (array $config): bool {
                return $config['transport'] === 'smtp'
                    && $config['host'] === 'smtp.internal.test'
                    && $config['port'] === 2525
                    && $config['username'] === 'mailer-user'
                    && $config['password'] === 'super-secret-pass'
                    && $config['timeout'] === 20;
            }))
            ->andReturn($mailer);

        $this->app->instance(MailManager::class, $mailManager);

        $service = $this->app->make(SmtpSettingsService::class);

        $this->assertSame($mailer, $service->buildStoredMailer());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function stored_connection_failures_are_sanitized_in_user_message_and_logs(): void
    {
        $this->seedStoredSettings();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                $encoded = json_encode($context);

                return $message === 'smtp.transport_failed'
                    && ($context['host'] ?? null) === 'smtp.internal.test'
                    && ! str_contains((string) $encoded, 'mailer-user')
                    && ! str_contains((string) $encoded, 'super-secret-pass');
            });

        $transport = Mockery::mock(SmtpTransport::class);
        $transport->shouldReceive('start')
            ->once()
            ->andThrow(new RuntimeException('AUTH failed for mailer-user / super-secret-pass'));
        $transport->shouldReceive('stop')->never();

        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('alwaysFrom')->once();
        $mailer->shouldReceive('alwaysReplyTo')->once();
        $mailer->shouldReceive('getSymfonyTransport')->once()->andReturn($transport);

        $mailManager = Mockery::mock(MailManager::class);
        $mailManager->shouldReceive('build')->once()->andReturn($mailer);

        $this->app->instance(MailManager::class, $mailManager);

        $service = $this->app->make(SmtpSettingsService::class);

        try {
            $service->testStoredConnection();
            $this->fail('Expected SMTP connection test to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'SMTP connection test failed. Verify host, port, encryption, and credentials.',
                $exception->getMessage()
            );
            $this->assertStringNotContainsString('mailer-user', $exception->getMessage());
            $this->assertStringNotContainsString('super-secret-pass', $exception->getMessage());
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function send_notification_falls_back_to_default_mailer_when_stored_smtp_is_disabled(): void
    {
        SystemSetting::setValue('smtp', 'enabled', 'false', 'boolean');

        $sentMessage = Mockery::mock(SentMessage::class);
        $sentMessage->shouldReceive('getMessageId')->andReturn('fallback-message-id');

        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('raw')
            ->once()
            ->withArgs(function (string $body, $callback): bool {
                return $body === 'Shipment delivered' && is_callable($callback);
            })
            ->andReturn($sentMessage);

        $mailManager = Mockery::mock(MailManager::class);
        $mailManager->shouldReceive('mailer')->once()->andReturn($mailer);

        $this->app->instance(MailManager::class, $mailManager);

        $notification = new Notification([
            'destination' => 'ops@example.test',
            'subject' => 'Delivery update',
            'body' => 'Shipment delivered',
        ]);

        $service = $this->app->make(SmtpSettingsService::class);

        $this->assertSame('fallback-message-id', $service->sendNotification($notification));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function send_stored_test_email_uses_saved_mailer_and_returns_message_id(): void
    {
        $this->seedStoredSettings();

        $sentMessage = Mockery::mock(SentMessage::class);
        $sentMessage->shouldReceive('getMessageId')->andReturn('stored-test-message-id');

        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('alwaysFrom')->once();
        $mailer->shouldReceive('alwaysReplyTo')->once();
        $mailer->shouldReceive('raw')
            ->once()
            ->withArgs(function (string $body, $callback): bool {
                return str_contains($body, 'This is a CBEX SMTP test email.')
                    && str_contains($body, 'Requested by: Platform Ops')
                    && is_callable($callback);
            })
            ->andReturn($sentMessage);

        $mailManager = Mockery::mock(MailManager::class);
        $mailManager->shouldReceive('build')->once()->andReturn($mailer);

        $this->app->instance(MailManager::class, $mailManager);

        $service = $this->app->make(SmtpSettingsService::class);

        $this->assertSame(
            'stored-test-message-id',
            $service->sendStoredTestEmail('ops@example.test', 'Platform Ops')
        );
    }

    private function seedStoredSettings(): void
    {
        SystemSetting::setValue('smtp', 'enabled', 'true', 'boolean');
        SystemSetting::setValue('smtp', 'host', 'smtp.internal.test');
        SystemSetting::setValue('smtp', 'port', 2525, 'integer');
        SystemSetting::setValue('smtp', 'encryption', 'none');
        SystemSetting::setValue('smtp', 'username', 'mailer-user', 'encrypted');
        SystemSetting::setValue('smtp', 'password', 'super-secret-pass', 'encrypted');
        SystemSetting::setValue('smtp', 'from_name', 'CBEX Platform');
        SystemSetting::setValue('smtp', 'from_address', 'noreply@cbex.test');
        SystemSetting::setValue('smtp', 'reply_to_name', 'Support Desk');
        SystemSetting::setValue('smtp', 'reply_to_address', 'support@cbex.test');
        SystemSetting::setValue('smtp', 'timeout', 20, 'integer');
    }
}
