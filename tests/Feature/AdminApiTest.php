<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\FeatureFlag;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\TaxRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API Tests — ADM Module (FR-ADM-001→010)
 *
 * 20 tests covering all admin API endpoints.
 */
class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = Account::factory()->create();
        $role = Role::factory()->create(['account_id' => $this->account->id, 'slug' => 'admin']);
        $this->admin = $this->createUserWithRole((string) $this->account->id, (string) $role->id);
    }

    // ═══════════════ FR-ADM-001: Settings ════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_update_setting(): void
    {
        $response = $this->actingAs($this->admin)->putJson('/api/v1/admin/settings', [
            'group' => 'carrier', 'key' => 'dhl_mode', 'value' => 'sandbox',
        ]);
        $response->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_get_settings(): void
    {
        $this->actingAs($this->admin)->putJson('/api/v1/admin/settings', [
            'group' => 'platform', 'key' => 'currency', 'value' => 'SAR',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/settings/platform');
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_test_carrier(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/test-carrier', ['carrier' => 'dhl']);
        $response->assertOk()->assertJsonPath('data.status', 'healthy');
    }

    // ═══════════════ FR-ADM-002/006: Health ══════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_system_health(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/system-health');
        $response->assertOk()->assertJsonStructure(['data' => ['overall_status', 'services']]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_integration_health(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/integration-health');
        $response->assertOk();
    }

    // ═══════════════ FR-ADM-003: Users ═══════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_list_users(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/users');
        $response->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_suspend_user(): void
    {
        $userRole = Role::factory()->create(['account_id' => $this->account->id]);
        $user = $this->createUserWithRole((string) $this->account->id, (string) $userRole->id);
        $response = $this->actingAs($this->admin)->postJson("/api/v1/admin/users/{$user->id}/suspend", ['reason' => 'TOS violation']);
        $response->assertOk();
    }

    // ═══════════════ FR-ADM-005: Tax Rules ═══════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_create_tax_rule(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/tax-rules', [
            'name' => 'Saudi VAT', 'country_code' => 'SA', 'rate' => 15,
        ]);
        $response->assertStatus(201);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_list_tax_rules(): void
    {
        TaxRule::factory()->create();
        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/tax-rules');
        $response->assertOk();
    }

    // ═══════════════ FR-ADM-006: Role Templates ══════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_create_role_template(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/role-templates', [
            'name' => 'Warehouse', 'slug' => 'warehouse',
            'permissions' => ['shipments.view', 'shipments.create'],
        ]);
        $response->assertStatus(201);
    }

    // ═══════════════ FR-ADM-008: Support Tickets ═════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_create_ticket(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/support/tickets', [
            'subject' => 'Cannot create shipment', 'description' => 'Error on page',
        ]);
        $response->assertStatus(201)->assertJsonPath('data.status', 'open');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_list_tickets(): void
    {
        SupportTicket::factory()->count(3)->create(['account_id' => $this->account->id, 'user_id' => $this->admin->id]);
        $response = $this->actingAs($this->admin)->getJson('/api/v1/support/tickets');
        $response->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_reply_to_ticket(): void
    {
        $ticket = SupportTicket::factory()->create(['account_id' => $this->account->id, 'user_id' => $this->admin->id]);
        $response = $this->actingAs($this->admin)->postJson("/api/v1/support/tickets/{$ticket->id}/reply", [
            'body' => 'Thank you for reporting',
        ]);
        $response->assertStatus(201);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_resolve_ticket(): void
    {
        $ticket = SupportTicket::factory()->create(['account_id' => $this->account->id, 'user_id' => $this->admin->id]);
        $response = $this->actingAs($this->admin)->postJson("/api/v1/support/tickets/{$ticket->id}/resolve", [
            'notes' => 'Issue resolved',
        ]);
        $response->assertOk()->assertJsonPath('data.status', 'resolved');
    }

    // ═══════════════ FR-ADM-009: API Keys ════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_create_api_key(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/api-keys', [
            'name' => 'My API Key', 'scopes' => ['shipments:read'],
        ]);
        $response->assertStatus(201)->assertJsonStructure(['data' => ['raw_key', 'warning']]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_list_api_keys(): void
    {
        ApiKey::factory()->count(2)->create(['account_id' => $this->account->id, 'created_by' => $this->admin->id]);
        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/api-keys');
        $response->assertOk()->assertJsonCount(2, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_revoke_api_key(): void
    {
        $key = ApiKey::factory()->create(['account_id' => $this->account->id, 'created_by' => $this->admin->id]);
        $response = $this->actingAs($this->admin)->deleteJson("/api/v1/admin/api-keys/{$key->id}");
        $response->assertOk();
    }

    // ═══════════════ FR-ADM-010: Feature Flags ═══════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_create_feature_flag(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/feature-flags', [
            'key' => 'new_dashboard', 'name' => 'New Dashboard',
        ]);
        $response->assertStatus(201);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_toggle_feature_flag(): void
    {
        $flag = FeatureFlag::factory()->create();
        $response = $this->actingAs($this->admin)->putJson("/api/v1/admin/feature-flags/{$flag->id}/toggle", [
            'is_enabled' => true,
        ]);
        $response->assertOk()->assertJsonPath('data.is_enabled', true);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_check_feature_flag(): void
    {
        FeatureFlag::factory()->enabled()->create(['key' => 'check_me']);
        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/feature-flags/check_me/check');
        $response->assertOk()->assertJsonPath('data.enabled', true);
    }
}
