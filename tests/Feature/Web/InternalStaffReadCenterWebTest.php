<?php

namespace Tests\Feature\Web;

use App\Models\Permission;
use App\Models\User;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalStaffReadCenterWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);
    }

    #[Test]
    public function super_admin_and_support_can_open_staff_index_and_detail(): void
    {
        $supportUser = $this->userByEmail('e2e.internal.support@example.test');

        foreach ([
            'e2e.internal.super_admin@example.test',
            'e2e.internal.support@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $index = $this->actingAs($user, 'web')
                ->get(route('internal.staff.index'))
                ->assertOk()
                ->assertSeeText('دليل فريق المنصة')
                ->assertSeeText('E2E Internal Super Admin')
                ->assertSeeText('E2E Internal Support');

            $this->assertHasNavigationLink($index, 'internal.staff.index');

            $this->actingAs($user, 'web')
                ->get(route('internal.staff.show', $supportUser))
                ->assertOk()
                ->assertSeeText('ملخص الموظف')
                ->assertSeeText('الدور الداخلي')
                ->assertSeeText('ملخص الصلاحيات')
                ->assertSee('data-testid="staff-canonical-role-key"', false)
                ->assertSeeText('support')
                ->assertSeeText('يعرض هذا الملخص للقراءة فقط وهو مشتق من الدور الداخلي المعتمد الحالي.')
                ->assertDontSeeText('finance')
                ->assertDontSeeText('integration_admin')
                ->assertDontSeeText('ops')
                ->assertSeeText('E2E Internal Support');
        }
    }

    #[Test]
    public function staff_index_supports_search_and_basic_filters(): void
    {
        $viewer = $this->userByEmail('e2e.internal.super_admin@example.test');
        $suspendedUser = $this->userByEmail('e2e.internal.carrier_manager@example.test');
        $suspendedUser->forceFill(['status' => 'suspended'])->save();
        $legacyUser = $this->createLegacyInternalUser('finance');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.staff.index', ['q' => 'Support']))
            ->assertOk()
            ->assertSeeText('E2E Internal Support')
            ->assertDontSeeText('E2E Internal Carrier Manager');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.staff.index', ['role' => 'support']))
            ->assertOk()
            ->assertSeeText('E2E Internal Support')
            ->assertDontSeeText('E2E Internal Carrier Manager');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.staff.index', ['status' => 'suspended']))
            ->assertOk()
            ->assertSeeText('E2E Internal Carrier Manager')
            ->assertDontSeeText('E2E Internal Support');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.staff.index', ['deprecated' => 'flagged']))
            ->assertOk()
            ->assertSeeText($legacyUser->name)
            ->assertDontSeeText('E2E Internal Support');
    }

    #[Test]
    public function staff_index_lists_internal_staff_only_and_excludes_external_users(): void
    {
        $viewer = $this->userByEmail('e2e.internal.super_admin@example.test');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.staff.index'))
            ->assertOk()
            ->assertSeeText('E2E Internal Super Admin')
            ->assertSeeText('E2E Internal Support')
            ->assertDontSeeText('e2e.a.individual@example.test')
            ->assertDontSeeText('e2e.c.organization_owner@example.test');
    }

    #[Test]
    public function super_admin_and_support_see_staff_navigation_while_denied_roles_do_not(): void
    {
        $superAdminPage = $this->actingAs($this->userByEmail('e2e.internal.super_admin@example.test'), 'web')
            ->get(route('admin.index'))
            ->assertOk();
        $this->assertHasNavigationLink($superAdminPage, 'internal.staff.index');

        $supportPage = $this->actingAs($this->userByEmail('e2e.internal.support@example.test'), 'web')
            ->get(route('internal.home'))
            ->assertOk();
        $this->assertHasNavigationLink($supportPage, 'internal.staff.index');

        foreach ([
            'e2e.internal.ops_readonly@example.test',
            'e2e.internal.carrier_manager@example.test',
        ] as $email) {
            $page = $this->actingAs($this->userByEmail($email), 'web')
                ->get(route('internal.home'))
                ->assertOk();

            $this->assertMissingNavigationLink($page, 'internal.staff.index');
        }
    }

    #[Test]
    public function ops_readonly_and_carrier_manager_are_forbidden_from_staff_routes(): void
    {
        $supportUser = $this->userByEmail('e2e.internal.support@example.test');

        foreach ([
            'e2e.internal.ops_readonly@example.test',
            'e2e.internal.carrier_manager@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->get(route('internal.staff.index'))
            );

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->get(route('internal.staff.show', $supportUser))
            );
        }
    }

    #[Test]
    public function external_users_are_forbidden_from_internal_staff_routes(): void
    {
        $externalUser = $this->userByEmail('e2e.c.organization_owner@example.test');
        $supportUser = $this->userByEmail('e2e.internal.support@example.test');

        $this->actingAs($externalUser, 'web')
            ->get(route('internal.staff.index'))
            ->assertForbidden()
            ->assertSeeText('هذه الصفحة مخصصة لفريق التشغيل الداخلي في المنصة');

        $this->actingAs($externalUser, 'web')
            ->get(route('internal.staff.show', $supportUser))
            ->assertForbidden()
            ->assertSeeText('هذه الصفحة مخصصة لفريق التشغيل الداخلي في المنصة');
    }

    #[Test]
    public function legacy_internal_role_names_are_hidden_from_visible_staff_flows(): void
    {
        $viewer = $this->userByEmail('e2e.internal.super_admin@example.test');
        $legacyUser = $this->createLegacyInternalUser('finance');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.staff.index', ['q' => $legacyUser->email]))
            ->assertOk()
            ->assertSeeText($legacyUser->name)
            ->assertSeeText('دور داخلي قديم مخفي من الواجهة النشطة')
            ->assertDontSeeText('finance')
            ->assertDontSeeText('integration_admin')
            ->assertDontSeeText('ops');

        $this->actingAs($viewer, 'web')
            ->get(route('internal.staff.show', $legacyUser))
            ->assertOk()
            ->assertSeeText($legacyUser->name)
            ->assertSeeText('دور داخلي قديم مخفي من الواجهة النشطة')
            ->assertSeeText('تم تعليق عرض نقطة الهبوط المتوقعة حتى يقتصر الحساب على دور داخلي معتمد واحد دون تعيينات قديمة.')
            ->assertSeeText('تم إخفاء ملخص الصلاحيات حتى تكتمل مواءمة هذا الحساب مع دور داخلي معتمد واحد دون تعيينات قديمة.')
            ->assertDontSee('data-testid="staff-canonical-role-key"', false)
            ->assertDontSeeText('finance')
            ->assertDontSeeText('integration_admin')
            ->assertDontSeeText('ops');
    }

    private function userByEmail(string $email): User
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('email', $email)
            ->firstOrFail();
    }

    private function createLegacyInternalUser(string $roleName): User
    {
        $user = User::factory()->create([
            'account_id' => null,
            'user_type' => 'internal',
            'status' => 'active',
            'password' => Hash::make('Password123!'),
            'name' => 'Legacy Hidden Staff',
            'email' => 'legacy.hidden.staff@example.test',
        ]);

        $roleId = (string) Str::uuid();
        DB::table('internal_roles')->insert([
            'id' => $roleId,
            'name' => $roleName,
            'display_name' => Str::headline(str_replace('_', ' ', $roleName)),
            'description' => 'Legacy internal role for hidden staff coverage.',
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permission = Permission::query()->updateOrCreate(
            ['key' => 'users.read'],
            $this->permissionPayload('users.read')
        );

        DB::table('internal_role_permission')->updateOrInsert(
            [
                'internal_role_id' => $roleId,
                'permission_id' => (string) $permission->id,
            ],
            [
                'granted_at' => now(),
            ]
        );

        DB::table('internal_user_role')->insert([
            'user_id' => (string) $user->id,
            'internal_role_id' => $roleId,
            'assigned_by' => null,
            'assigned_at' => now(),
        ]);

        return $user->fresh();
    }

    /**
     * @return array<string, string>
     */
    private function permissionPayload(string $permissionKey): array
    {
        $payload = [
            'group' => explode('.', $permissionKey)[0],
            'display_name' => $permissionKey,
            'description' => $permissionKey,
        ];

        if (Schema::hasColumn('permissions', 'audience')) {
            $payload['audience'] = 'internal';
        }

        return $payload;
    }

    private function assertHasNavigationLink(TestResponse $response, string $routeName): void
    {
        $response->assertSee('href="' . route($routeName) . '"', false);
    }

    private function assertMissingNavigationLink(TestResponse $response, string $routeName): void
    {
        $response->assertDontSee('href="' . route($routeName) . '"', false);
    }

    private function assertForbiddenInternalSurface(TestResponse $response): void
    {
        $response->assertForbidden()
            ->assertSeeText('هذه الصفحة ليست ضمن دورك الحالي');
    }
}
