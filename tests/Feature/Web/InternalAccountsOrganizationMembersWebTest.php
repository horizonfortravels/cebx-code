<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\AssertsSchemaAwareAuditLogs;
use Tests\TestCase;

class InternalAccountsOrganizationMembersWebTest extends TestCase
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
    }

    #[Test]
    public function super_admin_can_view_organization_members_and_invite_a_new_member(): void
    {
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');
        $staffRole = $this->tenantRoleByName($this->organizationAccount, 'staff');

        $detail = $this->actingAs($actor, 'web')
            ->get(route('internal.accounts.show', $this->organizationAccount))
            ->assertOk();

        $detail->assertSee('data-testid="organization-members-card"', false);
        $detail->assertSee('data-testid="organization-member-invite-form"', false);
        $detail->assertSeeText('E2E C Organization Owner');
        $detail->assertSeeText('E2E C Organization Admin');
        $detail->assertSeeText('E2E C Staff');

        $email = 'i2d-member-invite@example.test';

        $this->actingAs($actor, 'web')
            ->post(route('internal.accounts.members.invite', $this->organizationAccount), [
                'name' => 'I2D Invited Member',
                'email' => $email,
                'role_id' => (string) $staffRole->id,
            ])
            ->assertRedirect(route('internal.accounts.show', $this->organizationAccount))
            ->assertSessionHasNoErrors();

        $invitation = Invitation::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $this->organizationAccount->id)
            ->where('email', $email)
            ->firstOrFail();

        $this->assertSame(Invitation::STATUS_PENDING, $invitation->status);
        $this->assertAuditLogRecorded(
            'invitation.created',
            (string) $actor->id,
            (string) $this->organizationAccount->id,
            'Invitation',
            (string) $invitation->id,
        );
    }

    #[Test]
    public function super_admin_can_deactivate_and_reactivate_non_owner_organization_members(): void
    {
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');
        $member = $this->userByEmail('e2e.c.staff@example.test');
        $inactiveMember = $this->userByEmail('e2e.c.disabled@example.test');

        $this->actingAs($actor, 'web')
            ->post(route('internal.accounts.members.deactivate', [$this->organizationAccount, $member]))
            ->assertRedirect(route('internal.accounts.show', $this->organizationAccount))
            ->assertSessionHasNoErrors();

        $this->assertSame('inactive', $member->fresh()->status);
        $this->assertAuditLogRecorded(
            'user.disabled',
            (string) $actor->id,
            (string) $this->organizationAccount->id,
            'User',
            (string) $member->id,
        );

        $this->actingAs($actor, 'web')
            ->post(route('internal.accounts.members.reactivate', [$this->organizationAccount, $inactiveMember]))
            ->assertRedirect(route('internal.accounts.show', $this->organizationAccount))
            ->assertSessionHasNoErrors();

        $this->assertSame('active', $inactiveMember->fresh()->status);
        $this->assertAuditLogRecorded(
            'user.enabled',
            (string) $actor->id,
            (string) $this->organizationAccount->id,
            'User',
            (string) $inactiveMember->id,
        );
    }

    #[Test]
    public function support_can_view_members_and_resend_invites_but_cannot_mutate_members(): void
    {
        $support = $this->userByEmail('e2e.internal.support@example.test');
        $staffRole = $this->tenantRoleByName($this->organizationAccount, 'staff');
        $activeMember = $this->userByEmail('e2e.c.staff@example.test');
        $inactiveMember = $this->userByEmail('e2e.c.disabled@example.test');
        $pendingInvitation = $this->pendingInvitation();

        $detail = $this->actingAs($support, 'web')
            ->get(route('internal.accounts.show', $this->organizationAccount))
            ->assertOk();

        $detail->assertSee('data-testid="organization-members-card"', false);
        $detail->assertSeeText('E2E C Organization Owner');
        $detail->assertSeeText('E2E C Staff');
        $detail->assertSee(route('internal.accounts.invitations.resend', [$this->organizationAccount, $pendingInvitation]), false);
        $detail->assertDontSee('data-testid="organization-member-invite-form"', false);
        $detail->assertDontSee('data-testid="organization-member-deactivate-button"', false);
        $detail->assertDontSee('data-testid="organization-member-reactivate-button"', false);

        $this->actingAs($support, 'web')
            ->post(route('internal.accounts.invitations.resend', [$this->organizationAccount, $pendingInvitation]))
            ->assertRedirect(route('internal.accounts.show', $this->organizationAccount))
            ->assertSessionHasNoErrors();

        $this->actingAs($support, 'web')
            ->post(route('internal.accounts.members.invite', $this->organizationAccount), [
                'name' => 'Should Not Work',
                'email' => 'support-should-not-invite@example.test',
                'role_id' => (string) $staffRole->id,
            ])
            ->assertForbidden();

        $this->actingAs($support, 'web')
            ->post(route('internal.accounts.members.deactivate', [$this->organizationAccount, $activeMember]))
            ->assertForbidden();

        $this->actingAs($support, 'web')
            ->post(route('internal.accounts.members.reactivate', [$this->organizationAccount, $inactiveMember]))
            ->assertForbidden();
    }

    #[Test]
    public function individual_accounts_remain_excluded_from_member_management_surfaces_and_actions(): void
    {
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');
        $staffRole = $this->tenantRoleByName($this->organizationAccount, 'staff');

        $detail = $this->actingAs($actor, 'web')
            ->get(route('internal.accounts.show', $this->individualAccount))
            ->assertOk();

        $detail->assertSeeText('هذا حساب فردي');
        $detail->assertDontSee('data-testid="organization-members-card"', false);
        $detail->assertDontSee('data-testid="organization-member-invite-form"', false);

        $this->actingAs($actor, 'web')
            ->post(route('internal.accounts.members.invite', $this->individualAccount), [
                'name' => 'Should Fail',
                'email' => 'individual-member-should-fail@example.test',
                'role_id' => (string) $staffRole->id,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('account_member');
    }

    #[Test]
    public function ops_readonly_carrier_manager_and_external_users_are_forbidden_from_member_management_routes(): void
    {
        $staffRole = $this->tenantRoleByName($this->organizationAccount, 'staff');
        $member = $this->userByEmail('e2e.c.staff@example.test');
        $inactiveMember = $this->userByEmail('e2e.c.disabled@example.test');

        foreach ([
            'e2e.internal.ops_readonly@example.test',
            'e2e.internal.carrier_manager@example.test',
            'e2e.c.organization_owner@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $this->actingAs($user, 'web')
                ->get(route('internal.accounts.show', $this->organizationAccount))
                ->assertForbidden();

            $this->actingAs($user, 'web')
                ->post(route('internal.accounts.members.invite', $this->organizationAccount), [
                    'name' => 'Forbidden Invite',
                    'email' => 'forbidden-member@example.test',
                    'role_id' => (string) $staffRole->id,
                ])
                ->assertForbidden();

            $this->actingAs($user, 'web')
                ->post(route('internal.accounts.members.deactivate', [$this->organizationAccount, $member]))
                ->assertForbidden();

            $this->actingAs($user, 'web')
                ->post(route('internal.accounts.members.reactivate', [$this->organizationAccount, $inactiveMember]))
                ->assertForbidden();
        }
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

    private function tenantRoleByName(Account $account, string $roleName): Role
    {
        return Role::query()
            ->withoutGlobalScopes()
            ->where('account_id', (string) $account->id)
            ->where('name', $roleName)
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
