<?php

namespace Tests\Unit;

use App\Exceptions\BusinessException;
use App\Events\InvitationCreated;
use App\Events\InvitationAccepted;
use App\Events\InvitationCancelled;
use App\Events\InvitationResent;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\SeedsPermissions;

class InvitationTest extends TestCase
{
    use RefreshDatabase, SeedsPermissions;

    private InvitationService $service;
    private Account $account;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->service = new InvitationService();

        $this->account = Account::factory()->create(['status' => 'active']);
        $this->owner = User::factory()->owner()->create([
            'account_id' => $this->account->id,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  AC: نجاح — إنشاء دعوة
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_create_invitation(): void
    {
        Event::fake();

        $invitation = $this->service->createInvitation([
            'email' => 'newuser@example.com',
            'name'  => 'محمد أحمد',
        ], $this->owner);

        $this->assertNotNull($invitation->id);
        $this->assertEquals('newuser@example.com', $invitation->email);
        $this->assertEquals('محمد أحمد', $invitation->name);
        $this->assertEquals(Invitation::STATUS_PENDING, $invitation->status);
        $this->assertEquals($this->owner->id, $invitation->invited_by);
        $this->assertEquals($this->account->id, $invitation->account_id);
        $this->assertNotEmpty($invitation->token);
        $this->assertTrue($invitation->expires_at->isFuture());
        $this->assertEquals(1, $invitation->send_count);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function invitation_fires_created_event(): void
    {
        Event::fake([InvitationCreated::class]);

        $this->service->createInvitation([
            'email' => 'event@example.com',
        ], $this->owner);

        Event::assertDispatched(InvitationCreated::class, function ($event) {
            return $event->invitation->email === 'event@example.com'
                && $event->inviter->id === $this->owner->id;
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function invitation_creates_audit_log(): void
    {
        Event::fake();

        $invitation = $this->service->createInvitation([
            'email' => 'audit@example.com',
        ], $this->owner);

        $log = AuditLog::withoutGlobalScopes()
            ->where('action', 'invitation.created')
            ->where('entity_id', $invitation->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($this->owner->id, $log->user_id);
        $this->assertEquals($this->account->id, $log->account_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function invitation_can_include_role_assignment(): void
    {
        Event::fake();

        $role = Role::factory()->create([
            'account_id' => $this->account->id,
            'name'       => 'warehouse-staff',
        ]);

        $invitation = $this->service->createInvitation([
            'email'   => 'role@example.com',
            'role_id' => $role->id,
        ], $this->owner);

        $this->assertEquals($role->id, $invitation->role_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function invitation_uses_custom_ttl(): void
    {
        Event::fake();

        $invitation = $this->service->createInvitation([
            'email'     => 'ttl@example.com',
            'ttl_hours' => 24,
        ], $this->owner);

        // Should expire approximately 24 hours from now
        $this->assertTrue(
            $invitation->expires_at->diffInHours(now(), true) >= 23
            && $invitation->expires_at->diffInHours(now(), true) <= 25
        );
    }

    // ═══════════════════════════════════════════════════════════════
    //  AC: فشل شائع — منع الدعوات المكررة
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_create_duplicate_pending_invitation(): void
    {
        Event::fake();

        $this->service->createInvitation([
            'email' => 'duplicate@example.com',
        ], $this->owner);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('توجد دعوة نشطة بالفعل');

        $this->service->createInvitation([
            'email' => 'duplicate@example.com',
        ], $this->owner);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_invite_existing_account_user(): void
    {
        Event::fake();

        $existingUser = User::factory()->create([
            'account_id' => $this->account->id,
            'email'      => 'existing@example.com',
        ]);

        $this->expectException(BusinessException::class);

        $this->service->createInvitation([
            'email' => 'existing@example.com',
        ], $this->owner);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_invite_with_role_from_another_account(): void
    {
        Event::fake();

        $otherAccount = Account::factory()->create();
        $otherRole = Role::factory()->create([
            'account_id' => $otherAccount->id,
        ]);

        $this->expectException(BusinessException::class);

        $this->service->createInvitation([
            'email'   => 'badrole@example.com',
            'role_id' => $otherRole->id,
        ], $this->owner);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_owner_without_invite_permission_cannot_create_invitation(): void
    {
        Event::fake();

        $regularUser = User::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $this->expectException(BusinessException::class);

        $this->service->createInvitation([
            'email' => 'noperm@example.com',
        ], $regularUser);
    }

    // ═══════════════════════════════════════════════════════════════
    //  AC: نجاح — قبول الدعوة
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function invitee_can_accept_valid_invitation(): void
    {
        Event::fake();

        $invitation = $this->service->createInvitation([
            'email' => 'accept@example.com',
            'name'  => 'مستخدم جديد',
        ], $this->owner);

        $result = $this->service->acceptInvitation($invitation->token, [
            'name'     => 'مستخدم جديد',
            'password' => 'SecureP@ss1!',
            'phone'    => '+966500000000',
        ]);

        $user = $result['user'];
        $updatedInvitation = $result['invitation'];

        // User created correctly
        $this->assertEquals('accept@example.com', $user->email);
        $this->assertEquals('مستخدم جديد', $user->name);
        $this->assertEquals($this->account->id, $user->account_id);
        $this->assertFalse($user->is_owner);
        $this->assertEquals('active', $user->status);

        // Invitation marked as accepted
        $this->assertEquals(Invitation::STATUS_ACCEPTED, $updatedInvitation->status);
        $this->assertNotNull($updatedInvitation->accepted_at);
        $this->assertEquals($user->id, $updatedInvitation->accepted_by);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function accepting_invitation_assigns_role(): void
    {
        Event::fake();

        $role = Role::factory()->create([
            'account_id' => $this->account->id,
            'name'       => 'viewer',
        ]);

        $invitation = $this->service->createInvitation([
            'email'   => 'withrole@example.com',
            'role_id' => $role->id,
        ], $this->owner);

        $result = $this->service->acceptInvitation($invitation->token, [
            'name'     => 'مستخدم بدور',
            'password' => 'SecureP@ss1!',
        ]);

        $this->assertTrue(
            $result['user']->roles()->where('roles.id', $role->id)->exists()
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function accepting_invitation_fires_event(): void
    {
        Event::fake();

        $invitation = $this->service->createInvitation([
            'email' => 'eventaccept@example.com',
        ], $this->owner);

        $this->service->acceptInvitation($invitation->token, [
            'name'     => 'New',
            'password' => 'SecureP@ss1!',
        ]);

        Event::assertDispatched(InvitationAccepted::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function accepting_invitation_creates_audit_log(): void
    {
        Event::fake();

        $invitation = $this->service->createInvitation([
            'email' => 'auditaccept@example.com',
        ], $this->owner);

        $this->service->acceptInvitation($invitation->token, [
            'name'     => 'Audit User',
            'password' => 'SecureP@ss1!',
        ]);

        $log = AuditLog::withoutGlobalScopes()
            ->where('action', 'invitation.accepted')
            ->where('entity_id', $invitation->id)
            ->first();

        $this->assertNotNull($log);
    }

    // ═══════════════════════════════════════════════════════════════
    //  AC: فشل شائع — انتهاء صلاحية الدعوة
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_accept_expired_invitation(): void
    {
        Event::fake();

        $invitation = Invitation::factory()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
            'email'      => 'expired@example.com',
            'status'     => Invitation::STATUS_PENDING,
            'expires_at' => now()->subHours(1), // Already past TTL
        ]);

        try {
            $this->service->acceptInvitation($invitation->token, [
                'name'     => 'Late User',
                'password' => 'SecureP@ss1!',
            ]);
            $this->fail('Expected BusinessException for expired invitation');
        } catch (BusinessException $e) {
            $this->assertEquals('ERR_INVITATION_EXPIRED', $e->getErrorCode());
        }

        // Verify status was auto-updated to expired
        $invitation->refresh();
        $this->assertEquals(Invitation::STATUS_EXPIRED, $invitation->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_accept_cancelled_invitation(): void
    {
        Event::fake();

        $invitation = Invitation::factory()->cancelled()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
            'email'      => 'cancelled@example.com',
        ]);

        $this->expectException(BusinessException::class);

        $this->service->acceptInvitation($invitation->token, [
            'name'     => 'Cancel User',
            'password' => 'SecureP@ss1!',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_accept_already_accepted_invitation(): void
    {
        Event::fake();

        $invitation = Invitation::factory()->accepted()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
            'email'      => 'alreadyaccepted@example.com',
        ]);

        $this->expectException(BusinessException::class);

        $this->service->acceptInvitation($invitation->token, [
            'name'     => 'Dup User',
            'password' => 'SecureP@ss1!',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_accept_with_invalid_token(): void
    {
        $this->expectException(BusinessException::class);

        $this->service->acceptInvitation('invalid-token-12345', [
            'name'     => 'Ghost',
            'password' => 'SecureP@ss1!',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  إلغاء الدعوة
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_cancel_pending_invitation(): void
    {
        Event::fake();

        $invitation = $this->service->createInvitation([
            'email' => 'tocancel@example.com',
        ], $this->owner);

        $cancelled = $this->service->cancelInvitation($invitation->id, $this->owner);

        $this->assertEquals(Invitation::STATUS_CANCELLED, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);

        Event::assertDispatched(InvitationCancelled::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_cancel_accepted_invitation(): void
    {
        Event::fake();

        $invitation = Invitation::factory()->accepted()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
        ]);

        $this->expectException(BusinessException::class);

        $this->service->cancelInvitation($invitation->id, $this->owner);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_cancel_invitation_from_another_account(): void
    {
        Event::fake();

        $otherAccount = Account::factory()->create();
        $otherOwner = User::factory()->owner()->create(['account_id' => $otherAccount->id]);

        $invitation = Invitation::factory()->create([
            'account_id' => $otherAccount->id,
            'invited_by' => $otherOwner->id,
        ]);

        $this->expectException(BusinessException::class);

        // Our owner tries to cancel other account's invitation
        $this->service->cancelInvitation($invitation->id, $this->owner);
    }

    // ═══════════════════════════════════════════════════════════════
    //  إعادة إرسال الدعوة
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_resend_pending_invitation(): void
    {
        Event::fake();

        $invitation = $this->service->createInvitation([
            'email' => 'resend@example.com',
        ], $this->owner);

        $originalToken = $invitation->token;

        $resent = $this->service->resendInvitation($invitation->id, $this->owner);

        $this->assertNotEquals($originalToken, $resent->token); // New token
        $this->assertEquals(2, $resent->send_count);
        $this->assertTrue($resent->expires_at->isFuture()); // Reset TTL

        Event::assertDispatched(InvitationResent::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_resend_cancelled_invitation(): void
    {
        Event::fake();

        $invitation = Invitation::factory()->cancelled()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
        ]);

        $this->expectException(BusinessException::class);

        $this->service->resendInvitation($invitation->id, $this->owner);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_exceed_max_resend_count(): void
    {
        Event::fake();

        $invitation = Invitation::factory()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
            'send_count' => InvitationService::MAX_RESEND_COUNT,
        ]);

        try {
            $this->service->resendInvitation($invitation->id, $this->owner);
            $this->fail('Expected BusinessException for max resend count');
        } catch (BusinessException $e) {
            $this->assertEquals('ERR_INVITATION_MAX_RESEND', $e->getErrorCode());
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  انتهاء الصلاحية التلقائي
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function stale_invitations_are_auto_expired(): void
    {
        Event::fake();

        // Create stale invitations (past TTL but still marked pending)
        $stale1 = Invitation::factory()->stale()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
        ]);
        $stale2 = Invitation::factory()->stale()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
        ]);

        // Still-valid pending invitation
        $valid = Invitation::factory()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
        ]);

        $count = $this->service->expireStaleInvitations($this->account->id);

        $this->assertEquals(2, $count);

        $stale1->refresh();
        $stale2->refresh();
        $valid->refresh();

        $this->assertEquals(Invitation::STATUS_EXPIRED, $stale1->status);
        $this->assertEquals(Invitation::STATUS_EXPIRED, $stale2->status);
        $this->assertEquals(Invitation::STATUS_PENDING, $valid->status);
    }

    // ═══════════════════════════════════════════════════════════════
    //  حالة حدية — إعادة الدعوة بعد إلغاء السابقة
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_create_new_invitation_after_cancelling_previous(): void
    {
        Event::fake();

        // Create and cancel
        $first = $this->service->createInvitation([
            'email' => 'retry@example.com',
        ], $this->owner);
        $this->service->cancelInvitation($first->id, $this->owner);

        // Create new one for same email
        $second = $this->service->createInvitation([
            'email' => 'retry@example.com',
        ], $this->owner);

        $this->assertNotEquals($first->id, $second->id);
        $this->assertEquals(Invitation::STATUS_PENDING, $second->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_create_new_invitation_after_previous_expired(): void
    {
        Event::fake();

        // Create an expired invitation
        Invitation::factory()->expired()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
            'email'      => 'expiredretry@example.com',
        ]);

        // Create new one for same email — should work
        $newInvitation = $this->service->createInvitation([
            'email' => 'expiredretry@example.com',
        ], $this->owner);

        $this->assertEquals(Invitation::STATUS_PENDING, $newInvitation->status);
    }

    // ═══════════════════════════════════════════════════════════════
    //  عرض تفاصيل الدعوة بالرمز (عام)
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_preview_invitation_by_token(): void
    {
        Event::fake();

        $invitation = $this->service->createInvitation([
            'email' => 'preview@example.com',
            'name'  => 'Preview User',
        ], $this->owner);

        $preview = $this->service->getInvitationByToken($invitation->token);

        $this->assertEquals('preview@example.com', $preview->email);
        $this->assertEquals('Preview User', $preview->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function preview_with_invalid_token_throws_not_found(): void
    {
        $this->expectException(BusinessException::class);

        $this->service->getInvitationByToken('nonexistent-token');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function preview_auto_expires_stale_invitation(): void
    {
        Event::fake();

        $invitation = Invitation::factory()->stale()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
        ]);

        $result = $this->service->getInvitationByToken($invitation->token);

        $this->assertEquals(Invitation::STATUS_EXPIRED, $result->status);
    }
}
