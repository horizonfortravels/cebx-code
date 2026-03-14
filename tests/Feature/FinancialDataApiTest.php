<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Account;
use App\Models\User;
use App\Models\Role;
use App\Models\AuditLog;
use Tests\Concerns\InteractsWithStrictRbac;

/**
 * FR-IAM-012: Financial Data Masking — Integration Tests (18 tests)
 * Tests API endpoints, permission-based access, and masking behavior via HTTP.
 */
class FinancialDataApiTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithStrictRbac;

    protected Account $account;
    protected User $owner;
    protected User $printer;
    protected User $accountant;
    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create();

        $this->owner = User::factory()->create([
            'account_id' => $this->account->id,
            'is_owner'   => true,
        ]);

        $printerRole = $this->createTenantRoleWithPermissions(
            (string) $this->account->id,
            ['shipments.read', 'shipments.print_label'],
            'printer_role'
        );
        $this->printer = $this->createUserWithRole((string) $this->account->id, (string) $printerRole->id, [
            'is_owner' => false,
        ]);

        $accountantRole = $this->createTenantRoleWithPermissions(
            (string) $this->account->id,
            [
                'financial.view',
                'financial.profit.view',
                'financial.cards.view',
                'financial.invoices.view',
            ],
            'accountant_role'
        );
        $this->accountant = $this->createUserWithRole((string) $this->account->id, (string) $accountantRole->id, [
            'is_owner' => false,
        ]);

        $viewerRole = $this->createTenantRoleWithPermissions(
            (string) $this->account->id,
            ['financial.view', 'financial.invoices.view'],
            'viewer_role'
        );
        $this->viewer = $this->createUserWithRole((string) $this->account->id, (string) $viewerRole->id, [
            'is_owner' => false,
        ]);
    }

    // ─── Visibility Map Endpoint ─────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_visibility_shows_all_true()
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/financial/visibility');

        $response->assertOk()
            ->assertJsonPath('data.financial_general', true)
            ->assertJsonPath('data.financial_profit', true)
            ->assertJsonPath('data.financial_cards', true);

        $this->assertEmpty($response->json('data.masked_fields'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function printer_visibility_shows_all_false()
    {
        $response = $this->actingAs($this->printer)
            ->getJson('/api/v1/financial/visibility');

        $response->assertOk()
            ->assertJsonPath('data.financial_general', false)
            ->assertJsonPath('data.financial_profit', false)
            ->assertJsonPath('data.financial_cards', false);

        $this->assertNotEmpty($response->json('data.masked_fields'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function viewer_visibility_shows_partial()
    {
        $response = $this->actingAs($this->viewer)
            ->getJson('/api/v1/financial/visibility');

        $response->assertOk()
            ->assertJsonPath('data.financial_general', true)
            ->assertJsonPath('data.financial_profit', false)
            ->assertJsonPath('data.financial_cards', false);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function accountant_visibility_shows_all_true()
    {
        $response = $this->actingAs($this->accountant)
            ->getJson('/api/v1/financial/visibility');

        $response->assertOk()
            ->assertJsonPath('data.financial_general', true)
            ->assertJsonPath('data.financial_profit', true)
            ->assertJsonPath('data.financial_cards', true);
    }

    // ─── Mask Card Endpoint ──────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function mask_card_returns_masked_number()
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/financial/mask-card', [
                'card_number' => '4111111111111234',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.last4', '1234');

        $masked = $response->json('data.masked');
        $this->assertStringEndsWith('1234', $masked);
        $this->assertStringContainsString('•', $masked);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function mask_card_logs_audit_entry()
    {
        $this->actingAs($this->owner)
            ->postJson('/api/v1/financial/mask-card', [
                'card_number' => '4111111111111234',
            ]);

        $log = AuditLog::withoutGlobalScopes()
            ->where('action', 'financial.card_masked')
            ->where('account_id', $this->account->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('1234', $log->metadata['last4']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function mask_card_validates_input()
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/financial/mask-card', [
                'card_number' => '12', // too short
            ]);

        $response->assertStatus(422);
    }

    // ─── Filter Data Endpoint ────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_sees_all_filtered_data()
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/financial/filter', [
                'data' => $this->sampleData(),
            ]);

        $response->assertOk();
        $data = $response->json('data');

        $this->assertEquals(150.00, $data['net_rate']);
        $this->assertEquals(50.00, $data['profit']);
        $this->assertEquals(500.00, $data['total_amount']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function printer_sees_no_financial_data_via_api()
    {
        $response = $this->actingAs($this->printer)
            ->postJson('/api/v1/financial/filter', [
                'data' => $this->sampleData(),
            ]);

        $response->assertOk();
        $data = $response->json('data');

        // All financial fields masked
        $this->assertNull($data['net_rate']);
        $this->assertNull($data['profit']);
        $this->assertNull($data['total_amount']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function viewer_sees_totals_not_profit_via_api()
    {
        $response = $this->actingAs($this->viewer)
            ->postJson('/api/v1/financial/filter', [
                'data' => $this->sampleData(),
            ]);

        $response->assertOk();
        $data = $response->json('data');

        // Can see general financial
        $this->assertEquals(500.00, $data['total_amount']);

        // Cannot see profit
        $this->assertNull($data['net_rate']);
        $this->assertNull($data['profit']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function filter_returns_masked_fields_meta()
    {
        $response = $this->actingAs($this->printer)
            ->postJson('/api/v1/financial/filter', [
                'data' => $this->sampleData(),
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'meta' => ['masked_fields', 'visible_fields'],
            ]);

        $this->assertNotEmpty($response->json('meta.masked_fields'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function filter_logs_access_attempt()
    {
        $this->actingAs($this->viewer)
            ->postJson('/api/v1/financial/filter', [
                'data' => $this->sampleData(),
            ]);

        $log = AuditLog::withoutGlobalScopes()
            ->where('action', 'financial.view_attempted')
            ->where('account_id', $this->account->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertArrayHasKey('fields_requested', $log->metadata);
        $this->assertArrayHasKey('fields_masked', $log->metadata);
    }

    // ─── Sensitive Fields Endpoint ───────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function sensitive_fields_lists_all_categories()
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/financial/sensitive-fields');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'profit_sensitive' => ['permission', 'fields'],
                    'general_financial' => ['permission', 'fields'],
                    'card_sensitive' => ['permission', 'fields'],
                ],
            ]);

        $this->assertEquals('financial:profit.view', $response->json('data.profit_sensitive.permission'));
        $this->assertContains('net_rate', $response->json('data.profit_sensitive.fields'));
        $this->assertContains('card_number', $response->json('data.card_sensitive.fields'));
    }

    // ─── Masking Error Handling ──────────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function masking_failure_returns_safe_default()
    {
        // A card number that's all spaces should handle gracefully
        $result = \App\Services\DataMaskingService::maskCardNumber('    ');
        // After stripping non-digits, empty string
        $this->assertEquals('', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function card_with_unusual_short_length()
    {
        // Short but valid-ish card (edge case from AC)
        $result = \App\Services\DataMaskingService::maskCardNumber('12345678');
        $this->assertStringEndsWith('5678', $result);
        $this->assertStringContainsString('•', $result);
    }

    // ─── Printer Template Validation ─────────────────────────────

    #[\PHPUnit\Framework\Attributes\Test]
    public function printer_template_has_no_financial_permissions()
    {
        $template = \App\Rbac\PermissionsCatalog::template('printer');

        $this->assertNotNull($template);
        $this->assertNotContains('financial:view', $template['permissions']);
        $this->assertNotContains('financial:profit.view', $template['permissions']);
        $this->assertNotContains('financial:cards.view', $template['permissions']);
        $this->assertContains('shipments:print', $template['permissions']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function accountant_template_has_full_financial_permissions()
    {
        $template = \App\Rbac\PermissionsCatalog::template('accountant');

        $this->assertNotNull($template);
        $this->assertContains('financial:view', $template['permissions']);
        $this->assertContains('financial:profit.view', $template['permissions']);
        $this->assertContains('financial:cards.view', $template['permissions']);
    }

    // ─── Helper ──────────────────────────────────────────────────

    private function sampleData(): array
    {
        return [
            'net_rate'      => 150.00,
            'retail_rate'   => 200.00,
            'profit'        => 50.00,
            'total_amount'  => 500.00,
            'tax_amount'    => 50.00,
            'card_number'   => '4111111111111234',
            'tracking'      => 'TRACK123',
        ];
    }
}
