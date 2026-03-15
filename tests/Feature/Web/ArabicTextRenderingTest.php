<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ArabicTextRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);
    }

    #[Test]
    public function internal_admin_dashboard_renders_readable_arabic_copy(): void
    {
        $user = User::query()
            ->withoutGlobalScopes()
            ->where('email', 'e2e.internal.super_admin@example.test')
            ->firstOrFail();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk();
        $response->assertSeeText('لوحة الإدارة العامة');
        $response->assertSeeText('لا يوجد حساب محدد بعد');
        $response->assertDontSee('???', false);
    }

    #[Test]
    public function suspended_b2b_login_shows_readable_arabic_message(): void
    {
        $response = $this->from('/b2b/login')->post('/b2b/login', [
            'email' => 'e2e.c.suspended@example.test',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/b2b/login');
        $response->assertSessionHasErrors([
            'email' => 'تم تعليق هذا الحساب مؤقتًا. تواصل مع الدعم أو مدير الحساب لمراجعة حالة الوصول.',
        ]);

        $this->get('/b2b/login')
            ->assertOk()
            ->assertSeeText('تم تعليق هذا الحساب مؤقتًا. تواصل مع الدعم أو مدير الحساب لمراجعة حالة الوصول.');
    }

    #[Test]
    public function external_admin_forbidden_page_renders_readable_arabic_copy(): void
    {
        $user = User::query()
            ->withoutGlobalScopes()
            ->where('email', 'e2e.a.individual@example.test')
            ->firstOrFail();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertForbidden();
        $response->assertSeeText('هذه الصفحة مخصصة لفريق التشغيل الداخلي في المنصة');
        $response->assertSeeText('العودة إلى بوابة الأفراد');
        $response->assertDontSeeText('PERMISSION DENIED');
    }
}
