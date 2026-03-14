<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Rbac\PermissionsCatalog;
use App\Services\RbacService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\SeedsPermissions;

class RbacApiTest extends TestCase
{
    use RefreshDatabase, SeedsPermissions;

    private Account $account;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();

        $this->account = Account::factory()->create(['status' => 'active']);
        $this->owner = User::factory()->owner()->create([
            'account_id' => $this->account->id,
        ]);
    }

    private function actAsOwner(): void
    {
        Sanctum::actingAs($this->owner);
        app()->instance('current_account_id', $this->account->id);
    }

    // ─── POST /api/v1/roles — إنشاء دور ─────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_create_role_via_api(): void
    {
        $this->actAsOwner();

        $response = $this->postJson('/api/v1/roles', [
            'name'         => 'store-manager',
            'display_name' => 'مدير المتجر',
            'description'  => 'إدارة الشحنات',
            'permissions'  => ['shipments:view', 'shipments:create', 'orders:view'],
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.name', 'store-manager')
                 ->assertJsonPath('data.is_system', false)
                 ->assertJsonCount(3, 'data.permissions');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function new_role_without_permissions_starts_empty(): void
    {
        $this->actAsOwner();

        $response = $this->postJson('/api/v1/roles', [
            'name'         => 'empty-role',
            'display_name' => 'فارغ',
        ]);

        $response->assertStatus(201)
                 ->assertJsonCount(0, 'data.permissions');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function creating_role_with_unknown_permission_returns_error(): void
    {
        $this->actAsOwner();

        $response = $this->postJson('/api/v1/roles', [
            'name'         => 'bad-role',
            'display_name' => 'Bad',
            'permissions'  => ['nonexistent:perm'],
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('error_code', 'PERMISSION_UNKNOWN');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function creating_duplicate_role_name_returns_error(): void
    {
        $this->actAsOwner();

        $this->postJson('/api/v1/roles', [
            'name' => 'manager', 'display_name' => 'Manager',
        ])->assertStatus(201);

        $response = $this->postJson('/api/v1/roles', [
            'name' => 'manager', 'display_name' => 'Manager 2',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('error_code', 'ERR_ROLE_EXISTS');
    }

    // ─── POST /api/v1/roles/from-template ────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_create_role_from_template_via_api(): void
    {
        $this->actAsOwner();

        $response = $this->postJson('/api/v1/roles/from-template', [
            'template' => 'accountant',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.template', 'accountant')
                 ->assertJsonPath('data.display_name', 'محاسب');

        $template = PermissionsCatalog::template('accountant');
        $response->assertJsonCount(count($template['permissions']), 'data.permissions');
    }

    // ─── GET /api/v1/roles — قائمة الأدوار ───────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_list_roles(): void
    {
        $this->actAsOwner();

        $this->postJson('/api/v1/roles', [
            'name' => 'role-a', 'display_name' => 'A',
        ]);
        $this->postJson('/api/v1/roles', [
            'name' => 'role-b', 'display_name' => 'B',
        ]);

        $response = $this->getJson('/api/v1/roles');

        $response->assertOk()
                 ->assertJsonCount(2, 'data');
    }

    // ─── PUT /api/v1/roles/{id} — تحديث الدور ────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_update_role_permissions(): void
    {
        $this->actAsOwner();

        $createResp = $this->postJson('/api/v1/roles', [
            'name' => 'updatable', 'display_name' => 'Updatable',
            'permissions' => ['shipments:view'],
        ]);

        $roleId = $createResp->json('data.id');

        $response = $this->putJson("/api/v1/roles/{$roleId}", [
            'permissions' => ['shipments:view', 'shipments:create', 'orders:view'],
        ]);

        $response->assertOk()
                 ->assertJsonCount(3, 'data.permissions');
    }

    // ─── Role Assignment ─────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_assign_role_to_user_via_api(): void
    {
        $this->actAsOwner();

        $roleResp = $this->postJson('/api/v1/roles', [
            'name' => 'shipper', 'display_name' => 'Shipper',
            'permissions' => ['shipments:view'],
        ]);
        $roleId = $roleResp->json('data.id');

        $user = User::factory()->create(['account_id' => $this->account->id]);

        $response = $this->postJson("/api/v1/roles/{$roleId}/assign/{$user->id}");

        $response->assertOk()
                 ->assertJsonPath('data.user_id', $user->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_revoke_role_via_api(): void
    {
        $this->actAsOwner();

        $roleResp = $this->postJson('/api/v1/roles', [
            'name' => 'revocable', 'display_name' => 'Revocable',
        ]);
        $roleId = $roleResp->json('data.id');

        $user = User::factory()->create(['account_id' => $this->account->id]);
        $this->postJson("/api/v1/roles/{$roleId}/assign/{$user->id}");

        $response = $this->deleteJson("/api/v1/roles/{$roleId}/revoke/{$user->id}");

        $response->assertOk();
    }

    // ─── GET /api/v1/permissions — كتالوج الصلاحيات ──────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_get_permissions_catalog(): void
    {
        $this->actAsOwner();

        $response = $this->getJson('/api/v1/permissions');

        $response->assertOk()
                 ->assertJsonStructure(['data' => ['users', 'roles', 'shipments', 'financial']]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_get_role_templates(): void
    {
        $this->actAsOwner();

        $response = $this->getJson('/api/v1/roles/templates');

        $response->assertOk()
                 ->assertJsonStructure(['data' => ['admin', 'accountant', 'warehouse', 'viewer', 'printer']]);
    }

    // ─── GET /api/v1/users/{id}/permissions — صلاحيات المستخدم ───

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_get_user_effective_permissions(): void
    {
        $this->actAsOwner();

        $role = (new RbacService())->createRole([
            'name' => 'test-perms', 'display_name' => 'Test',
            'permissions' => ['shipments:view', 'orders:view'],
        ], $this->owner);

        $user = User::factory()->create(['account_id' => $this->account->id]);
        (new RbacService())->assignRoleToUser($user->id, $role->id, $this->owner);

        $response = $this->getJson("/api/v1/users/{$user->id}/permissions");

        $response->assertOk()
                 ->assertJsonPath('data.is_owner', false);

        $perms = $response->json('data.permissions');
        $this->assertContains('shipments:view', $perms);
        $this->assertContains('orders:view', $perms);
        $this->assertNotContains('financial:view', $perms);
    }

    // ─── 403 Enforcement — الميزة الأهم في RBAC ─────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_without_permission_gets_403(): void
    {
        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner' => false,
        ]);

        Sanctum::actingAs($user);
        app()->instance('current_account_id', $this->account->id);

        // Try to create a role without roles:manage permission
        $response = $this->postJson('/api/v1/roles', [
            'name' => 'unauthorized', 'display_name' => 'Unauthorized',
        ]);

        $response->assertStatus(403)
                 ->assertJsonPath('error_code', 'ERR_PERMISSION');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_with_correct_permission_can_access(): void
    {
        // Create a user with roles:manage permission
        $role = Role::factory()->create(['account_id' => $this->account->id]);
        $permIds = Permission::whereIn('key', ['roles:manage', 'roles:view'])->pluck('id');
        $role->syncPermissions($permIds->toArray());

        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner' => false,
        ]);
        $user->roles()->attach($role->id);

        Sanctum::actingAs($user);
        app()->instance('current_account_id', $this->account->id);

        // Should be able to list roles with roles:view permission
        $response = $this->getJson('/api/v1/roles');

        $response->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_owner_cannot_escalate_permissions(): void
    {
        // Create user with limited permissions
        $role = Role::factory()->create(['account_id' => $this->account->id]);
        $permIds = Permission::whereIn('key', ['roles:manage', 'shipments:view'])->pluck('id');
        $role->syncPermissions($permIds->toArray());

        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner' => false,
        ]);
        $user->roles()->attach($role->id);

        Sanctum::actingAs($user);
        app()->instance('current_account_id', $this->account->id);

        // Try to create a role with financial permissions they don't have
        $response = $this->postJson('/api/v1/roles', [
            'name'         => 'escalated',
            'display_name' => 'Escalated',
            'permissions'  => ['financial:view', 'financial:wallet_topup'],
        ]);

        $response->assertStatus(403)
                 ->assertJsonPath('error_code', 'ERR_ESCALATION_DENIED');
    }

    // ─── DELETE /api/v1/roles/{id} — حذف الدور ───────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_delete_custom_role(): void
    {
        $this->actAsOwner();

        $resp = $this->postJson('/api/v1/roles', [
            'name' => 'deletable', 'display_name' => 'Deletable',
        ]);

        $roleId = $resp->json('data.id');

        $response = $this->deleteJson("/api/v1/roles/{$roleId}");

        $response->assertOk()
                 ->assertJsonPath('success', true);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_delete_role_with_users_assigned(): void
    {
        $this->actAsOwner();

        $resp = $this->postJson('/api/v1/roles', [
            'name' => 'in-use-role', 'display_name' => 'In Use',
        ]);
        $roleId = $resp->json('data.id');

        $user = User::factory()->create(['account_id' => $this->account->id]);
        $this->postJson("/api/v1/roles/{$roleId}/assign/{$user->id}");

        $response = $this->deleteJson("/api/v1/roles/{$roleId}");

        $response->assertStatus(409)
                 ->assertJsonPath('error_code', 'ERR_ROLE_IN_USE');
    }

    // ─── Tenant Isolation ─────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function roles_are_tenant_isolated(): void
    {
        $this->actAsOwner();

        $this->postJson('/api/v1/roles', [
            'name' => 'my-role', 'display_name' => 'My Role',
        ]);

        // Create another account
        $otherAccount = Account::factory()->create();
        $otherOwner = User::factory()->owner()->create([
            'account_id' => $otherAccount->id,
        ]);

        Sanctum::actingAs($otherOwner);
        app()->instance('current_account_id', $otherAccount->id);

        $response = $this->getJson('/api/v1/roles');

        $response->assertOk()
                 ->assertJsonCount(0, 'data'); // Other account sees no roles
    }
}
