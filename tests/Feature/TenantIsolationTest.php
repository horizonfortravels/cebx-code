<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private AccountService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AccountService();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function tenant_a_cannot_see_tenant_b_account_data(): void
    {
        // Create two separate accounts
        $tenantA = $this->service->createAccount([
            'account_name' => 'Tenant A',
            'name'         => 'Owner A',
            'email'        => 'a@tenant.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $tenantB = $this->service->createAccount([
            'account_name' => 'Tenant B',
            'name'         => 'Owner B',
            'email'        => 'b@tenant.com',
            'password'     => 'Str0ng!Pass',
        ]);

        // Authenticate as Tenant A
        Sanctum::actingAs($tenantA['user']);
        app()->instance('current_account_id', $tenantA['account']->id);

        // Tenant A should only see their own users
        $users = User::all();
        $this->assertCount(1, $users);
        $this->assertEquals($tenantA['user']->id, $users->first()->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function tenant_a_cannot_access_tenant_b_via_api(): void
    {
        $tenantA = $this->service->createAccount([
            'account_name' => 'Tenant A',
            'name'         => 'Owner A',
            'email'        => 'api-a@tenant.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $tenantB = $this->service->createAccount([
            'account_name' => 'Tenant B',
            'name'         => 'Owner B',
            'email'        => 'api-b@tenant.com',
            'password'     => 'Str0ng!Pass',
        ]);

        // Act as Tenant A, try to see their own account
        Sanctum::actingAs($tenantA['user']);

        $response = $this->getJson('/api/v1/account');
        $response->assertOk()
                 ->assertJsonPath('data.id', $tenantA['account']->id);

        // The response should NOT contain any data from Tenant B
        $response->assertJsonMissing(['name' => 'Tenant B']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function audit_logs_are_tenant_scoped(): void
    {
        $tenantA = $this->service->createAccount([
            'account_name' => 'Audit Tenant A',
            'name'         => 'Owner A',
            'email'        => 'audit-a@tenant.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $tenantB = $this->service->createAccount([
            'account_name' => 'Audit Tenant B',
            'name'         => 'Owner B',
            'email'        => 'audit-b@tenant.com',
            'password'     => 'Str0ng!Pass',
        ]);

        // Set context to Tenant A
        app()->instance('current_account_id', $tenantA['account']->id);

        $logs = \App\Models\AuditLog::all();
        $this->assertTrue($logs->every(fn ($log) => $log->account_id === $tenantA['account']->id));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function each_account_gets_unique_uuid(): void
    {
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $result = $this->service->createAccount([
                'account_name' => "Account $i",
                'name'         => "User $i",
                'email'        => "user{$i}@test.com",
                'password'     => 'Str0ng!Pass',
            ]);
            $ids[] = $result['account']->id;
        }

        // All IDs should be unique
        $this->assertCount(5, array_unique($ids));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function unauthenticated_user_cannot_access_account_endpoint(): void
    {
        $response = $this->getJson('/api/v1/account');
        $response->assertStatus(401);
    }
}
