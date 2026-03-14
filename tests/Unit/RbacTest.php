<?php

namespace Tests\Unit;

use App\Exceptions\BusinessException;
use App\Models\Account;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Rbac\PermissionsCatalog;
use App\Services\RbacService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsPermissions;

class RbacTest extends TestCase
{
    use RefreshDatabase, SeedsPermissions;

    private RbacService $service;
    private Account $account;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->service = new RbacService();

        $this->account = Account::factory()->create(['status' => 'active']);
        $this->owner = User::factory()->owner()->create([
            'account_id' => $this->account->id,
        ]);
    }

    // ─── AC: نجاح — إنشاء دور مخصص ─────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_create_custom_role(): void
    {
        $role = $this->service->createRole([
            'name'         => 'store-manager',
            'display_name' => 'مدير المتجر',
            'description'  => 'إدارة الشحنات والطلبات',
            'permissions'  => ['shipments:view', 'shipments:create', 'orders:view'],
        ], $this->owner);

        $this->assertNotNull($role->id);
        $this->assertEquals('store-manager', $role->name);
        $this->assertEquals($this->account->id, $role->account_id);
        $this->assertFalse($role->is_system);
        $this->assertCount(3, $role->permissions);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function new_role_starts_with_zero_permissions_by_default(): void
    {
        $role = $this->service->createRole([
            'name'         => 'empty-role',
            'display_name' => 'دور فارغ',
        ], $this->owner);

        $this->assertCount(0, $role->permissions);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_create_role_from_template(): void
    {
        $role = $this->service->createFromTemplate('accountant', $this->owner);

        $this->assertEquals('accountant', $role->template);
        $this->assertEquals('محاسب', $role->display_name);

        $template = PermissionsCatalog::template('accountant');
        $this->assertCount(count($template['permissions']), $role->permissions);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function template_permissions_can_be_modified_before_save(): void
    {
        $role = $this->service->createFromTemplate('warehouse', $this->owner, 'custom-warehouse');

        // Now update to remove some permissions
        $updated = $this->service->updateRole($role->id, [
            'permissions' => ['shipments:view', 'orders:view'],
        ], $this->owner);

        $this->assertCount(2, $updated->permissions);
    }

    // ─── AC: فشل شائع — صلاحية غير موجودة في الكتالوج ────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_assign_permission_outside_catalog(): void
    {
        $this->expectException(BusinessException::class);

        $this->service->createRole([
            'name'         => 'bad-role',
            'display_name' => 'دور خاطئ',
            'permissions'  => ['nonexistent:permission'],
        ], $this->owner);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function unknown_permission_returns_correct_error_code(): void
    {
        try {
            $this->service->createRole([
                'name'         => 'bad-role',
                'display_name' => 'دور',
                'permissions'  => ['fake:perm'],
            ], $this->owner);
            $this->fail('Expected exception');
        } catch (BusinessException $e) {
            $this->assertEquals('PERMISSION_UNKNOWN', $e->getErrorCode());
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function duplicate_role_name_in_same_account_rejected(): void
    {
        $this->service->createRole([
            'name' => 'manager', 'display_name' => 'Manager',
        ], $this->owner);

        $this->expectException(BusinessException::class);

        $this->service->createRole([
            'name' => 'manager', 'display_name' => 'Manager 2',
        ], $this->owner);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function same_role_name_in_different_accounts_allowed(): void
    {
        $this->service->createRole([
            'name' => 'manager', 'display_name' => 'Manager',
        ], $this->owner);

        $otherAccount = Account::factory()->create();
        $otherOwner = User::factory()->owner()->create([
            'account_id' => $otherAccount->id,
        ]);

        $role = $this->service->createRole([
            'name' => 'manager', 'display_name' => 'Manager',
        ], $otherOwner);

        $this->assertNotNull($role->id);
    }

    // ─── AC: حالة حدية — عدد كبير من الصلاحيات ───────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function max_permissions_per_role_is_enforced(): void
    {
        // Generate 101 fake permission keys (over limit)
        $fakeKeys = array_fill(0, 101, 'shipments:view');

        $this->expectException(BusinessException::class);

        $this->service->createRole([
            'name'         => 'too-many',
            'display_name' => 'Too Many',
            'permissions'  => $fakeKeys,
        ], $this->owner);
    }

    // ─── Escalation Prevention ───────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_owner_cannot_grant_permissions_they_dont_have(): void
    {
        // Create a user with limited permissions
        $role = Role::factory()->create(['account_id' => $this->account->id]);
        $limitedPerms = Permission::whereIn('key', ['shipments:view'])->pluck('id');
        $role->syncPermissions($limitedPerms->toArray());

        $limitedUser = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner'   => false,
        ]);
        $limitedUser->roles()->attach($role->id);

        // Give them roles:manage permission so they can create roles
        $managePermId = Permission::where('key', 'roles:manage')->first()->id;
        $role->permissions()->attach($managePermId);

        // Try to create a role with more permissions than they have
        $this->expectException(BusinessException::class);

        $this->service->createRole([
            'name'         => 'escalated-role',
            'display_name' => 'Escalated',
            'permissions'  => ['financial:view', 'financial:wallet_topup'], // they don't have these
        ], $limitedUser);
    }

    // ─── Role Assignment ─────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_assign_role_to_user(): void
    {
        $role = $this->service->createRole([
            'name' => 'viewer', 'display_name' => 'Viewer',
            'permissions' => ['shipments:view'],
        ], $this->owner);

        $user = User::factory()->create(['account_id' => $this->account->id]);

        $result = $this->service->assignRoleToUser($user->id, $role->id, $this->owner);

        $this->assertTrue($result->roles->contains('id', $role->id));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_assign_same_role_twice(): void
    {
        $role = $this->service->createRole([
            'name' => 'viewer', 'display_name' => 'Viewer',
        ], $this->owner);

        $user = User::factory()->create(['account_id' => $this->account->id]);
        $this->service->assignRoleToUser($user->id, $role->id, $this->owner);

        $this->expectException(BusinessException::class);
        $this->service->assignRoleToUser($user->id, $role->id, $this->owner);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_revoke_role_from_user(): void
    {
        $role = $this->service->createRole([
            'name' => 'temp-role', 'display_name' => 'Temp',
        ], $this->owner);

        $user = User::factory()->create(['account_id' => $this->account->id]);
        $this->service->assignRoleToUser($user->id, $role->id, $this->owner);
        $result = $this->service->revokeRoleFromUser($user->id, $role->id, $this->owner);

        $this->assertFalse($result->roles->contains('id', $role->id));
    }

    // ─── Permission Checking ─────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_has_all_permissions(): void
    {
        $this->assertTrue($this->owner->hasPermission('shipments:create'));
        $this->assertTrue($this->owner->hasPermission('financial:view'));
        $this->assertTrue($this->owner->hasPermission('any:thing'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_without_role_has_no_permissions(): void
    {
        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner' => false,
        ]);

        $this->assertFalse($user->hasPermission('shipments:view'));
        $this->assertEmpty($user->allPermissions());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_gets_permissions_from_assigned_role(): void
    {
        $role = $this->service->createRole([
            'name' => 'shipper', 'display_name' => 'Shipper',
            'permissions' => ['shipments:view', 'shipments:create'],
        ], $this->owner);

        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner' => false,
        ]);
        $this->service->assignRoleToUser($user->id, $role->id, $this->owner);

        $this->assertTrue($user->hasPermission('shipments:view'));
        $this->assertTrue($user->hasPermission('shipments:create'));
        $this->assertFalse($user->hasPermission('financial:view'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_with_multiple_roles_gets_union_of_permissions(): void
    {
        $role1 = $this->service->createRole([
            'name' => 'role-a', 'display_name' => 'A',
            'permissions' => ['shipments:view'],
        ], $this->owner);

        $role2 = $this->service->createRole([
            'name' => 'role-b', 'display_name' => 'B',
            'permissions' => ['financial:view'],
        ], $this->owner);

        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner' => false,
        ]);
        $this->service->assignRoleToUser($user->id, $role1->id, $this->owner);
        $this->service->assignRoleToUser($user->id, $role2->id, $this->owner);

        $perms = $user->allPermissions();
        $this->assertContains('shipments:view', $perms);
        $this->assertContains('financial:view', $perms);
    }

    // ─── System Roles Protection ─────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_delete_system_role(): void
    {
        $systemRole = Role::withoutGlobalScopes()->create([
            'account_id' => $this->account->id,
            'name' => 'system-owner',
            'display_name' => 'System Owner',
            'is_system' => true,
        ]);

        $this->expectException(BusinessException::class);
        $this->service->deleteRole($systemRole->id, $this->owner);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_delete_role_with_assigned_users(): void
    {
        $role = $this->service->createRole([
            'name' => 'in-use', 'display_name' => 'In Use',
        ], $this->owner);

        $user = User::factory()->create(['account_id' => $this->account->id]);
        $this->service->assignRoleToUser($user->id, $role->id, $this->owner);

        $this->expectException(BusinessException::class);
        $this->service->deleteRole($role->id, $this->owner);
    }

    // ─── Audit Logging ───────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function role_creation_is_logged(): void
    {
        $role = $this->service->createRole([
            'name' => 'logged-role', 'display_name' => 'Logged',
        ], $this->owner);

        $this->assertDatabaseHas('audit_logs', [
            'account_id'  => $this->account->id,
            'action'      => 'role.created',
            'entity_type' => 'Role',
            'entity_id'   => $role->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function role_assignment_is_logged(): void
    {
        $role = $this->service->createRole([
            'name' => 'assign-test', 'display_name' => 'Assign',
        ], $this->owner);

        $user = User::factory()->create(['account_id' => $this->account->id]);
        $this->service->assignRoleToUser($user->id, $role->id, $this->owner);

        $this->assertDatabaseHas('audit_logs', [
            'account_id' => $this->account->id,
            'action'     => 'role.assigned',
            'entity_id'  => $user->id,
        ]);
    }
}
