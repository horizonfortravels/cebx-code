<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class DemoBrowserReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);
    }

    #[Test]
    public function b2b_organization_owner_can_open_modern_users_page_without_crashing(): void
    {
        $user = User::query()
            ->withoutGlobalScopes()
            ->where('email', 'e2e.c.organization_owner@example.test')
            ->firstOrFail();

        $response = $this->actingAs($user, 'web')->get('/b2b/users');

        $response->assertOk();
        $response->assertSeeText('فريق المنظمة');
        $response->assertDontSeeText('Internal Server Error');
        $response->assertDontSeeText('Undefined variable');
    }

    #[Test]
    public function b2c_navigation_hides_business_and_developer_sections(): void
    {
        $user = User::query()
            ->withoutGlobalScopes()
            ->where('email', 'e2e.a.individual@example.test')
            ->firstOrFail();

        $response = $this->actingAs($user, 'web')->get('/b2c/shipments');

        $response->assertOk();
        $response->assertSeeText('بوابة الأفراد للحساب الفردي الحالي');
        $response->assertDontSeeText('أدوات المطور');
        $response->assertDontSeeText('المستخدمون');
        $response->assertDontSeeText('الأدوار');
        $response->assertDontSeeText('التقارير');
    }

    #[Test]
    public function internal_super_admin_login_redirects_to_admin_dashboard(): void
    {
        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => 'e2e.internal.super_admin@example.test',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('admin.index'));

        $dashboard = $this->actingAs(
            User::query()->withoutGlobalScopes()->where('email', 'e2e.internal.super_admin@example.test')->firstOrFail(),
            'web'
        )->get(route('admin.index'));

        $dashboard->assertOk();
        $dashboard->assertSeeText('لوحة الإدارة الداخلية');
        $dashboard->assertSeeText('إجمالي الحسابات');
        $dashboard->assertSeeText('أحدث الشحنات');
    }

    #[Test]
    public function unexpected_browser_exceptions_render_safe_html_error_page(): void
    {
        Route::middleware('web')->get('/__demo-browser-error', function (): void {
            throw new RuntimeException('demo-blowup');
        });

        $response = $this->get('/__demo-browser-error');

        $response->assertStatus(500);
        $response->assertSeeText('تعذر إكمال هذه الصفحة الآن');
        $response->assertDontSeeText('RuntimeException');
        $response->assertDontSeeText('demo-blowup');
    }
}
