<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\Invitation;
use App\Models\KycVerification;
use App\Models\User;
use App\Support\Kyc\AccountKycStatusMapper;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AssertsSchemaAwareAuditLogs;
use Tests\TestCase;

class InternalAccountsSupportActionsWebTest extends TestCase
{
    use AssertsSchemaAwareAuditLogs;
    use RefreshDatabase;

    private Account $individualAccount;
    private Account $organizationAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);

        $this->individualAccount = $this->accountBySlug('e2e-account-a');
        $this->organizationAccount = $this->accountBySlug('e2e-account-c');

        $this->seedSupportFixtures();
    }

    #[Test]
    public function super_admin_and_support_can_view_verification_status_and_support_actions_on_account_detail(): void
    {
        $pendingInvitation = $this->pendingInvitation();

        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $organizationDetail = $this->actingAs($user, 'web')
                ->get(route('internal.accounts.show', $this->organizationAccount))
                ->assertOk();

            $organizationDetail->assertSee('data-testid="account-verification-status-card"', false);
            $organizationDetail->assertSee('data-testid="account-support-actions-card"', false);
            $organizationDetail->assertSeeText('قيد المراجعة');
            $organizationDetail->assertSee(route('internal.accounts.password-reset', $this->organizationAccount), false);
            $organizationDetail->assertSee(route('internal.accounts.invitations.resend', [$this->organizationAccount, $pendingInvitation]), false);
            $organizationDetail->assertSeeText('إعادة إرسال التحقق ليست جزءًا من هذه المرحلة');

            $individualDetail = $this->actingAs($user, 'web')
                ->get(route('internal.accounts.show', $this->individualAccount))
                ->assertOk();

            $individualDetail->assertSee(route('internal.accounts.password-reset', $this->individualAccount), false);
            $individualDetail->assertDontSee('data-testid="organization-pending-invitations-card"', false);
            $individualDetail->assertSeeText('هذا حساب فردي');
        }

        $this->assertFalse(app('router')->has('internal.accounts.verification.resend'));
    }

    #[Test]
    public function super_admin_and_support_can_trigger_password_reset_for_external_accounts(): void
    {
        Notification::fake();

        $cases = [
            [
                'actor' => 'e2e.internal.super_admin@example.test',
                'account' => $this->individualAccount,
                'target' => 'e2e.a.individual@example.test',
            ],
            [
                'actor' => 'e2e.internal.support@example.test',
                'account' => $this->organizationAccount,
                'target' => 'e2e.c.organization_owner@example.test',
            ],
        ];

        foreach ($cases as $case) {
            $actor = $this->userByEmail($case['actor']);
            $target = $this->userByEmail($case['target']);

            $this->actingAs($actor, 'web')
                ->post(route('internal.accounts.password-reset', $case['account']))
                ->assertRedirect(route('internal.accounts.show', $case['account']))
                ->assertSessionHasNoErrors();

            Notification::assertSentTo($target, ResetPassword::class, function (ResetPassword $notification) use ($target): bool {
                $notification->toMail($target);

                return true;
            });

            $this->assertDatabaseHas('password_reset_tokens', [
                'email' => $target->email,
            ]);

            $this->assertAuditLogRecorded(
                'account.password_reset_link_sent',
                (string) $actor->id,
                (string) $case['account']->id,
                'User',
                (string) $target->id,
            );
        }
    }

    #[Test]
    public function super_admin_and_support_can_resend_safe_pending_organization_invitations(): void
    {
        Queue::fake();

        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
        ] as $index => $email) {
            $actor = $this->userByEmail($email);
            $owner = $this->userByEmail('e2e.c.organization_owner@example.test');

            $invitation = Invitation::factory()->create([
                'account_id' => (string) $this->organizationAccount->id,
                'invited_by' => (string) $owner->id,
                'email' => sprintf('support-resend-%d@example.test', $index),
                'send_count' => 1,
            ]);

            $originalToken = (string) $invitation->token;

            $this->actingAs($actor, 'web')
                ->post(route('internal.accounts.invitations.resend', [$this->organizationAccount, $invitation]))
                ->assertRedirect(route('internal.accounts.show', $this->organizationAccount))
                ->assertSessionHasNoErrors();

            $invitation->refresh();

            $this->assertNotSame($originalToken, (string) $invitation->token);
            $this->assertSame(2, (int) $invitation->send_count);
            $this->assertSame(Invitation::STATUS_PENDING, $invitation->status);
            $this->assertNotNull($invitation->expires_at);

            $this->assertAuditLogRecorded(
                'invitation.resent',
                (string) $actor->id,
                (string) $this->organizationAccount->id,
                'Invitation',
                (string) $invitation->id,
            );
        }
    }

    #[Test]
    public function invite_resend_is_not_exposed_for_individual_accounts_or_unsafe_invitation_states(): void
    {
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');
        $owner = $this->userByEmail('e2e.c.organization_owner@example.test');

        $cancelledInvitation = Invitation::factory()->cancelled()->create([
            'account_id' => (string) $this->organizationAccount->id,
            'invited_by' => (string) $owner->id,
            'email' => 'cancelled-invite@example.test',
        ]);

        $individualInvitation = Invitation::factory()->create([
            'account_id' => (string) $this->individualAccount->id,
            'invited_by' => (string) $this->userByEmail('e2e.a.individual@example.test')->id,
            'email' => 'individual-invite@example.test',
        ]);

        $organizationDetail = $this->actingAs($actor, 'web')
            ->get(route('internal.accounts.show', $this->organizationAccount))
            ->assertOk();

        $organizationDetail->assertDontSee(route('internal.accounts.invitations.resend', [$this->organizationAccount, $cancelledInvitation]), false);

        $this->actingAs($actor, 'web')
            ->post(route('internal.accounts.invitations.resend', [$this->organizationAccount, $cancelledInvitation]))
            ->assertRedirect()
            ->assertSessionHasErrors('account');

        $this->actingAs($actor, 'web')
            ->post(route('internal.accounts.invitations.resend', [$this->individualAccount, $individualInvitation]))
            ->assertRedirect()
            ->assertSessionHasErrors('account');
    }

    #[Test]
    public function ops_readonly_carrier_manager_and_external_users_are_forbidden_from_account_support_action_routes(): void
    {
        $pendingInvitation = $this->pendingInvitation();

        foreach ([
            'e2e.internal.ops_readonly@example.test',
            'e2e.internal.carrier_manager@example.test',
            'e2e.c.organization_owner@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $this->actingAs($user, 'web')
                ->post(route('internal.accounts.password-reset', $this->organizationAccount))
                ->assertForbidden();

            $this->actingAs($user, 'web')
                ->post(route('internal.accounts.invitations.resend', [$this->organizationAccount, $pendingInvitation]))
                ->assertForbidden();
        }
    }

    private function seedSupportFixtures(): void
    {
        $this->individualAccount->forceFill([
            'kyc_status' => AccountKycStatusMapper::fromVerificationStatus(KycVerification::STATUS_APPROVED),
        ])->save();

        $this->organizationAccount->forceFill([
            'kyc_status' => AccountKycStatusMapper::fromVerificationStatus(KycVerification::STATUS_PENDING),
        ])->save();

        KycVerification::query()->withoutGlobalScopes()->updateOrCreate(
            ['account_id' => (string) $this->individualAccount->id],
            [
                'status' => KycVerification::STATUS_APPROVED,
                'verification_type' => 'individual',
                'reviewed_at' => now()->subDay(),
                'expires_at' => now()->addMonths(6),
            ]
        );

        KycVerification::query()->withoutGlobalScopes()->updateOrCreate(
            ['account_id' => (string) $this->organizationAccount->id],
            [
                'status' => KycVerification::STATUS_PENDING,
                'verification_type' => 'organization',
                'submitted_at' => now()->subDay(),
            ]
        );
    }

    private function userByEmail(string $email): User
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('email', $email)
            ->firstOrFail();
    }

    private function accountBySlug(string $slug): Account
    {
        return Account::query()
            ->withoutGlobalScopes()
            ->where('slug', $slug)
            ->firstOrFail();
    }

    private function pendingInvitation(): Invitation
    {
        return Invitation::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $this->organizationAccount->id)
            ->where('email', 'e2e.c.pending.invite@example.test')
            ->firstOrFail();
    }
}
