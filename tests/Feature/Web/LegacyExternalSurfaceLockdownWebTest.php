<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Database\Seeders\E2EUserMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegacyExternalSurfaceLockdownWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(E2EUserMatrixSeeder::class);
    }

    #[Test]
    public function organization_user_is_redirected_from_legacy_root_routes_to_modern_b2b_surfaces(): void
    {
        $user = $this->userByEmail('e2e.c.organization_owner@example.test');

        $cases = [
            '/' => '/b2b/dashboard',
            '/shipments' => '/b2b/shipments',
            '/shipments/export' => '/b2b/shipments/export',
            '/wallet' => '/b2b/wallet',
            '/users' => '/b2b/users',
            '/roles' => '/b2b/roles',
            '/invitations' => '/b2b/invitations',
            '/orders' => '/b2b/orders',
            '/stores' => '/b2b/stores',
            '/reports' => '/b2b/reports',
            '/addresses' => '/b2b/addresses',
            '/settings' => '/b2b/settings',
        ];

        foreach ($cases as $path => $expectedLocation) {
            $response = $this->actingAs($user, 'web')->get($path);

            $response->assertRedirect($expectedLocation);
        }
    }

    #[Test]
    public function individual_user_is_redirected_from_shared_legacy_root_routes_to_modern_b2c_surfaces(): void
    {
        $user = $this->userByEmail('e2e.a.individual@example.test');

        $cases = [
            '/' => '/b2c/dashboard',
            '/shipments' => '/b2c/shipments',
            '/shipments/export' => '/b2c/shipments/export',
            '/wallet' => '/b2c/wallet',
            '/addresses' => '/b2c/addresses',
            '/settings' => '/b2c/settings',
            '/tracking' => '/b2c/tracking',
        ];

        foreach ($cases as $path => $expectedLocation) {
            $response = $this->actingAs($user, 'web')->get($path);

            $response->assertRedirect($expectedLocation);
        }
    }

    #[Test]
    public function individual_user_cannot_open_organization_only_legacy_routes(): void
    {
        $user = $this->userByEmail('e2e.a.individual@example.test');

        foreach ([
            '/users',
            '/roles',
            '/invitations',
            '/orders',
            '/stores',
            '/reports',
        ] as $path) {
            $response = $this->actingAs($user, 'web')->get($path);

            $response->assertNotFound();
            $response->assertDontSeeText('RuntimeException');
            $response->assertDontSeeText('Whoops');
            $response->assertDontSeeText('Stack trace');
            $response->assertDontSeeText('Internal Server Error');
        }
    }

    #[Test]
    public function dangerous_legacy_management_routes_are_not_reachable_by_external_users(): void
    {
        $user = $this->userByEmail('e2e.c.organization_owner@example.test');

        foreach ([
            '/audit',
            '/audit/export',
            '/tracking',
            '/pricing',
            '/organizations',
            '/financial',
            '/risk',
            '/dg',
            '/containers',
            '/customs',
            '/drivers',
            '/claims',
            '/vessels',
            '/schedules',
            '/branches',
            '/companies',
            '/hscodes',
            '/reports/export/shipments',
        ] as $path) {
            $response = $this->actingAs($user, 'web')->get($path);

            $response->assertNotFound();
            $response->assertDontSeeText('RuntimeException');
            $response->assertDontSeeText('Whoops');
            $response->assertDontSeeText('Stack trace');
            $response->assertDontSeeText('Internal Server Error');
        }
    }

    #[Test]
    public function legacy_management_mutation_routes_are_not_reachable_by_external_users(): void
    {
        $user = $this->userByEmail('e2e.c.organization_owner@example.test');

        $cases = [
            ['POST', '/users'],
            ['POST', '/roles'],
            ['POST', '/invitations'],
            ['POST', '/pricing'],
            ['POST', '/orders'],
            ['POST', '/stores'],
            ['POST', '/wallet/topup'],
            ['POST', '/wallet/hold'],
            ['PATCH', '/users/not-a-real-user/toggle'],
            ['DELETE', '/stores/not-a-real-store'],
        ];

        foreach ($cases as [$method, $path]) {
            $response = $this->actingAs($user, 'web')->call($method, $path);

            $response->assertNotFound();
        }
    }

    #[Test]
    public function legacy_notifications_center_remains_available_for_external_users(): void
    {
        $user = $this->userByEmail('e2e.c.organization_owner@example.test');

        $response = $this->actingAs($user, 'web')->get('/notifications');

        $response->assertOk();
        $response->assertDontSeeText('Internal Server Error');
        $response->assertDontSeeText('Whoops');
    }

    #[Test]
    public function legacy_support_center_remains_available_for_external_users(): void
    {
        foreach ([
            'e2e.a.individual@example.test',
            'e2e.c.organization_owner@example.test',
        ] as $email) {
            $response = $this->actingAs($this->userByEmail($email), 'web')->get('/support');

            $response->assertOk();
            $response->assertDontSeeText('Internal Server Error');
            $response->assertDontSeeText('Whoops');
        }
    }

    private function userByEmail(string $email): User
    {
        return User::query()
            ->withoutGlobalScopes()
            ->where('email', $email)
            ->firstOrFail();
    }
}
