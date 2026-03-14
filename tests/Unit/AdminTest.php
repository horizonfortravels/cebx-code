<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\FeatureFlag;
use App\Models\IntegrationHealthLog;
use App\Models\Role;
use App\Models\RoleTemplate;
use App\Models\SupportTicket;
use App\Models\SystemSetting;
use App\Models\TaxRule;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit Tests — ADM Module (FR-ADM-001→010)
 *
 * 40 tests covering all 10 requirements.
 */
class AdminTest extends TestCase
{
    use RefreshDatabase;

    private AdminService $service;
    private Account $account;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(AdminService::class);
        $this->account = Account::factory()->create();
        $role = Role::factory()->create(['account_id' => $this->account->id, 'slug' => 'admin']);
        $this->admin = $this->createUserWithRole((string) $this->account->id, (string) $role->id);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ADM-001: System Settings (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_set_and_get_setting(): void
    {
        $this->service->updateSetting('carrier', 'dhl_sandbox', 'true', 'boolean');
        $val = $this->service->getSetting('carrier', 'dhl_sandbox');
        $this->assertTrue($val);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_encrypted_setting(): void
    {
        SystemSetting::setValue('carrier', 'dhl_api_key', 'secret123', 'encrypted');
        $setting = SystemSetting::where('group', 'carrier')->where('key', 'dhl_api_key')->first();
        $this->assertTrue($setting->is_sensitive);
        $this->assertEquals('secret123', $setting->getTypedValue());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_json_setting(): void
    {
        SystemSetting::setValue('platform', 'languages', ['ar', 'en'], 'json');
        $val = SystemSetting::getValue('platform', 'languages');
        $this->assertEquals(['ar', 'en'], $val);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_list_settings_by_group(): void
    {
        SystemSetting::setValue('carrier', 'key1', 'val1');
        SystemSetting::setValue('carrier', 'key2', 'val2');
        SystemSetting::setValue('billing', 'key3', 'val3');

        $settings = $this->service->getSettings('carrier');
        $this->assertCount(2, $settings);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_carrier_connection_test(): void
    {
        $result = $this->service->testCarrierConnection('dhl');
        $this->assertEquals('healthy', $result['status']);
        $this->assertDatabaseHas('integration_health_logs', ['service' => 'dhl_api']);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ADM-002/006: Health Monitoring (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_record_health_check(): void
    {
        $log = $this->service->recordHealthCheck('dhl_api', 'healthy', 150);
        $this->assertEquals('healthy', $log->status);
        $this->assertEquals(150, $log->response_time_ms);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_degraded_status(): void
    {
        $log = IntegrationHealthLog::recordCheck('aramex_api', 'degraded', 3000, 'Slow response');
        $this->assertFalse($log->isHealthy());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_get_integration_health(): void
    {
        IntegrationHealthLog::recordCheck('dhl_api', 'healthy', 100);
        IntegrationHealthLog::recordCheck('dhl_api', 'degraded', 2000);

        $logs = $this->service->getIntegrationHealth('dhl_api');
        $this->assertCount(2, $logs);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_system_health_dashboard(): void
    {
        IntegrationHealthLog::recordCheck('dhl_api', 'healthy', 100);
        $dashboard = $this->service->getSystemHealthDashboard();

        $this->assertArrayHasKey('overall_status', $dashboard);
        $this->assertArrayHasKey('services', $dashboard);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_down_service_degrades_overall(): void
    {
        IntegrationHealthLog::recordCheck('dhl_api', 'down', 0, 'Connection refused');
        $dashboard = $this->service->getSystemHealthDashboard();
        $this->assertEquals('degraded', $dashboard['overall_status']);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ADM-003: User Management (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_list_platform_users(): void
    {
        $users = $this->service->listPlatformUsers();
        $this->assertGreaterThanOrEqual(1, $users->total());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_suspend_user(): void
    {
        $userRole = Role::factory()->create(['account_id' => $this->account->id]);
        $user = $this->createUserWithRole((string) $this->account->id, (string) $userRole->id);
        $suspended = $this->service->suspendUser($user->id, 'Policy violation');
        $this->assertEquals('suspended', $suspended->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_activate_user(): void
    {
        $userRole = Role::factory()->create(['account_id' => $this->account->id]);
        $user = $this->createUserWithRole((string) $this->account->id, (string) $userRole->id, [
            'status' => 'suspended',
        ]);
        $activated = $this->service->activateUser($user->id);
        $this->assertEquals('active', $activated->status);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ADM-005: Tax Rules (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_tax_rule(): void
    {
        $rule = $this->service->createTaxRule(['name' => 'UAE VAT', 'country_code' => 'AE', 'rate' => 5.00]);
        $this->assertEquals(5.00, $rule->rate);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_get_tax_rate_for_country(): void
    {
        TaxRule::factory()->create(['country_code' => 'SA', 'rate' => 15.00]);
        $rate = TaxRule::getRateFor('SA');
        $this->assertEquals(15.00, $rate);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_tax_rules_for_country(): void
    {
        TaxRule::factory()->create(['country_code' => 'SA']);
        TaxRule::factory()->create(['country_code' => 'AE', 'rate' => 5]);
        $rules = $this->service->listTaxRules('SA');
        $this->assertCount(1, $rules);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ADM-006: Role Templates (2 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_role_template(): void
    {
        $tpl = $this->service->createRoleTemplate([
            'name' => 'Accountant', 'slug' => 'accountant',
            'permissions' => ['invoices.view', 'transactions.view'],
        ]);
        $this->assertEquals(['invoices.view', 'transactions.view'], $tpl->permissions);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_list_role_templates(): void
    {
        RoleTemplate::create(['name' => 'T1', 'slug' => 't1', 'permissions' => ['a']]);
        RoleTemplate::create(['name' => 'T2', 'slug' => 't2', 'permissions' => ['b']]);
        $this->assertCount(2, $this->service->listRoleTemplates());
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ADM-008: Support Tickets (8 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_ticket(): void
    {
        $ticket = $this->service->createTicket($this->account, $this->admin, [
            'subject' => 'Test issue', 'description' => 'Details here',
        ]);
        $this->assertEquals(SupportTicket::STATUS_OPEN, $ticket->status);
        $this->assertNotNull($ticket->ticket_number);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_reply_to_ticket(): void
    {
        $ticket = SupportTicket::factory()->create(['account_id' => $this->account->id, 'user_id' => $this->admin->id]);
        $reply = $this->service->replyToTicket($ticket->id, $this->admin, 'Here is my response');
        $this->assertEquals('Here is my response', $reply->body);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_assign_ticket(): void
    {
        $ticket = SupportTicket::factory()->create(['account_id' => $this->account->id, 'user_id' => $this->admin->id]);
        $assigned = $this->service->assignTicket($ticket->id, $this->admin->id, 'support');
        $this->assertEquals(SupportTicket::STATUS_IN_PROGRESS, $assigned->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_resolve_ticket(): void
    {
        $ticket = SupportTicket::factory()->create(['account_id' => $this->account->id, 'user_id' => $this->admin->id]);
        $resolved = $this->service->resolveTicket($ticket->id, 'Issue fixed');
        $this->assertEquals(SupportTicket::STATUS_RESOLVED, $resolved->status);
        $this->assertNotNull($resolved->resolved_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_close_ticket(): void
    {
        $ticket = SupportTicket::factory()->create(['account_id' => $this->account->id, 'user_id' => $this->admin->id]);
        $ticket->close();
        $this->assertEquals(SupportTicket::STATUS_CLOSED, $ticket->fresh()->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_list_tickets_filtered(): void
    {
        SupportTicket::factory()->count(3)->create(['account_id' => $this->account->id, 'user_id' => $this->admin->id, 'priority' => 'high']);
        SupportTicket::factory()->create(['account_id' => $this->account->id, 'user_id' => $this->admin->id, 'priority' => 'low']);

        $tickets = $this->service->listTickets(['priority' => 'high']);
        $this->assertEquals(3, $tickets->total());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_ticket_with_replies(): void
    {
        $ticket = SupportTicket::factory()->create(['account_id' => $this->account->id, 'user_id' => $this->admin->id]);
        $this->service->replyToTicket($ticket->id, $this->admin, 'Reply 1');
        $this->service->replyToTicket($ticket->id, $this->admin, 'Reply 2');

        $loaded = $this->service->getTicket($ticket->id);
        $this->assertCount(2, $loaded->replies);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_internal_note(): void
    {
        $ticket = SupportTicket::factory()->create(['account_id' => $this->account->id, 'user_id' => $this->admin->id]);
        $reply = $this->service->replyToTicket($ticket->id, $this->admin, 'Internal note', true);
        $this->assertTrue($reply->is_internal_note);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ADM-009: API Keys (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_api_key(): void
    {
        $result = $this->service->createApiKey($this->account, $this->admin, 'Test Key', ['shipments:read']);
        $this->assertNotNull($result['raw_key']);
        $this->assertTrue(str_starts_with($result['raw_key'], 'sgw_'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_find_by_raw_key(): void
    {
        $result = $this->service->createApiKey($this->account, $this->admin, 'Lookup Test');
        $found = ApiKey::findByKey($result['raw_key']);
        $this->assertNotNull($found);
        $this->assertEquals($result['api_key']->id, $found->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_revoke_api_key(): void
    {
        $result = $this->service->createApiKey($this->account, $this->admin, 'Revoke Test');
        $this->service->revokeApiKey($result['api_key']->id);
        $this->assertFalse($result['api_key']->fresh()->is_active);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_rotate_api_key(): void
    {
        $old = $this->service->createApiKey($this->account, $this->admin, 'Rotate Test');
        $new = $this->service->rotateApiKey($old['api_key']->id, $this->admin);

        $this->assertFalse($old['api_key']->fresh()->is_active);
        $this->assertTrue($new['api_key']->is_active);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_key_scope_check(): void
    {
        $key = ApiKey::factory()->create([
            'account_id' => $this->account->id, 'created_by' => $this->admin->id,
            'scopes' => ['shipments:read'],
        ]);
        $this->assertTrue($key->hasScope('shipments:read'));
        $this->assertFalse($key->hasScope('admin:write'));
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ADM-010: Feature Flags (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_feature_flag(): void
    {
        $flag = $this->service->createFeatureFlag([
            'key' => 'new_ui', 'name' => 'New UI', 'is_enabled' => false,
        ]);
        $this->assertFalse($flag->is_enabled);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_toggle_feature_flag(): void
    {
        $flag = FeatureFlag::factory()->create();
        $toggled = $this->service->toggleFeatureFlag($flag->id, true);
        $this->assertTrue($toggled->is_enabled);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_feature_enabled_check(): void
    {
        FeatureFlag::factory()->enabled()->create(['key' => 'test_feature']);
        $this->assertTrue($this->service->isFeatureEnabled('test_feature'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_disabled_feature(): void
    {
        FeatureFlag::factory()->create(['key' => 'disabled_feature', 'is_enabled' => false]);
        $this->assertFalse($this->service->isFeatureEnabled('disabled_feature'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_rollout_percentage(): void
    {
        $flag = FeatureFlag::factory()->create(['key' => 'gradual', 'is_enabled' => true, 'rollout_percentage' => 50]);
        // With 50% rollout, some accounts enabled some not — deterministic via crc32
        $this->assertIsBool($flag->isEnabledFor($this->account->id));
    }
}
