<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Concerns\InteractsWithStrictRbac;

class UserManagementApiTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithStrictRbac;

    private Account $account;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->account = Account::factory()->organization()->create(['status' => 'active']);
        $this->owner = User::factory()->owner()->create([
            'account_id' => $this->account->id,
            'email'      => 'owner@company.com',
            'user_type'  => 'external',
        ]);

        $this->grantTenantPermissions($this->owner, [
            'users.read',
            'users.manage',
            'users.invite',
        ], 'users_owner');
    }

    // ─── POST /api/v1/users — إضافة مستخدم ──────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_add_user_via_api(): void
    {
        Sanctum::actingAs($this->owner);
        app()->instance('current_account_id', $this->account->id);

        $response = $this->postJson('/api/v1/users', [
            'name'  => 'موظف جديد',
            'email' => 'newuser@company.com',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => ['id', 'name', 'email', 'status', 'is_owner'],
                 ])
                 ->assertJsonPath('data.status', 'active')
                 ->assertJsonPath('data.is_owner', false);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function adding_user_with_duplicate_email_returns_error(): void
    {
        User::factory()->create([
            'account_id' => $this->account->id,
            'email'      => 'existing@company.com',
        ]);

        Sanctum::actingAs($this->owner);
        app()->instance('current_account_id', $this->account->id);

        $response = $this->postJson('/api/v1/users', [
            'name'  => 'Duplicate',
            'email' => 'existing@company.com',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('error_code', 'ERR_DUPLICATE_EMAIL');
    }

    // ─── GET /api/v1/users — قائمة المستخدمين ────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_list_users(): void
    {
        User::factory()->count(3)->create([
            'account_id' => $this->account->id,
        ]);

        Sanctum::actingAs($this->owner);
        app()->instance('current_account_id', $this->account->id);

        $response = $this->getJson('/api/v1/users');

        $response->assertOk()
                 ->assertJsonPath('meta.total', 4) // 3 + owner
                 ->assertJsonStructure([
                     'data' => [['id', 'name', 'email', 'status']],
                     'meta' => ['current_page', 'total'],
                 ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_filter_users_by_status(): void
    {
        User::factory()->count(2)->create([
            'account_id' => $this->account->id,
            'status' => 'active',
        ]);
        User::factory()->inactive()->create([
            'account_id' => $this->account->id,
        ]);

        Sanctum::actingAs($this->owner);
        app()->instance('current_account_id', $this->account->id);

        $response = $this->getJson('/api/v1/users?status=inactive');

        $response->assertOk()
                 ->assertJsonPath('meta.total', 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_search_users_by_name_or_email(): void
    {
        User::factory()->create([
            'account_id' => $this->account->id,
            'name' => 'محمد أحمد',
            'email' => 'mohammed@test.com',
        ]);
        User::factory()->create([
            'account_id' => $this->account->id,
            'name' => 'سارة علي',
            'email' => 'sara@test.com',
        ]);

        Sanctum::actingAs($this->owner);
        app()->instance('current_account_id', $this->account->id);

        $response = $this->getJson('/api/v1/users?search=محمد');

        $response->assertOk()
                 ->assertJsonPath('meta.total', 1);
    }

    // ─── PATCH /api/v1/users/{id}/disable — تعطيل مستخدم ─────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_disable_user_via_api(): void
    {
        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->owner);
        app()->instance('current_account_id', $this->account->id);

        $response = $this->patchJson("/api/v1/users/{$user->id}/disable");

        $response->assertOk()
                 ->assertJsonPath('data.status', 'inactive');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function disabled_user_cannot_access_api(): void
    {
        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'status' => 'active',
        ]);

        $token = $user->createToken('auth-token')->accessToken;

        // Owner disables the user
        Sanctum::actingAs($this->owner);
        app()->instance('current_account_id', $this->account->id);
        $this->patchJson("/api/v1/users/{$user->id}/disable");

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function disabling_nonexistent_user_returns_404(): void
    {
        Sanctum::actingAs($this->owner);
        app()->instance('current_account_id', $this->account->id);

        $response = $this->patchJson('/api/v1/users/nonexistent-uuid/disable');

        $response->assertStatus(404);

        $this->assertContains(
            $response->json('error_code'),
            ['ERR_USER_NOT_FOUND', 'ERR_NOT_FOUND', null]
        );
    }

    // ─── PATCH /api/v1/users/{id}/enable — تفعيل مستخدم ──────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_enable_disabled_user_via_api(): void
    {
        $user = User::factory()->inactive()->create([
            'account_id' => $this->account->id,
        ]);

        Sanctum::actingAs($this->owner);
        app()->instance('current_account_id', $this->account->id);

        $response = $this->patchJson("/api/v1/users/{$user->id}/enable");

        $response->assertOk()
                 ->assertJsonPath('data.status', 'active');
    }

    // ─── DELETE /api/v1/users/{id} — حذف مستخدم ──────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_delete_user_via_api(): void
    {
        $user = User::factory()->create([
            'account_id' => $this->account->id,
        ]);

        Sanctum::actingAs($this->owner);
        app()->instance('current_account_id', $this->account->id);

        $response = $this->deleteJson("/api/v1/users/{$user->id}");

        $response->assertOk()
                 ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function delete_user_with_responsibilities_returns_409(): void
    {
        $user = User::factory()->create([
            'account_id' => $this->account->id,
        ]);
        $user->createToken('active-work-token');

        Sanctum::actingAs($this->owner);
        app()->instance('current_account_id', $this->account->id);

        $response = $this->deleteJson("/api/v1/users/{$user->id}");

        $response->assertStatus(409)
                 ->assertJsonPath('error_code', 'ERR_RESPONSIBILITY_TRANSFER_REQUIRED');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function delete_user_with_force_transfer_succeeds(): void
    {
        $user = User::factory()->create([
            'account_id' => $this->account->id,
        ]);
        $user->createToken('active-work-token');

        Sanctum::actingAs($this->owner);
        app()->instance('current_account_id', $this->account->id);

        $response = $this->deleteJson("/api/v1/users/{$user->id}?force_transfer=true");

        $response->assertOk()
                 ->assertJsonPath('success', true);
    }

    // ─── PUT /api/v1/users/{id} — تحديث مستخدم ──────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_update_user_via_api(): void
    {
        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'name' => 'Old Name',
        ]);

        Sanctum::actingAs($this->owner);
        app()->instance('current_account_id', $this->account->id);

        $response = $this->putJson("/api/v1/users/{$user->id}", [
            'name'  => 'اسم جديد',
            'phone' => '+966501234567',
        ]);

        $response->assertOk()
                 ->assertJsonPath('data.name', 'اسم جديد')
                 ->assertJsonPath('data.phone', '+966501234567');
    }

    // ─── صلاحيات — غير المالك ─────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_owner_cannot_add_users(): void
    {
        $regularUser = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner' => false,
        ]);

        Sanctum::actingAs($regularUser);
        app()->instance('current_account_id', $this->account->id);

        $response = $this->postJson('/api/v1/users', [
            'name'  => 'Unauthorized Add',
            'email' => 'unauthorized@test.com',
        ]);

        $response->assertStatus(403)
                 ->assertJsonPath('error_code', 'ERR_PERMISSION');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_owner_cannot_disable_users(): void
    {
        $regularUser = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner' => false,
        ]);

        $targetUser = User::factory()->create([
            'account_id' => $this->account->id,
        ]);

        Sanctum::actingAs($regularUser);
        app()->instance('current_account_id', $this->account->id);

        $response = $this->patchJson("/api/v1/users/{$targetUser->id}/disable");

        $response->assertStatus(403);
    }

    // ─── GET /api/v1/users/changelog — سجل التغييرات ─────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_view_user_changelog(): void
    {
        // Add a user to generate audit log
        Sanctum::actingAs($this->owner);
        app()->instance('current_account_id', $this->account->id);

        $this->postJson('/api/v1/users', [
            'name'  => 'Logged User',
            'email' => 'logged@test.com',
        ]);

        $response = $this->getJson('/api/v1/users/changelog');

        $response->assertOk()
                 ->assertJsonStructure([
                     'data',
                 ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function changelog_only_shows_current_account_logs(): void
    {
        // Create another account
        $otherAccount = Account::factory()->create();
        $otherOwner = User::factory()->owner()->create([
            'account_id' => $otherAccount->id,
            'user_type' => 'external',
        ]);
        $this->grantTenantPermissions($otherOwner, [
            'users.read',
            'users.manage',
            'users.invite',
        ], 'users_owner_other_account');

        // Add user in other account
        Sanctum::actingAs($otherOwner);
        app()->instance('current_account_id', $otherAccount->id);
        $this->postJson('/api/v1/users', [
            'name' => 'Other Account User',
            'email' => 'other@test.com',
        ]);

        // Switch to our account
        Sanctum::actingAs($this->owner);
        app()->instance('current_account_id', $this->account->id);

        $response = $this->getJson('/api/v1/users/changelog');

        $response->assertOk();

        // Should not contain logs from other account
        $data = $response->json('data');
        foreach ($data as $log) {
            $this->assertNotEquals($otherOwner->id, $log['performed_by'] ?? null);
        }
    }

    // ─── Tenant Isolation ─────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_cannot_manage_users_from_another_account(): void
    {
        $otherAccount = Account::factory()->create();
        $otherUser = User::factory()->create([
            'account_id' => $otherAccount->id,
        ]);

        Sanctum::actingAs($this->owner);
        app()->instance('current_account_id', $this->account->id);

        // Try to disable user from another account
        $response = $this->patchJson("/api/v1/users/{$otherUser->id}/disable");

        $response->assertStatus(404);

        $this->assertContains(
            $response->json('error_code'),
            ['ERR_USER_NOT_FOUND', 'ERR_NOT_FOUND', null]
        );
    }
}
