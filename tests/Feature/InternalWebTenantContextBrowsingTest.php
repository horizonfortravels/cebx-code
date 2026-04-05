<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Support\Tenancy\WebTenantContext;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternalWebTenantContextBrowsingTest extends TestCase
{
    public function test_internal_super_admin_can_open_admin_dashboard_without_account_id(): void
    {
        $user = $this->createInternalSuperAdmin();

        $response = $this->actingAs($user, 'web')->get(route('admin.index'));

        $response->assertOk();
        $response->assertSeeText('لوحة الإدارة الداخلية');
        $response->assertSeeText('إجمالي الحسابات');
        $response->assertSeeText('أحدث الشحنات');
        $response->assertSee('/admin/tenant-context', false);
    }

    public function test_internal_tenant_bound_page_redirects_to_selector_until_account_is_chosen(): void
    {
        $user = $this->createInternalSuperAdmin();
        $account = Account::factory()->create(['type' => 'organization']);

        $response = $this->actingAs($user, 'web')->get(route('admin.users'));

        $response->assertRedirect();
        $location = $response->headers->get('Location', '');
        $this->assertStringContainsString('/admin/tenant-context', $location);

        $selector = $this->actingAs($user, 'web')->get($location);
        $selector->assertOk();
        $selector->assertSee('name="account_id"', false);

        $this->actingAs($user, 'web')->post(route('admin.tenant-context.store'), [
            'account_id' => (string) $account->id,
            'redirect' => route('admin.users'),
        ])->assertRedirect(route('admin.users'));

        $session = [WebTenantContext::sessionKey() => (string) $account->id];

        $this->actingAs($user, 'web')
            ->withSession($session)
            ->get(route('admin.users'))
            ->assertOk()
            ->assertSee($account->name);

        $this->actingAs($user, 'web')
            ->withSession($session)
            ->get(route('admin.roles'))
            ->assertOk();

        $this->actingAs($user, 'web')
            ->withSession($session)
            ->get(route('admin.reports'))
            ->assertOk();
    }

    public function test_external_user_cannot_access_internal_admin_routes(): void
    {
        $account = Account::factory()->create(['type' => 'organization']);

        $user = User::factory()->create([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'web')->get(route('admin.index'));

        $response->assertStatus(403);
    }

    private function createInternalSuperAdmin(): User
    {
        $user = User::factory()->create([
            'account_id' => null,
            'user_type' => 'internal',
            'status' => 'active',
            'locale' => 'en',
            'timezone' => 'UTC',
        ]);

        $roleId = (string) DB::table('internal_roles')->where('name', 'super_admin')->value('id');
        $this->assertNotSame('', $roleId, 'Expected seeded internal role super_admin to exist.');

        DB::table('internal_user_role')->updateOrInsert(
            [
                'user_id' => (string) $user->id,
                'internal_role_id' => $roleId,
            ],
            [
                'assigned_by' => null,
                'assigned_at' => now(),
            ]
        );

        return $user->fresh();
    }
}
