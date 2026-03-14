<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Traits\SeedsPermissions;

class InvitationApiTest extends TestCase
{
    use RefreshDatabase, SeedsPermissions;

    private Account $account;
    private User $owner;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        $this->seedPermissions();

        $this->account = Account::factory()->organization()->create(['status' => 'active']);
        $this->owner = User::factory()->owner()->create([
            'account_id' => $this->account->id,
        ]);
        $this->token = $this->owner->createToken('test')->plainTextToken;
    }

    // ═══════════════════════════════════════════════════════════════
    //  POST /api/v1/invitations — إنشاء دعوة
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_create_invitation_via_api(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->postJson('/api/v1/invitations', [
            'email' => 'newuser@example.com',
            'name'  => 'مستخدم جديد',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.email', 'newuser@example.com')
                 ->assertJsonPath('data.status', 'pending');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function create_invitation_with_role(): void
    {
        $role = Role::factory()->create([
            'account_id'   => $this->account->id,
            'display_name' => 'محاسب',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->postJson('/api/v1/invitations', [
            'email'   => 'withrole@example.com',
            'role_id' => $role->id,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.role.id', $role->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function duplicate_pending_invitation_returns_409(): void
    {
        $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->postJson('/api/v1/invitations', [
            'email' => 'dup@example.com',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->postJson('/api/v1/invitations', [
            'email' => 'dup@example.com',
        ]);

        $response->assertStatus(409)
                 ->assertJsonPath('error_code', 'ERR_INVITATION_ALREADY_EXISTS');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function invite_existing_user_returns_409(): void
    {
        User::factory()->create([
            'account_id' => $this->account->id,
            'email'      => 'exists@example.com',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->postJson('/api/v1/invitations', [
            'email' => 'exists@example.com',
        ]);

        $response->assertStatus(409)
                 ->assertJsonPath('error_code', 'ERR_EMAIL_ALREADY_IN_ACCOUNT');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_owner_cannot_create_invitation(): void
    {
        $regularUser = User::factory()->create([
            'account_id' => $this->account->id,
        ]);
        $regularToken = $regularUser->createToken('test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$regularToken}",
        ])->postJson('/api/v1/invitations', [
            'email' => 'nope@example.com',
        ]);

        $response->assertStatus(403);
    }

    // ═══════════════════════════════════════════════════════════════
    //  GET /api/v1/invitations — قائمة الدعوات
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_list_invitations(): void
    {
        // Create some invitations
        Invitation::factory()->count(3)->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/invitations');

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonCount(3, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_filter_invitations_by_status(): void
    {
        Invitation::factory()->count(2)->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
            'status'     => 'pending',
        ]);
        Invitation::factory()->cancelled()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/invitations?status=pending');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_search_invitations_by_email(): void
    {
        Invitation::factory()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
            'email'      => 'findme@example.com',
        ]);
        Invitation::factory()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
            'email'      => 'other@example.com',
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/invitations?search=findme');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function invitations_are_tenant_isolated(): void
    {
        // Other account's invitation
        $otherAccount = Account::factory()->create();
        $otherOwner = User::factory()->owner()->create(['account_id' => $otherAccount->id]);
        Invitation::factory()->create([
            'account_id' => $otherAccount->id,
            'invited_by' => $otherOwner->id,
        ]);

        // Our account's invitation
        Invitation::factory()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/invitations');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data');
    }

    // ═══════════════════════════════════════════════════════════════
    //  PATCH /api/v1/invitations/{id}/cancel — إلغاء دعوة
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_cancel_invitation_via_api(): void
    {
        $invitation = Invitation::factory()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->patchJson("/api/v1/invitations/{$invitation->id}/cancel");

        $response->assertStatus(200)
                 ->assertJsonPath('data.status', 'cancelled');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cancel_accepted_invitation_returns_422(): void
    {
        $invitation = Invitation::factory()->accepted()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->patchJson("/api/v1/invitations/{$invitation->id}/cancel");

        $response->assertStatus(422)
                 ->assertJsonPath('error_code', 'ERR_INVITATION_CANNOT_CANCEL');
    }

    // ═══════════════════════════════════════════════════════════════
    //  POST /api/v1/invitations/{id}/resend — إعادة إرسال
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_resend_invitation_via_api(): void
    {
        $invitation = Invitation::factory()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
            'send_count' => 1,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->postJson("/api/v1/invitations/{$invitation->id}/resend");

        $response->assertStatus(200)
                 ->assertJsonPath('data.send_count', 2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function resend_cancelled_invitation_returns_422(): void
    {
        $invitation = Invitation::factory()->cancelled()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->postJson("/api/v1/invitations/{$invitation->id}/resend");

        $response->assertStatus(422)
                 ->assertJsonPath('error_code', 'ERR_INVITATION_CANNOT_RESEND');
    }

    // ═══════════════════════════════════════════════════════════════
    //  Public: GET /api/v1/invitations/preview/{token}
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function invitee_can_preview_invitation(): void
    {
        $invitation = Invitation::factory()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
            'email'      => 'preview@example.com',
            'name'       => 'Preview User',
        ]);

        // No auth required
        $response = $this->getJson("/api/v1/invitations/preview/{$invitation->token}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.email', 'preview@example.com')
                 ->assertJsonPath('data.name', 'Preview User')
                 ->assertJsonPath('data.status', 'pending');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function preview_invalid_token_returns_404(): void
    {
        $response = $this->getJson('/api/v1/invitations/preview/invalid-token');

        $response->assertStatus(404)
                 ->assertJsonPath('error_code', 'ERR_INVITATION_NOT_FOUND');
    }

    // ═══════════════════════════════════════════════════════════════
    //  Public: POST /api/v1/invitations/accept/{token}
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function invitee_can_accept_invitation_via_api(): void
    {
        $invitation = Invitation::factory()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
            'email'      => 'newjoin@example.com',
        ]);

        $response = $this->postJson("/api/v1/invitations/accept/{$invitation->token}", [
            'name'     => 'مستخدم مقبول',
            'password' => 'SecureP@ss1!',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.user.email', 'newjoin@example.com')
                 ->assertJsonPath('data.invitation.status', 'accepted');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function accept_expired_invitation_returns_410(): void
    {
        $invitation = Invitation::factory()->stale()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
        ]);

        $response = $this->postJson("/api/v1/invitations/accept/{$invitation->token}", [
            'name'     => 'Late User',
            'password' => 'SecureP@ss1!',
        ]);

        $response->assertStatus(410)
                 ->assertJsonPath('error_code', 'ERR_INVITATION_EXPIRED');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function accept_with_missing_password_returns_422(): void
    {
        $invitation = Invitation::factory()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
        ]);

        $response = $this->postJson("/api/v1/invitations/accept/{$invitation->token}", [
            'name' => 'No Password',
        ]);

        $response->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function accept_assigns_role_to_new_user(): void
    {
        $role = Role::factory()->create([
            'account_id'   => $this->account->id,
            'display_name' => 'عامل المستودع',
        ]);

        $invitation = Invitation::factory()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
            'email'      => 'roleaccept@example.com',
            'role_id'    => $role->id,
        ]);

        $response = $this->postJson("/api/v1/invitations/accept/{$invitation->token}", [
            'name'     => 'عامل جديد',
            'password' => 'SecureP@ss1!',
        ]);

        $response->assertStatus(201);

        // Verify user has the role
        $userId = $response->json('data.user.id');
        $user = User::withoutGlobalScopes()->find($userId);
        $this->assertTrue($user->roles()->where('roles.id', $role->id)->exists());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_reuse_accepted_invitation_link(): void
    {
        $invitation = Invitation::factory()->create([
            'account_id' => $this->account->id,
            'invited_by' => $this->owner->id,
            'email'      => 'reuse@example.com',
        ]);

        // First accept
        $this->postJson("/api/v1/invitations/accept/{$invitation->token}", [
            'name'     => 'First',
            'password' => 'SecureP@ss1!',
        ])->assertStatus(201);

        // Try to reuse
        $response = $this->postJson("/api/v1/invitations/accept/{$invitation->token}", [
            'name'     => 'Second',
            'password' => 'SecureP@ss1!',
        ]);

        $response->assertStatus(409)
                 ->assertJsonPath('error_code', 'ERR_INVITATION_ALREADY_ACCEPTED');
    }
}
