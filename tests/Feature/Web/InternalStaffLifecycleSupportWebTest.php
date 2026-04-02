<?php

namespace Tests\Feature\Web;

use App\Mail\PasswordResetMail;
use App\Models\User;
use App\Services\SmtpSettingsService;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Concerns\AssertsSchemaAwareAuditLogs;
use Tests\TestCase;

class InternalStaffLifecycleSupportWebTest extends TestCase
{
    use AssertsSchemaAwareAuditLogs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);
    }

    protected function tearDown(): void
    {
        try {
            Mockery::close();
        } finally {
            parent::tearDown();
        }
    }

    #[Test]
    public function super_admin_can_deactivate_and_activate_internal_staff_user(): void
    {
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');
        $target = $this->userByEmail('e2e.internal.carrier_manager@example.test');

        if (Schema::hasTable('personal_access_tokens')) {
            $target->createToken('i3c-lifecycle-token');
        }

        $deactivate = $this->actingAs($actor, 'web')->post(route('internal.staff.deactivate', $target));
        $deactivate->assertRedirect(route('internal.staff.show', $target));
        $deactivate->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertSame('disabled', (string) $target->status);

        if (Schema::hasTable('personal_access_tokens')) {
            $this->assertDatabaseMissing('personal_access_tokens', [
                'tokenable_id' => (string) $target->id,
            ]);
        }

        $this->assertAuditLogRecorded('user.deactivated', (string) $actor->id, null, 'User', (string) $target->id);

        $activate = $this->actingAs($actor, 'web')->post(route('internal.staff.activate', $target));
        $activate->assertRedirect(route('internal.staff.show', $target));
        $activate->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertSame('active', (string) $target->status);
        $this->assertAuditLogRecorded('user.activated', (string) $actor->id, null, 'User', (string) $target->id);
    }

    #[Test]
    public function super_admin_can_suspend_and_unsuspend_internal_staff_user(): void
    {
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');
        $target = $this->userByEmail('e2e.internal.support@example.test');

        if (Schema::hasTable('personal_access_tokens')) {
            $target->createToken('i3c-suspend-token');
        }

        $suspend = $this->actingAs($actor, 'web')->post(route('internal.staff.suspend', $target));
        $suspend->assertRedirect(route('internal.staff.show', $target));
        $suspend->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertSame('suspended', (string) $target->status);

        if (Schema::hasTable('personal_access_tokens')) {
            $this->assertDatabaseMissing('personal_access_tokens', [
                'tokenable_id' => (string) $target->id,
            ]);
        }

        $this->assertAuditLogRecorded('user.suspended', (string) $actor->id, null, 'User', (string) $target->id);

        $unsuspend = $this->actingAs($actor, 'web')->post(route('internal.staff.unsuspend', $target));
        $unsuspend->assertRedirect(route('internal.staff.show', $target));
        $unsuspend->assertSessionHasNoErrors();

        $target->refresh();
        $this->assertSame('active', (string) $target->status);
        $this->assertAuditLogRecorded('user.unsuspended', (string) $actor->id, null, 'User', (string) $target->id);
    }

    #[Test]
    public function super_admin_can_trigger_password_reset_for_internal_staff_user(): void
    {
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');
        $target = $this->userByEmail('e2e.internal.support@example.test');
        $smtp = Mockery::mock(SmtpSettingsService::class);
        $smtp->shouldReceive('sendMailable')
            ->once()
            ->with(
                $target->email,
                Mockery::type(PasswordResetMail::class),
            )
            ->andReturn('i3c-password-reset-message-id');
        $this->app->instance(SmtpSettingsService::class, $smtp);

        $response = $this->actingAs($actor, 'web')->post(route('internal.staff.password-reset', $target));

        $response->assertRedirect(route('internal.staff.show', $target));
        $response->assertSessionHasNoErrors();

        if (Schema::hasTable('password_reset_tokens')) {
            $this->assertDatabaseHas('password_reset_tokens', [
                'email' => $target->email,
            ]);
        }

        $this->assertAuditLogRecorded('auth.password_reset_link_sent', (string) $actor->id, null, 'User', (string) $target->id);
    }

    #[Test]
    public function password_reset_transport_failures_are_returned_as_safe_staff_errors(): void
    {
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');
        $target = $this->userByEmail('e2e.internal.support@example.test');
        $smtp = Mockery::mock(SmtpSettingsService::class);
        $smtp->shouldReceive('sendMailable')
            ->once()
            ->andThrow(new RuntimeException('SMTP relay rejected the message.'));
        $smtp->shouldReceive('providerName')
            ->once()
            ->andReturn('smtp');
        $this->app->instance(SmtpSettingsService::class, $smtp);

        $response = $this->actingAs($actor, 'web')
            ->from(route('internal.staff.show', $target))
            ->post(route('internal.staff.password-reset', $target));

        $response->assertRedirect(route('internal.staff.show', $target));
        $response->assertSessionHasErrors('staff');
        $response->assertSessionMissing('success');
        $this->assertAuditLogRecorded('auth.password_reset_link_failed', (string) $actor->id, null, 'User', (string) $target->id);
    }

    #[Test]
    public function support_cannot_submit_staff_lifecycle_or_reset_routes(): void
    {
        $support = $this->userByEmail('e2e.internal.support@example.test');
        $target = $this->userByEmail('e2e.internal.carrier_manager@example.test');

        foreach ([
            'internal.staff.activate',
            'internal.staff.deactivate',
            'internal.staff.suspend',
            'internal.staff.unsuspend',
            'internal.staff.password-reset',
        ] as $routeName) {
            $this->actingAs($support, 'web')
                ->post(route($routeName, $target))
                ->assertForbidden();
        }
    }

    #[Test]
    public function external_users_are_forbidden_from_internal_staff_lifecycle_and_support_routes(): void
    {
        $externalUser = $this->userByEmail('e2e.c.organization_owner@example.test');
        $target = $this->userByEmail('e2e.internal.support@example.test');

        foreach ([
            'internal.staff.activate',
            'internal.staff.deactivate',
            'internal.staff.suspend',
            'internal.staff.unsuspend',
            'internal.staff.password-reset',
        ] as $routeName) {
            $this->actingAs($externalUser, 'web')
                ->post(route($routeName, $target))
                ->assertForbidden();
        }
    }

    #[Test]
    public function the_last_login_capable_super_admin_cannot_be_suspended_or_disabled(): void
    {
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');

        $suspend = $this->actingAs($actor, 'web')
            ->from(route('internal.staff.show', $actor))
            ->post(route('internal.staff.suspend', $actor));
        $suspend->assertRedirect(route('internal.staff.show', $actor));
        $suspend->assertSessionHasErrors('staff');

        $deactivate = $this->actingAs($actor, 'web')
            ->from(route('internal.staff.show', $actor))
            ->post(route('internal.staff.deactivate', $actor));
        $deactivate->assertRedirect(route('internal.staff.show', $actor));
        $deactivate->assertSessionHasErrors('staff');

        $actor->refresh();
        $this->assertSame('active', (string) $actor->status);
    }

    private function userByEmail(string $email): User
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('email', $email)
            ->firstOrFail();
    }
}
