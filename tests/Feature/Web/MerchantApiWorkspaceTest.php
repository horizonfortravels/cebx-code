<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MerchantApiWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);
    }

    #[Test]
    public function organization_admin_sees_platform_api_tools_on_b2b_dashboard_and_can_open_workspace(): void
    {
        $user = User::query()->withoutGlobalScopes()->where('email', 'e2e.c.organization_admin@example.test')->firstOrFail();

        $this->actingAs($user, 'web')
            ->get(route('b2b.dashboard'))
            ->assertOk()
            ->assertSeeText('أدوات المطور')
            ->assertSeeText('واجهة المطور');

        $this->actingAs($user, 'web')
            ->get(route('b2b.developer.index'))
            ->assertOk()
            ->assertSeeText('واجهة المطور')
            ->assertSeeText('مفاتيح API');
    }

    #[Test]
    public function staff_does_not_see_developer_navigation_and_cannot_open_it(): void
    {
        $user = User::query()->withoutGlobalScopes()->where('email', 'e2e.c.staff@example.test')->firstOrFail();

        $this->actingAs($user, 'web')
            ->get(route('b2b.dashboard'))
            ->assertOk()
            ->assertDontSeeText('أدوات المطور')
            ->assertDontSeeText('واجهة المطور');

        $this->actingAs($user, 'web')
            ->get(route('b2b.developer.index'))
            ->assertForbidden();
    }

    #[Test]
    public function organization_owner_can_still_open_platform_api_pages_when_permissions_allow(): void
    {
        $user = User::query()->withoutGlobalScopes()->where('email', 'e2e.c.organization_owner@example.test')->firstOrFail();

        $this->actingAs($user, 'web')
            ->get(route('b2b.developer.webhooks'))
            ->assertOk()
            ->assertSeeText('مركز الويبهوكات');
    }
}
