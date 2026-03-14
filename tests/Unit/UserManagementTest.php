<?php

namespace Tests\Unit;

use App\Exceptions\BusinessException;
use App\Models\Account;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use App\Events\UserInvited;
use App\Events\UserDisabled;
use App\Events\UserDeleted;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private UserService $service;
    private Account $account;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UserService();

        // Create account with owner
        $this->account = Account::factory()->create(['status' => 'active']);
        $this->owner = User::factory()->owner()->create([
            'account_id' => $this->account->id,
            'email'      => 'owner@test.com',
        ]);
    }

    // ─── AC: نجاح — إضافة مستخدم جديد ──────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_add_user_to_account(): void
    {
        Event::fake([UserInvited::class]);

        $user = $this->service->addUser([
            'name'  => 'موظف جديد',
            'email' => 'employee@test.com',
            'password' => 'Str0ng!Pass',
        ], $this->owner);

        $this->assertNotNull($user->id);
        $this->assertEquals($this->account->id, $user->account_id);
        $this->assertEquals('active', $user->status);
        $this->assertFalse($user->is_owner);
        Event::assertDispatched(UserInvited::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function added_user_gets_invitation_event(): void
    {
        Event::fake([UserInvited::class]);

        $user = $this->service->addUser([
            'name'  => 'مدعو',
            'email' => 'invited@test.com',
        ], $this->owner);

        Event::assertDispatched(UserInvited::class, function ($event) use ($user) {
            return $event->user->id === $user->id
                && $event->invitedBy->id === $this->owner->id;
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function adding_user_creates_audit_log(): void
    {
        Event::fake();

        $user = $this->service->addUser([
            'name'  => 'Audit User',
            'email' => 'audit-user@test.com',
        ], $this->owner);

        $this->assertDatabaseHas('audit_logs', [
            'account_id'  => $this->account->id,
            'action'      => 'user.added',
            'entity_type' => 'User',
            'entity_id'   => $user->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_add_duplicate_email_in_same_account(): void
    {
        Event::fake();

        $this->service->addUser([
            'name'  => 'First',
            'email' => 'same@test.com',
        ], $this->owner);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('البريد الإلكتروني مستخدم بالفعل');

        $this->service->addUser([
            'name'  => 'Duplicate',
            'email' => 'same@test.com',
        ], $this->owner);
    }

    // ─── تعطيل المستخدم ──────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_disable_user(): void
    {
        Event::fake([UserDisabled::class]);

        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'status' => 'active',
        ]);

        $disabled = $this->service->disableUser($user->id, $this->owner);

        $this->assertEquals('inactive', $disabled->status);
        Event::assertDispatched(UserDisabled::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function disabling_user_revokes_all_tokens(): void
    {
        Event::fake();

        $user = User::factory()->create([
            'account_id' => $this->account->id,
        ]);

        // Create a token
        $user->createToken('test-token');
        $this->assertEquals(1, $user->tokens()->count());

        $this->service->disableUser($user->id, $this->owner);

        $this->assertEquals(0, $user->tokens()->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_disable_self(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('لا يمكنك تعطيل حسابك الخاص');

        $this->service->disableUser($this->owner->id, $this->owner);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_disable_account_owner(): void
    {
        // Create admin (non-owner but trying to disable owner)
        $admin = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner' => true, // Another owner scenario
        ]);

        // Create actual test: non-owner tries action
        // For this test, owner tries to disable another owner
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('لا يمكن تعديل حالة مالك الحساب');

        $this->service->disableUser($admin->id, $this->owner);
    }

    // ─── تفعيل المستخدم ──────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_enable_disabled_user(): void
    {
        Event::fake();

        $user = User::factory()->inactive()->create([
            'account_id' => $this->account->id,
        ]);

        $enabled = $this->service->enableUser($user->id, $this->owner);

        $this->assertEquals('active', $enabled->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_enable_already_active_user(): void
    {
        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'status' => 'active',
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('المستخدم نشط بالفعل');

        $this->service->enableUser($user->id, $this->owner);
    }

    // ─── حذف المستخدم ─────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_delete_user_without_responsibilities(): void
    {
        Event::fake([UserDeleted::class]);

        $user = User::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $result = $this->service->deleteUser($user->id, $this->owner);

        $this->assertTrue($result);
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        Event::assertDispatched(UserDeleted::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_delete_self(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('لا يمكنك حذف حسابك الخاص');

        $this->service->deleteUser($this->owner->id, $this->owner);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_delete_account_owner(): void
    {
        $anotherOwner = User::factory()->owner()->create([
            'account_id' => $this->account->id,
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('لا يمكن تعديل حالة مالك الحساب');

        $this->service->deleteUser($anotherOwner->id, $this->owner);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function deleting_user_with_responsibilities_requires_transfer(): void
    {
        Event::fake();

        $user = User::factory()->create([
            'account_id' => $this->account->id,
        ]);

        // Give user active tokens (responsibility)
        $user->createToken('active-token');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('يجب نقل مسؤوليات');

        $this->service->deleteUser($user->id, $this->owner, false);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function deleting_user_with_force_transfer_bypasses_check(): void
    {
        Event::fake([UserDeleted::class]);

        $user = User::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $user->createToken('active-token');

        $result = $this->service->deleteUser($user->id, $this->owner, true);

        $this->assertTrue($result);
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    // ─── AC: فشل شائع — مستخدم غير موجود ────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function disable_nonexistent_user_throws_not_found(): void
    {
        $this->expectException(BusinessException::class);

        $this->service->disableUser('nonexistent-uuid', $this->owner);
    }

    // ─── صلاحيات — غير المالك لا يستطيع الإدارة ──────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_owner_cannot_manage_users(): void
    {
        $regularUser = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner' => false,
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('لا تملك صلاحية كافية');

        $this->service->addUser([
            'name'  => 'Test',
            'email' => 'fail@test.com',
        ], $regularUser);
    }

    // ─── تحديث المستخدم ──────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_update_user_info(): void
    {
        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'name' => 'Old Name',
        ]);

        $updated = $this->service->updateUser($user->id, [
            'name' => 'New Name',
            'phone' => '+966500000000',
        ], $this->owner);

        $this->assertEquals('New Name', $updated->name);
        $this->assertEquals('+966500000000', $updated->phone);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function update_creates_audit_log_with_old_and_new_values(): void
    {
        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'name' => 'Before',
        ]);

        $this->service->updateUser($user->id, ['name' => 'After'], $this->owner);

        $this->assertDatabaseHas('audit_logs', [
            'account_id'  => $this->account->id,
            'action'      => 'user.updated',
            'entity_id'   => $user->id,
        ]);
    }
}
