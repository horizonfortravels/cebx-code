<?php

namespace Tests\Feature\Web;

use App\Models\Account;
use App\Models\Permission;
use App\Models\User;
use App\Support\Tenancy\WebTenantContext;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InternalPortalRoleAlignmentWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);
    }

    #[Test]
    public function seeded_internal_users_only_use_canonical_internal_roles(): void
    {
        $this->assertSame(['super_admin'], $this->userByEmail('e2e.internal.super_admin@example.test')->internalRoleNames());
        $this->assertSame(['support'], $this->userByEmail('e2e.internal.support@example.test')->internalRoleNames());
        $this->assertSame(['ops_readonly'], $this->userByEmail('e2e.internal.ops_readonly@example.test')->internalRoleNames());
        $this->assertSame(['carrier_manager'], $this->userByEmail('e2e.internal.carrier_manager@example.test')->internalRoleNames());

        foreach (['e2e_internal_support', 'e2e_internal_ops_readonly', 'finance', 'integration_admin', 'ops'] as $legacyRoleName) {
            $this->assertDatabaseMissing('internal_roles', ['name' => $legacyRoleName]);
        }
    }

    #[Test]
    public function super_admin_lands_on_admin_dashboard_and_sees_admin_navigation(): void
    {
        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => 'e2e.internal.super_admin@example.test',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('admin.index'));

        $page = $this->actingAs($this->userByEmail('e2e.internal.super_admin@example.test'), 'web')
            ->get(route('admin.index'))
            ->assertOk()
            ->assertSeeText('لوحة الإدارة');

        $this->assertHasNavigationLink($page, 'admin.index');
        $this->assertHasNavigationLink($page, 'admin.tenant-context');
        $this->assertHasNavigationLink($page, 'admin.users');
        $this->assertHasNavigationLink($page, 'admin.roles');
        $this->assertHasNavigationLink($page, 'admin.reports');
    }

    #[Test]
    public function support_and_ops_readonly_land_on_internal_home_without_admin_or_smtp_navigation(): void
    {
        foreach ([
            ['email' => 'e2e.internal.support@example.test', 'label' => 'الدعم'],
            ['email' => 'e2e.internal.ops_readonly@example.test', 'label' => 'التشغيل للقراءة فقط'],
        ] as $scenario) {
            $response = $this->from('/admin/login')->post('/admin/login', [
                'email' => $scenario['email'],
                'password' => 'Password123!',
            ]);

            $response->assertRedirect(route('internal.home'));

            $page = $this->actingAs($this->userByEmail($scenario['email']), 'web')
                ->get(route('internal.home'))
                ->assertOk()
                ->assertSeeText('المساحة الداخلية')
                ->assertSeeText($scenario['label']);

            $this->assertHasNavigationLink($page, 'internal.home');
            $this->assertMissingNavigationLink($page, 'admin.index');
            $this->assertMissingNavigationLink($page, 'admin.tenant-context');
            $this->assertMissingNavigationLink($page, 'admin.users');
            $this->assertMissingNavigationLink($page, 'admin.roles');
            $this->assertMissingNavigationLink($page, 'admin.reports');
            $this->assertMissingNavigationLink($page, 'internal.tenant-context');
            $this->assertMissingNavigationLink($page, 'internal.smtp-settings.edit');
        }
    }

    #[Test]
    public function carrier_manager_lands_on_internal_home_and_sees_smtp_navigation_only(): void
    {
        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => 'e2e.internal.carrier_manager@example.test',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('internal.home'));

        $page = $this->actingAs($this->userByEmail('e2e.internal.carrier_manager@example.test'), 'web')
            ->get(route('internal.home'))
            ->assertOk()
            ->assertSeeText('إدارة الناقلين')
            ->assertSeeText('إعدادات SMTP');

        $this->assertHasNavigationLink($page, 'internal.home');
        $this->assertHasNavigationLink($page, 'internal.smtp-settings.edit');
        $this->assertMissingNavigationLink($page, 'admin.index');
        $this->assertMissingNavigationLink($page, 'admin.tenant-context');
        $this->assertMissingNavigationLink($page, 'admin.users');
        $this->assertMissingNavigationLink($page, 'admin.roles');
        $this->assertMissingNavigationLink($page, 'admin.reports');
        $this->assertMissingNavigationLink($page, 'internal.tenant-context');
    }

    #[Test]
    public function direct_url_access_is_enforced_for_canonical_internal_roles(): void
    {
        $account = Account::factory()->create(['type' => 'organization']);
        $tenantSession = $this->tenantContextSession($account);
        $staffTarget = $this->userByEmail('e2e.internal.support@example.test');

        $superAdmin = $this->userByEmail('e2e.internal.super_admin@example.test');
        $this->actingAs($superAdmin, 'web')
            ->get(route('admin.index'))
            ->assertOk();
        $this->actingAs($superAdmin, 'web')
            ->get(route('admin.tenant-context'))
            ->assertOk();
        $this->actingAs($superAdmin, 'web')
            ->withSession($tenantSession)
            ->get(route('admin.users'))
            ->assertOk();
        $this->actingAs($superAdmin, 'web')
            ->withSession($tenantSession)
            ->get(route('admin.roles'))
            ->assertOk();
        $this->actingAs($superAdmin, 'web')
            ->withSession($tenantSession)
            ->get(route('admin.reports'))
            ->assertOk();
        $this->actingAs($superAdmin, 'web')
            ->get(route('internal.tenant-context'))
            ->assertOk();
        $this->actingAs($superAdmin, 'web')
            ->get(route('internal.smtp-settings.edit'))
            ->assertOk();
        $this->actingAs($superAdmin, 'web')
            ->get(route('internal.staff.index'))
            ->assertOk();
        $this->actingAs($superAdmin, 'web')
            ->get(route('internal.staff.show', $staffTarget))
            ->assertOk();

        $support = $this->userByEmail('e2e.internal.support@example.test');
        $this->assertForbiddenInternalSurface(
            $this->actingAs($support, 'web')->get(route('admin.index'))
        );
        $this->assertForbiddenInternalSurface(
            $this->actingAs($support, 'web')->get(route('admin.tenant-context'))
        );
        $this->assertForbiddenInternalSurface(
            $this->actingAs($support, 'web')->withSession($tenantSession)->get(route('admin.users'))
        );
        $this->assertForbiddenInternalSurface(
            $this->actingAs($support, 'web')->withSession($tenantSession)->get(route('admin.roles'))
        );
        $this->assertForbiddenInternalSurface(
            $this->actingAs($support, 'web')->withSession($tenantSession)->get(route('admin.reports'))
        );
        $this->assertForbiddenInternalSurface(
            $this->actingAs($support, 'web')->get(route('internal.tenant-context'))
        );
        $this->assertForbiddenInternalSurface(
            $this->actingAs($support, 'web')->get(route('internal.smtp-settings.edit'))
        );
        $this->actingAs($support, 'web')
            ->get(route('internal.staff.index'))
            ->assertOk();
        $this->actingAs($support, 'web')
            ->get(route('internal.staff.show', $staffTarget))
            ->assertOk();

        foreach ([
            'e2e.internal.ops_readonly@example.test',
        ] as $email) {
            $user = $this->userByEmail($email);

            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->get(route('admin.index'))
            );
            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->get(route('admin.tenant-context'))
            );
            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->withSession($tenantSession)->get(route('admin.users'))
            );
            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->withSession($tenantSession)->get(route('admin.roles'))
            );
            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->withSession($tenantSession)->get(route('admin.reports'))
            );
            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->get(route('internal.tenant-context'))
            );
            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->get(route('internal.smtp-settings.edit'))
            );
            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->get(route('internal.staff.index'))
            );
            $this->assertForbiddenInternalSurface(
                $this->actingAs($user, 'web')->get(route('internal.staff.show', $staffTarget))
            );
        }

        $carrierManager = $this->userByEmail('e2e.internal.carrier_manager@example.test');
        $this->assertForbiddenInternalSurface(
            $this->actingAs($carrierManager, 'web')->get(route('admin.index'))
        );
        $this->assertForbiddenInternalSurface(
            $this->actingAs($carrierManager, 'web')->get(route('admin.tenant-context'))
        );
        $this->assertForbiddenInternalSurface(
            $this->actingAs($carrierManager, 'web')->withSession($tenantSession)->get(route('admin.users'))
        );
        $this->assertForbiddenInternalSurface(
            $this->actingAs($carrierManager, 'web')->withSession($tenantSession)->get(route('admin.roles'))
        );
        $this->assertForbiddenInternalSurface(
            $this->actingAs($carrierManager, 'web')->withSession($tenantSession)->get(route('admin.reports'))
        );
        $this->assertForbiddenInternalSurface(
            $this->actingAs($carrierManager, 'web')->get(route('internal.tenant-context'))
        );
        $this->actingAs($carrierManager, 'web')
            ->get(route('internal.smtp-settings.edit'))
            ->assertOk();
        $this->assertForbiddenInternalSurface(
            $this->actingAs($carrierManager, 'web')->get(route('internal.staff.index'))
        );
        $this->assertForbiddenInternalSurface(
            $this->actingAs($carrierManager, 'web')->get(route('internal.staff.show', $staffTarget))
        );
    }

    #[Test]
    public function legacy_internal_role_names_are_hidden_from_active_internal_portal_flows(): void
    {
        $legacyUser = $this->createLegacyInternalUser('finance', [
            'admin.access',
            'tenancy.context.select',
            'notifications.channels.manage',
            'users.read',
            'roles.read',
            'reports.read',
        ]);
        $account = Account::factory()->create(['type' => 'organization']);
        $tenantSession = $this->tenantContextSession($account);
        $staffTarget = $this->userByEmail('e2e.internal.support@example.test');

        $response = $this->from('/admin/login')->post('/admin/login', [
            'email' => $legacyUser->email,
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('internal.home'));

        $page = $this->actingAs($legacyUser, 'web')
            ->get(route('internal.home'))
            ->assertOk()
            ->assertSeeText('تم إخفاء الدور الداخلي القديم من الواجهة النشطة')
            ->assertDontSeeText('finance');

        $this->assertHasNavigationLink($page, 'internal.home');
        $this->assertMissingNavigationLink($page, 'admin.index');
        $this->assertMissingNavigationLink($page, 'admin.tenant-context');
        $this->assertMissingNavigationLink($page, 'admin.users');
        $this->assertMissingNavigationLink($page, 'admin.roles');
        $this->assertMissingNavigationLink($page, 'admin.reports');
        $this->assertMissingNavigationLink($page, 'internal.tenant-context');
        $this->assertMissingNavigationLink($page, 'internal.smtp-settings.edit');

        $this->assertForbiddenInternalSurface(
            $this->actingAs($legacyUser, 'web')->get(route('admin.index'))
        );
        $this->assertForbiddenInternalSurface(
            $this->actingAs($legacyUser, 'web')->get(route('admin.tenant-context'))
        );
        $this->assertForbiddenInternalSurface(
            $this->actingAs($legacyUser, 'web')->withSession($tenantSession)->get(route('admin.users'))
        );
        $this->assertForbiddenInternalSurface(
            $this->actingAs($legacyUser, 'web')->withSession($tenantSession)->get(route('admin.roles'))
        );
        $this->assertForbiddenInternalSurface(
            $this->actingAs($legacyUser, 'web')->withSession($tenantSession)->get(route('admin.reports'))
        );
        $this->assertForbiddenInternalSurface(
            $this->actingAs($legacyUser, 'web')->get(route('internal.tenant-context'))
        );
        $this->assertForbiddenInternalSurface(
            $this->actingAs($legacyUser, 'web')->get(route('internal.smtp-settings.edit'))
        );
        $this->assertForbiddenInternalSurface(
            $this->actingAs($legacyUser, 'web')->get(route('internal.staff.index'))
        );
        $this->assertForbiddenInternalSurface(
            $this->actingAs($legacyUser, 'web')->get(route('internal.staff.show', $staffTarget))
        );
    }

    private function userByEmail(string $email): User
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('email', $email)
            ->firstOrFail();
    }

    /**
     * @param array<int, string> $permissionKeys
     */
    private function createLegacyInternalUser(string $roleName, array $permissionKeys): User
    {
        $user = User::factory()->create([
            'account_id' => null,
            'user_type' => 'internal',
            'status' => 'active',
            'password' => Hash::make('Password123!'),
            'email' => Str::lower($roleName) . '.' . Str::lower(Str::random(6)) . '@example.test',
        ]);

        $roleId = (string) Str::uuid();
        DB::table('internal_roles')->insert([
            'id' => $roleId,
            'name' => $roleName,
            'display_name' => Str::headline(str_replace('_', ' ', $roleName)),
            'description' => 'Legacy internal role for portal alignment coverage.',
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($permissionKeys as $permissionKey) {
            $permission = Permission::query()->updateOrCreate(
                ['key' => $permissionKey],
                $this->permissionPayload($permissionKey)
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
        }

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

    /**
     * @return array<string, string>
     */
    private function tenantContextSession(Account $account): array
    {
        return [
            WebTenantContext::sessionKey() => (string) $account->id,
        ];
    }
}
