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
use Tests\Concerns\AssertsSchemaAwareAuditLogs;
use Tests\TestCase;

class InternalStaffManagementWebTest extends TestCase
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
    public function super_admin_can_create_internal_staff_user_with_one_canonical_role(): void
    {
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');

        $response = $this->actingAs($actor, 'web')->post(route('internal.staff.store'), [
            'provisioning_mode' => 'create',
            'name' => 'I3B Support User',
            'email' => 'i3b.support.user@example.test',
            'locale' => 'en',
            'timezone' => 'UTC',
            'role' => 'support',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $user = $this->userByEmail('i3b.support.user@example.test');
        $this->assertNull($user->account_id);
        $this->assertSame('internal', (string) $user->user_type);
        $this->assertSame('active', (string) $user->status);
        $this->assertSame(['support'], $user->internalRoleNames());
        $this->assertSame(1, DB::table('internal_user_role')->where('user_id', (string) $user->id)->count());

        if (Schema::hasColumn('users', 'email_verified_at')) {
            $this->assertNotNull($user->email_verified_at);
        }

        $this->assertAuditLogRecorded('user.added', (string) $actor->id, null, 'User', (string) $user->id);
        $this->assertAuditLogRecorded('role.assigned', (string) $actor->id, null, 'User', (string) $user->id);
    }

    #[Test]
    public function super_admin_can_invite_internal_staff_user_via_reset_bootstrap(): void
    {
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');
        $smtp = Mockery::mock(SmtpSettingsService::class);
        $smtp->shouldReceive('sendMailable')
            ->once()
            ->with(
                'i3b.invited.user@example.test',
                Mockery::type(PasswordResetMail::class),
            )
            ->andReturn('staff-invite-message-id');
        $this->app->instance(SmtpSettingsService::class, $smtp);

        $response = $this->actingAs($actor, 'web')->post(route('internal.staff.store'), [
            'provisioning_mode' => 'invite',
            'name' => 'I3B Invited User',
            'email' => 'i3b.invited.user@example.test',
            'locale' => 'en',
            'timezone' => 'UTC',
            'role' => 'carrier_manager',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $user = $this->userByEmail('i3b.invited.user@example.test');
        $this->assertNull($user->account_id);
        $this->assertSame('internal', (string) $user->user_type);
        $this->assertSame(['carrier_manager'], $user->internalRoleNames());
        $this->assertSame(1, DB::table('internal_user_role')->where('user_id', (string) $user->id)->count());

        if (Schema::hasColumn('users', 'email_verified_at')) {
            $this->assertNull($user->email_verified_at);
        }

        if (Schema::hasTable('password_reset_tokens')) {
            $this->assertDatabaseHas('password_reset_tokens', [
                'email' => 'i3b.invited.user@example.test',
            ]);
        }

        $this->assertAuditLogRecorded('user.added', (string) $actor->id, null, 'User', (string) $user->id);
        $this->assertAuditLogRecorded('role.assigned', (string) $actor->id, null, 'User', (string) $user->id);
        $this->assertAuditLogRecorded('auth.password_reset_link_sent', (string) $actor->id, null, 'User', (string) $user->id);
    }

    #[Test]
    public function super_admin_can_edit_staff_user_and_reassign_a_canonical_role(): void
    {
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');
        $staffUser = $this->userByEmail('e2e.internal.support@example.test');

        $response = $this->actingAs($actor, 'web')->put(route('internal.staff.update', $staffUser), [
            'name' => 'Edited Internal Support',
            'email' => 'edited.internal.support@example.test',
            'locale' => 'ar',
            'timezone' => 'Asia/Riyadh',
            'role' => 'carrier_manager',
        ]);

        $response->assertRedirect(route('internal.staff.show', $staffUser));
        $response->assertSessionHasNoErrors();

        $staffUser->refresh();
        $this->assertSame('Edited Internal Support', $staffUser->name);
        $this->assertSame('edited.internal.support@example.test', $staffUser->email);
        $this->assertSame('ar', $staffUser->locale);
        $this->assertSame('Asia/Riyadh', $staffUser->timezone);
        $this->assertSame(['carrier_manager'], $staffUser->internalRoleNames());
        $this->assertSame(1, DB::table('internal_user_role')->where('user_id', (string) $staffUser->id)->count());

        $this->assertAuditLogRecorded('user.updated', (string) $actor->id, null, 'User', (string) $staffUser->id);
        $this->assertAuditLogRecorded('role.assigned', (string) $actor->id, null, 'User', (string) $staffUser->id);
    }

    #[Test]
    public function support_cannot_mutate_staff_records_or_see_mutation_ctas(): void
    {
        $support = $this->userByEmail('e2e.internal.support@example.test');
        $superAdmin = $this->userByEmail('e2e.internal.super_admin@example.test');

        $index = $this->actingAs($support, 'web')
            ->get(route('internal.staff.index'))
            ->assertOk();
        $index->assertDontSee(route('internal.staff.create'), false);

        $detail = $this->actingAs($support, 'web')
            ->get(route('internal.staff.show', $superAdmin))
            ->assertOk();
        $detail->assertDontSee(route('internal.staff.edit', $superAdmin), false);

        $this->actingAs($support, 'web')
            ->get(route('internal.staff.create'))
            ->assertForbidden();

        $this->actingAs($support, 'web')
            ->post(route('internal.staff.store'), [
                'provisioning_mode' => 'create',
                'name' => 'Should Not Work',
                'email' => 'should-not-work@example.test',
                'role' => 'support',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertForbidden();

        $this->actingAs($support, 'web')
            ->get(route('internal.staff.edit', $superAdmin))
            ->assertForbidden();

        $this->actingAs($support, 'web')
            ->put(route('internal.staff.update', $superAdmin), [
                'name' => 'Nope',
                'email' => 'nope@example.test',
                'role' => 'support',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function ops_readonly_and_carrier_manager_are_forbidden_from_staff_management_routes(): void
    {
        $target = $this->userByEmail('e2e.internal.support@example.test');

        foreach ([
            'e2e.internal.ops_readonly@example.test',
            'e2e.internal.carrier_manager@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $this->actingAs($user, 'web')
                ->get(route('internal.staff.create'))
                ->assertForbidden();

            $this->actingAs($user, 'web')
                ->get(route('internal.staff.edit', $target))
                ->assertForbidden();

            $this->actingAs($user, 'web')
                ->post(route('internal.staff.store'), [
                    'provisioning_mode' => 'create',
                    'name' => 'Nope',
                    'email' => 'forbidden@example.test',
                    'role' => 'support',
                    'password' => 'Password123!',
                    'password_confirmation' => 'Password123!',
                ])
                ->assertForbidden();
        }
    }

    #[Test]
    public function external_users_are_forbidden_from_internal_staff_management_routes(): void
    {
        $externalUser = $this->userByEmail('e2e.c.organization_owner@example.test');
        $target = $this->userByEmail('e2e.internal.support@example.test');

        $this->actingAs($externalUser, 'web')
            ->get(route('internal.staff.create'))
            ->assertForbidden();

        $this->actingAs($externalUser, 'web')
            ->post(route('internal.staff.store'), [
                'provisioning_mode' => 'create',
                'name' => 'Should Not Work',
                'email' => 'external-should-not-work@example.test',
                'role' => 'support',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertForbidden();

        $this->actingAs($externalUser, 'web')
            ->get(route('internal.staff.edit', $target))
            ->assertForbidden();

        $this->actingAs($externalUser, 'web')
            ->put(route('internal.staff.update', $target), [
                'name' => 'Nope',
                'email' => 'external-nope@example.test',
                'role' => 'support',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function legacy_internal_roles_are_hidden_from_visible_staff_management_flows_and_cannot_be_assigned(): void
    {
        $actor = $this->userByEmail('e2e.internal.super_admin@example.test');
        $staffUser = $this->userByEmail('e2e.internal.support@example.test');

        $this->actingAs($actor, 'web')
            ->get(route('internal.staff.create'))
            ->assertOk()
            ->assertSee('value="super_admin"', false)
            ->assertSee('value="support"', false)
            ->assertSee('value="ops_readonly"', false)
            ->assertSee('value="carrier_manager"', false)
            ->assertDontSeeText('finance')
            ->assertDontSeeText('integration_admin')
            ->assertDontSeeText('ops');

        $this->actingAs($actor, 'web')
            ->get(route('internal.staff.edit', $staffUser))
            ->assertOk()
            ->assertSee('value="super_admin"', false)
            ->assertSee('value="support"', false)
            ->assertSee('value="ops_readonly"', false)
            ->assertSee('value="carrier_manager"', false)
            ->assertDontSeeText('finance')
            ->assertDontSeeText('integration_admin')
            ->assertDontSeeText('ops');

        $response = $this->actingAs($actor, 'web')->from(route('internal.staff.create'))
            ->post(route('internal.staff.store'), [
                'provisioning_mode' => 'create',
                'name' => 'Legacy Role Attempt',
                'email' => 'legacy-role-attempt@example.test',
                'role' => 'finance',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ]);

        $response->assertRedirect(route('internal.staff.create'));
        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', [
            'email' => 'legacy-role-attempt@example.test',
        ]);
    }

    private function userByEmail(string $email): User
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('email', $email)
            ->firstOrFail();
    }
}
