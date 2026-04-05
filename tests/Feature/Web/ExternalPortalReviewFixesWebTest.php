<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExternalPortalReviewFixesWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);
    }

    #[Test]
    public function limited_b2b_member_does_not_see_permission_gated_read_centers_or_open_them(): void
    {
        $owner = User::query()->withoutGlobalScopes()->where('email', 'e2e.c.organization_owner@example.test')->firstOrFail();

        $user = User::factory()->create([
            'account_id' => (string) $owner->account_id,
            'user_type' => 'external',
            'status' => 'active',
            'locale' => 'ar',
        ]);

        $this->grantTenantPermissions($user, [
            'shipments.read',
            'shipments.create',
            'orders.read',
            'addresses.read',
        ], 'limited_b2b_member');

        $dashboard = $this->actingAs($user, 'web')->get(route('b2b.dashboard'));

        $dashboard->assertOk();
        $dashboard->assertDontSee('href="'.route('b2b.wallet.index').'"', false);
        $dashboard->assertDontSee('href="'.route('b2b.users.index').'"', false);
        $dashboard->assertDontSee('href="'.route('b2b.roles.index').'"', false);
        $dashboard->assertDontSee('href="'.route('b2b.reports.index').'"', false);

        $this->actingAs($user, 'web')->get(route('b2b.wallet.index'))->assertForbidden();
        $this->actingAs($user, 'web')->get(route('b2b.users.index'))->assertForbidden();
        $this->actingAs($user, 'web')->get(route('b2b.roles.index'))->assertForbidden();
        $this->actingAs($user, 'web')->get(route('b2b.reports.index'))->assertForbidden();
    }

    #[Test]
    public function b2b_settings_page_renders_in_the_modern_portal_shell(): void
    {
        $user = User::query()->withoutGlobalScopes()->where('email', 'e2e.c.organization_owner@example.test')->firstOrFail();

        $this->actingAs($user, 'web')
            ->get(route('b2b.settings.index'))
            ->assertOk()
            ->assertSeeText('إعدادات حساب المنظمة')
            ->assertSeeText('بوابة الأعمال أصبحت المسار الأساسي')
            ->assertDontSeeText('Internal Server Error');
    }
}
