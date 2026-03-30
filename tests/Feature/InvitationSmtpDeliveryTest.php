<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\Account;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\InvitationService;
use Tests\Support\LocalSmtpSink;
use Tests\TestCase;

class InvitationSmtpDeliveryTest extends TestCase
{
    private ?LocalSmtpSink $smtpSink = null;

    protected function tearDown(): void
    {
        $this->smtpSink?->close();

        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function creating_an_invitation_sends_a_real_email_via_the_stored_smtp_transport(): void
    {
        $sink = $this->startSmtpSink();
        $this->configureStoredSmtp($sink->port());

        $account = Account::factory()->organization()->create([
            'name' => 'Acme Logistics',
            'status' => 'active',
        ]);
        $owner = $this->createInviter($account, 'owner@acme.test');
        $role = Role::factory()->create([
            'account_id' => $account->id,
            'name' => 'operations_manager',
            'display_name' => 'Operations Manager',
        ]);

        $invitation = app(InvitationService::class)->createInvitation([
            'email' => 'invitee@example.test',
            'role_id' => $role->id,
        ], $owner);

        $messages = $sink->waitForMessages(1);

        $this->assertCount(1, $messages);
        $this->assertStringContainsString('invitee@example.test', $messages[0]);
        $this->assertStringContainsString('Acme Logistics', $messages[0]);
        $this->assertStringContainsString((string) $invitation->token, $messages[0]);
        $this->assertStringContainsString('/api/v1/invitations/preview/' . $invitation->token, $messages[0]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function duplicate_pending_invitation_attempts_do_not_send_a_second_email(): void
    {
        $sink = $this->startSmtpSink();
        $this->configureStoredSmtp($sink->port());

        $account = Account::factory()->organization()->create(['status' => 'active']);
        $owner = $this->createInviter($account, 'owner@example.test');

        app(InvitationService::class)->createInvitation([
            'email' => 'dup@example.test',
        ], $owner);

        $sink->waitForMessages(1);

        try {
            app(InvitationService::class)->createInvitation([
                'email' => 'dup@example.test',
            ], $owner);

            $this->fail('Expected duplicate invitation creation to fail.');
        } catch (BusinessException $exception) {
            $this->assertSame(1, count($sink->messages()));
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function resending_an_invitation_sends_exactly_one_additional_email_with_the_new_token(): void
    {
        $sink = $this->startSmtpSink();
        $this->configureStoredSmtp($sink->port());

        $account = Account::factory()->organization()->create([
            'name' => 'Resend Logistics',
            'status' => 'active',
        ]);
        $owner = $this->createInviter($account, 'owner@resend.test');

        $invitation = app(InvitationService::class)->createInvitation([
            'email' => 'resend@example.test',
        ], $owner);

        $firstMessages = $sink->waitForMessages(1);
        $firstToken = (string) $invitation->token;

        $resent = app(InvitationService::class)->resendInvitation((string) $invitation->id, $owner);
        $messages = $sink->waitForMessages(2);

        $this->assertCount(2, $messages);
        $this->assertStringContainsString($firstToken, $firstMessages[0]);
        $this->assertNotSame($firstToken, (string) $resent->token);
        $this->assertStringContainsString((string) $resent->token, $messages[1]);
        $this->assertStringNotContainsString($firstToken, $messages[1]);
        $this->assertSame(2, (int) $resent->send_count);
    }

    private function createInviter(Account $account, string $email): User
    {
        $user = User::factory()->owner()->create([
            'account_id' => $account->id,
            'email' => $email,
            'user_type' => 'external',
            'status' => 'active',
        ]);

        $this->grantTenantPermissions($user, ['users.invite', 'users.read', 'account.read'], 'p3_inviter');

        return $user;
    }

    private function startSmtpSink(): LocalSmtpSink
    {
        $this->smtpSink = LocalSmtpSink::start();

        return $this->smtpSink;
    }

    private function configureStoredSmtp(int $port): void
    {
        SystemSetting::setValue('smtp', 'enabled', 'true', 'boolean');
        SystemSetting::setValue('smtp', 'host', '127.0.0.1');
        SystemSetting::setValue('smtp', 'port', $port, 'integer');
        SystemSetting::setValue('smtp', 'encryption', 'none');
        SystemSetting::setValue('smtp', 'from_name', 'CBEX Ops');
        SystemSetting::setValue('smtp', 'from_address', 'ops@example.test');
        SystemSetting::setValue('smtp', 'timeout', 15, 'integer');
    }
}
