<?php

namespace Tests\Feature\Web;

use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\SentMessage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalSmtpSettingsWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function internal_super_admin_can_open_smtp_settings_page(): void
    {
        $user = $this->userByEmail('e2e.internal.super_admin@example.test');

        $this->actingAs($user, 'web')
            ->get(route('internal.smtp-settings.edit'))
            ->assertOk()
            ->assertSeeText('SMTP');
    }

    #[Test]
    public function external_user_cannot_access_internal_smtp_settings_page(): void
    {
        $user = $this->userByEmail('e2e.c.organization_owner@example.test');

        $this->actingAs($user, 'web')
            ->get(route('internal.smtp-settings.edit'))
            ->assertForbidden()
            ->assertSeeText('هذه الصفحة مخصصة لفريق التشغيل الداخلي');
    }

    #[Test]
    public function internal_support_user_without_channel_permission_is_denied(): void
    {
        $user = $this->userByEmail('e2e.internal.support@example.test');

        $this->actingAs($user, 'web')
            ->get(route('internal.smtp-settings.edit'))
            ->assertForbidden()
            ->assertSeeText('هذه الصفحة ليست ضمن دورك الحالي');
    }

    #[Test]
    public function saving_smtp_settings_encrypts_secrets_and_does_not_render_plaintext_back_to_the_ui(): void
    {
        $user = $this->userByEmail('e2e.internal.super_admin@example.test');

        $this->actingAs($user, 'web')
            ->put(route('internal.smtp-settings.update'), [
                'enabled' => '1',
                'host' => 'smtp.example.test',
                'port' => '587',
                'encryption' => 'tls',
                'smtp_username' => 'mailer@example.test',
                'smtp_password' => 'AppPassword123!',
                'from_name' => 'CBEX Ops',
                'from_address' => 'ops@example.test',
                'reply_to_name' => 'Support',
                'reply_to_address' => 'support@example.test',
                'timeout' => '20',
            ])
            ->assertRedirect(route('internal.smtp-settings.edit'));

        $usernameSetting = SystemSetting::query()->where('group', 'smtp')->where('key', 'username')->firstOrFail();
        $passwordSetting = SystemSetting::query()->where('group', 'smtp')->where('key', 'password')->firstOrFail();

        $this->assertSame('encrypted', $usernameSetting->type);
        $this->assertSame('encrypted', $passwordSetting->type);
        $this->assertNotSame('mailer@example.test', $usernameSetting->value);
        $this->assertNotSame('AppPassword123!', $passwordSetting->value);
        $this->assertSame('mailer@example.test', $usernameSetting->getTypedValue());
        $this->assertSame('AppPassword123!', $passwordSetting->getTypedValue());

        $this->actingAs($user, 'web')
            ->get(route('internal.smtp-settings.edit'))
            ->assertOk()
            ->assertDontSeeText('mailer@example.test')
            ->assertDontSeeText('AppPassword123!')
            ->assertSeeText('تم حفظ كلمة المرور');
    }

    #[Test]
    public function test_email_action_builds_runtime_mailer_from_saved_settings(): void
    {
        $user = $this->userByEmail('e2e.internal.super_admin@example.test');

        SystemSetting::setValue('smtp', 'enabled', 'true', 'boolean');
        SystemSetting::setValue('smtp', 'host', '127.0.0.1');
        SystemSetting::setValue('smtp', 'port', 2526, 'integer');
        SystemSetting::setValue('smtp', 'encryption', 'none');
        SystemSetting::setValue('smtp', 'from_name', 'CBEX Ops');
        SystemSetting::setValue('smtp', 'from_address', 'ops@example.test');
        SystemSetting::setValue('smtp', 'timeout', 15, 'integer');

        $sentMessage = Mockery::mock(SentMessage::class);
        $sentMessage->shouldReceive('getMessageId')->andReturn('smtp-test-message-id');

        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('alwaysFrom')->once()->with('ops@example.test', 'CBEX Ops');
        $mailer->shouldReceive('raw')->once()->withArgs(function (string $body, $callback): bool {
            return str_contains($body, 'CBEX SMTP test email') && is_callable($callback);
        })->andReturn($sentMessage);

        $mailManager = Mockery::mock(MailManager::class);
        $mailManager->shouldReceive('build')->once()->with(Mockery::on(function (array $config): bool {
            return $config['transport'] === 'smtp'
                && $config['host'] === '127.0.0.1'
                && $config['port'] === 2526
                && $config['scheme'] === 'smtp'
                && $config['auto_tls'] === false
                && $config['require_tls'] === false;
        }))->andReturn($mailer);

        $this->app->instance(MailManager::class, $mailManager);

        $this->actingAs($user, 'web')
            ->post(route('internal.smtp-settings.test-email'), [
                'destination' => 'probe@example.test',
            ])
            ->assertRedirect(route('internal.smtp-settings.edit'))
            ->assertSessionHas('success');
    }

    private function userByEmail(string $email): User
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('email', $email)
            ->firstOrFail();
    }
}
