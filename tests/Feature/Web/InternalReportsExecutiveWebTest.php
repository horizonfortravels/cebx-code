<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

class InternalReportsExecutiveWebTest extends SeededReadOnlyWebTestCase
{
    #[Test]
    public function super_admin_can_open_the_executive_dashboard_without_export_controls(): void
    {
        $this->actingAs($this->userByEmail('e2e.internal.super_admin@example.test'), 'web')
            ->get(route('internal.reports.executive'))
            ->assertOk()
            ->assertSee('data-testid="internal-report-dashboard"', false)
            ->assertSeeText('لوحة الربحية التنفيذية')
            ->assertSeeText('اللقطة التجارية المقتبسة')
            ->assertSeeText('لقطة أداء شركات الشحن')
            ->assertSeeText('ملخص حمل مركز الدعم')
            ->assertSeeText('لقطة نشاط المحفظة الآمن')
            ->assertSeeText('إجمالي الشحنات')
            ->assertSeeText('تم تسليمها (معياريًا)')
            ->assertSeeText('الاستثناءات المفتوحة')
            ->assertSeeText('الحجوزات النشطة')
            ->assertSeeText('ردود الفريق المرئية للعميل (7 أيام)')
            ->assertDontSee('data-testid="internal-report-dashboard-export-link"', false)
            ->assertDontSeeText('i8a-shopify-token-001')
            ->assertDontSeeText('Internal escalation note for leadership only.');
    }

    #[Test]
    public function executive_card_is_only_visible_to_super_admin_on_the_reports_hub(): void
    {
        $this->actingAs($this->userByEmail('e2e.internal.super_admin@example.test'), 'web')
            ->get(route('internal.reports.index'))
            ->assertOk()
            ->assertSee('data-testid="internal-report-card-executive"', false)
            ->assertSee('data-testid="internal-report-card-executive-dashboard-link"', false)
            ->assertSeeText('المؤشرات التنفيذية');

        foreach ([
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $this->actingAs($this->userByEmail($email), 'web')
                ->get(route('internal.reports.index'))
                ->assertOk()
                ->assertDontSee('data-testid="internal-report-card-executive"', false)
                ->assertDontSeeText('المؤشرات التنفيذية');
        }
    }

    #[Test]
    public function support_ops_readonly_carrier_manager_and_external_users_are_forbidden_from_the_executive_dashboard(): void
    {
        foreach ([
            'e2e.internal.support@example.test',
            'e2e.internal.ops_readonly@example.test',
            'e2e.internal.carrier_manager@example.test',
            'e2e.c.organization_owner@example.test',
        ] as $email) {
            $this->assertForbiddenInternalSurface(
                $this->actingAs($this->userByEmail($email), 'web')
                    ->get(route('internal.reports.executive'))
            );
        }
    }

    private function userByEmail(string $email): User
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('email', $email)
            ->firstOrFail();
    }

    private function assertForbiddenInternalSurface(TestResponse $response): void
    {
        $response->assertForbidden()
            ->assertSee('class="panel"', false)
            ->assertSeeText('403')
            ->assertDontSeeText('Internal Server Error');
    }
}
