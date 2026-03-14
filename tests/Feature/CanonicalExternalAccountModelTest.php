<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\E2EUserMatrixSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithStrictRbac;
use Tests\TestCase;

class CanonicalExternalAccountModelTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithStrictRbac;

    #[Test]
    public function individual_account_cannot_add_or_invite_a_second_external_user(): void
    {
        $account = Account::factory()->individual()->create(['status' => 'active']);
        $owner = User::factory()->owner()->create([
            'account_id' => $account->id,
            'user_type' => 'external',
            'email' => 'individual.owner.canonical@gmail.com',
        ]);

        $this->grantTenantPermissions($owner, [
            'users.read',
            'users.manage',
            'users.invite',
            'roles.read',
            'roles.manage',
            'roles.assign',
        ], 'individual_primary_permissions');

        Sanctum::actingAs($owner);
        app()->instance('current_account_id', $account->id);

        $createUser = $this->postJson('/api/v1/users', [
            'name' => 'Second User',
            'email' => 'second.user.canonical@gmail.com',
        ]);

        $this->assertContains($createUser->status(), [403, 409]);
        $this->assertSame(1, $this->externalUserCountForAccount((string) $account->id));

        $inviteUser = $this->postJson('/api/v1/invitations', [
            'email' => 'invited.user.canonical@gmail.com',
            'name' => 'Invited User',
        ]);

        $this->assertContains($inviteUser->status(), [403, 409]);
        $this->assertDatabaseMissing('invitations', [
            'account_id' => $account->id,
            'email' => 'invited.user.canonical@gmail.com',
        ]);
    }

    #[Test]
    public function organization_account_can_support_multi_user_behavior(): void
    {
        $account = Account::factory()->organization()->create(['status' => 'active']);
        $owner = User::factory()->owner()->create([
            'account_id' => $account->id,
            'user_type' => 'external',
            'email' => 'organization.owner.canonical@gmail.com',
        ]);

        $this->grantTenantPermissions($owner, [
            'users.read',
            'users.manage',
            'users.invite',
            'roles.read',
            'roles.manage',
            'roles.assign',
        ], 'organization_owner_permissions');

        Sanctum::actingAs($owner);
        app()->instance('current_account_id', $account->id);

        $this->postJson('/api/v1/users', [
            'name' => 'Org Team Member',
            'email' => 'org.team.member.canonical@gmail.com',
        ])->assertStatus(201);

        $this->postJson('/api/v1/invitations', [
            'email' => 'org.invite.canonical@gmail.com',
            'name' => 'Org Invite',
        ])->assertStatus(201);
    }

    #[Test]
    public function organization_owner_remains_the_highest_canonical_external_role(): void
    {
        $account = Account::factory()->organization()->create(['status' => 'active']);
        $this->seed(RolesAndPermissionsSeeder::class);

        $ownerRole = Role::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->where('name', 'organization_owner')
            ->firstOrFail();

        $adminRole = Role::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->where('name', 'organization_admin')
            ->firstOrFail();

        $staffRole = Role::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->where('name', 'staff')
            ->firstOrFail();

        $ownerPermissions = $this->permissionKeysForRole((string) $ownerRole->id);
        $adminPermissions = $this->permissionKeysForRole((string) $adminRole->id);
        $staffPermissions = $this->permissionKeysForRole((string) $staffRole->id);

        $this->assertContains('roles.manage', $ownerPermissions);
        $this->assertContains('roles.assign', $ownerPermissions);
        $this->assertContains('organizations.manage', $ownerPermissions);
        $this->assertContains('api_keys.manage', $ownerPermissions);
        $this->assertContains('webhooks.manage', $ownerPermissions);

        $this->assertContains('users.manage', $adminPermissions);
        $this->assertContains('users.invite', $adminPermissions);
        $this->assertContains('api_keys.manage', $adminPermissions);
        $this->assertNotContains('roles.manage', $adminPermissions);
        $this->assertNotContains('roles.assign', $adminPermissions);
        $this->assertNotContains('organizations.manage', $adminPermissions);

        $this->assertContains('shipments.read', $staffPermissions);
        $this->assertNotContains('users.manage', $staffPermissions);
        $this->assertNotContains('api_keys.manage', $staffPermissions);
    }

    #[Test]
    public function canonical_seed_data_removes_api_developer_and_legacy_external_role_names(): void
    {
        $this->seed(E2EUserMatrixSeeder::class);

        $this->assertDatabaseMissing('roles', ['name' => 'tenant_owner']);
        $this->assertDatabaseMissing('roles', ['name' => 'tenant_admin']);
        $this->assertDatabaseMissing('roles', ['name' => 'api_developer']);

        $legacyEmails = DB::table('users')
            ->where('email', 'like', '%api_developer%')
            ->orWhere('email', 'like', '%tenant_owner%')
            ->pluck('email')
            ->all();

        $this->assertSame([], $legacyEmails);
    }

    #[Test]
    public function seeded_e2e_matrix_respects_individual_and_organization_account_rules(): void
    {
        $this->seed(E2EUserMatrixSeeder::class);

        $accountA = Account::withoutGlobalScopes()->where('slug', 'e2e-account-a')->firstOrFail();
        $accountB = Account::withoutGlobalScopes()->where('slug', 'e2e-account-b')->firstOrFail();
        $accountC = Account::withoutGlobalScopes()->where('slug', 'e2e-account-c')->firstOrFail();
        $accountD = Account::withoutGlobalScopes()->where('slug', 'e2e-account-d')->firstOrFail();

        $this->assertSame('individual', $accountA->type);
        $this->assertSame('individual', $accountB->type);
        $this->assertSame('organization', $accountC->type);
        $this->assertSame('organization', $accountD->type);

        $this->assertSame(1, $this->externalUserCountForAccount((string) $accountA->id));
        $this->assertSame(1, $this->externalUserCountForAccount((string) $accountB->id));
        $this->assertGreaterThanOrEqual(3, $this->externalUserCountForAccount((string) $accountC->id));
        $this->assertGreaterThanOrEqual(3, $this->externalUserCountForAccount((string) $accountD->id));

        $this->assertSame(
            'individual_account_holder',
            $this->primaryRoleNameForUserEmail('e2e.a.individual@example.test')
        );
        $this->assertSame(
            'individual_account_holder',
            $this->primaryRoleNameForUserEmail('e2e.b.individual@example.test')
        );
    }

    /**
     * @return array<int, string>
     */
    private function permissionKeysForRole(string $roleId): array
    {
        return DB::table('role_permission')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('role_permission.role_id', $roleId)
            ->pluck('permissions.key')
            ->map(static fn ($key): string => (string) $key)
            ->all();
    }

    private function externalUserCountForAccount(string $accountId): int
    {
        return User::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('user_type', 'external')
            ->count();
    }

    private function primaryRoleNameForUserEmail(string $email): ?string
    {
        return DB::table('user_role')
            ->join('users', 'users.id', '=', 'user_role.user_id')
            ->join('roles', 'roles.id', '=', 'user_role.role_id')
            ->where('users.email', $email)
            ->value('roles.name');
    }
}
