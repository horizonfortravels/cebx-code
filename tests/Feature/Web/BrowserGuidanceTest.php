<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BrowserGuidanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);
    }

    #[Test]
    public function external_user_sees_guided_html_when_opening_admin_area(): void
    {
        $user = User::query()->withoutGlobalScopes()->where('email', 'e2e.a.individual@example.test')->firstOrFail();

        $response = $this->actingAs($user, 'web')->get('/admin');

        $response->assertForbidden();
        $response->assertSeeText('هذه الصفحة مخصصة لفريق التشغيل الداخلي');
        $response->assertSeeText('العودة إلى بوابة الأفراد');
    }

    #[Test]
    public function b2c_user_sees_wrong_portal_guidance_instead_of_silent_redirect(): void
    {
        $user = User::query()->withoutGlobalScopes()->where('email', 'e2e.a.individual@example.test')->firstOrFail();

        $response = $this->actingAs($user, 'web')->get('/b2b/shipments');

        $response->assertForbidden();
        $response->assertSeeText('هذه المنطقة مخصصة لبوابة الأعمال');
        $response->assertSeeText('العودة إلى بوابة الأفراد');
    }

    #[Test]
    public function internal_support_user_lands_on_internal_home_after_login(): void
    {
        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => 'e2e.internal.support@example.test',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('internal.home'));

        $this->actingAs(
            User::query()->withoutGlobalScopes()->where('email', 'e2e.internal.support@example.test')->firstOrFail(),
            'web'
        )->get(route('internal.home'))
            ->assertOk()
            ->assertSeeText('المساحة الداخلية')
            ->assertSeeText('اختيار الحساب');
    }

    #[Test]
    public function disabled_login_failure_is_clear_and_readable(): void
    {
        $response = $this->from('/b2b/login')->post('/b2b/login', [
            'email' => 'e2e.c.disabled@example.test',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/b2b/login');
        $response->assertSessionHasErrors([
            'email' => 'تم إيقاف هذا الحساب حاليًا. تواصل مع الدعم أو مدير الحساب لإعادة التفعيل.',
        ]);
    }
}
