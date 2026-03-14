<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\PricingRule;
use App\Models\PricingRuleSet;
use App\Models\Role;
use App\Models\RoundingPolicy;
use App\Models\User;
use App\Services\PricingEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API Tests — BRP Module (FR-BRP-001→008)
 * 15 tests covering pricing API endpoints.
 */
class PricingApiTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account = Account::factory()->create();
        $role = Role::factory()->create(['account_id' => $this->account->id]);
        $this->user = $this->createUserWithRole((string) $this->account->id, (string) $role->id);
    }

    // FR-BRP-001
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_calculate_price(): void
    {
        PricingRule::factory()->create(['account_id' => $this->account->id, 'markup_percentage' => 20, 'is_active' => true]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/pricing/calculate', [
            'net_rate' => 100, 'carrier_code' => 'DHL', 'service_code' => 'EXPRESS',
        ]);
        $response->assertOk()->assertJsonStructure(['data' => ['net_rate', 'retail_rate', 'correlation_id', 'applied_rules']]);
    }

    // FR-BRP-001
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_calculate_deterministic(): void
    {
        PricingRule::factory()->create(['account_id' => $this->account->id, 'markup_percentage' => 15, 'is_active' => true]);

        $payload = ['net_rate' => 80, 'carrier_code' => 'DHL', 'service_code' => 'EXPRESS'];
        $r1 = $this->actingAs($this->user)->postJson('/api/v1/pricing/calculate', $payload);
        $r2 = $this->actingAs($this->user)->postJson('/api/v1/pricing/calculate', $payload);

        $this->assertEquals($r1->json('data.retail_rate'), $r2->json('data.retail_rate'));
    }

    // FR-BRP-006
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_get_breakdown(): void
    {
        $engine = $this->app->make(PricingEngineService::class);
        $engine->calculatePrice($this->account->id, 100, ['carrier_code' => 'DHL', 'service_code' => 'EXP', 'currency' => 'SAR', 'subscription_status' => 'active'], 'shipment', 'SH-001');

        $response = $this->actingAs($this->user)->getJson('/api/v1/pricing/breakdowns/shipment/SH-001');
        $response->assertOk()->assertJsonPath('data.entity_id', 'SH-001');
    }

    // FR-BRP-006
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_list_breakdowns(): void
    {
        $engine = $this->app->make(PricingEngineService::class);
        $engine->calculatePrice($this->account->id, 50, ['carrier_code' => 'DHL', 'service_code' => 'EXP', 'currency' => 'SAR', 'subscription_status' => 'active']);

        $response = $this->actingAs($this->user)->getJson('/api/v1/pricing/breakdowns');
        $response->assertOk();
    }

    // FR-BRP-008
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_create_rule_set(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/pricing/rule-sets', ['name' => 'Q1 2026 Rules']);
        $response->assertStatus(201)->assertJsonPath('data.name', 'Q1 2026 Rules');
    }

    // FR-BRP-008
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_list_rule_sets(): void
    {
        PricingRuleSet::factory()->create(['account_id' => $this->account->id]);
        $response = $this->actingAs($this->user)->getJson('/api/v1/pricing/rule-sets');
        $response->assertOk();
    }

    // FR-BRP-008
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_get_rule_set(): void
    {
        $set = PricingRuleSet::factory()->create(['account_id' => $this->account->id]);
        $response = $this->actingAs($this->user)->getJson("/api/v1/pricing/rule-sets/{$set->id}");
        $response->assertOk()->assertJsonPath('data.id', $set->id);
    }

    // FR-BRP-008
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_activate_rule_set(): void
    {
        $set = PricingRuleSet::factory()->draft()->create(['account_id' => $this->account->id]);
        $response = $this->actingAs($this->user)->postJson("/api/v1/pricing/rule-sets/{$set->id}/activate");
        $response->assertOk()->assertJsonPath('data.status', 'active');
    }

    // FR-BRP-005
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_set_rounding(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/pricing/rounding', [
            'currency' => 'SAR', 'method' => 'up', 'precision' => 0, 'step' => 1,
        ]);
        $response->assertOk()->assertJsonPath('data.method', 'up');
    }

    // FR-BRP-007
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_set_expired_policy(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/pricing/expired-policy', [
            'policy_type' => 'surcharge_percent', 'value' => 25, 'reason_label' => 'Expired +25%',
        ]);
        $response->assertOk()->assertJsonPath('data.policy_type', 'surcharge_percent');
    }

    // FR-BRP-001
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_calculate_no_rules(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/pricing/calculate', [
            'net_rate' => 50, 'carrier_code' => 'DHL', 'service_code' => 'EXPRESS',
        ]);
        $response->assertOk()->assertJsonPath('data.retail_rate', '50.00');
    }

    // FR-BRP-004
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_guardrail_applied(): void
    {
        PricingRule::factory()->create([
            'account_id' => $this->account->id, 'min_retail_price' => 100,
            'markup_percentage' => 5, 'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/pricing/calculate', [
            'net_rate' => 30, 'carrier_code' => 'DHL', 'service_code' => 'EXPRESS',
        ]);
        $retail = (float) $response->json('data.retail_rate');
        $this->assertGreaterThanOrEqual(100, $retail);
    }

    // FR-BRP-005
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_rounding_applied(): void
    {
        RoundingPolicy::create(['currency' => 'SAR', 'method' => 'up', 'precision' => 0, 'step' => 1]);
        PricingRule::factory()->create(['account_id' => $this->account->id, 'markup_percentage' => 7, 'is_active' => true]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/pricing/calculate', [
            'net_rate' => 33.33, 'carrier_code' => 'DHL', 'service_code' => 'EXPRESS', 'currency' => 'SAR',
        ]);
        $retail = (float) $response->json('data.retail_rate');
        $this->assertEquals(floor($retail), $retail); // Should be whole number
    }

    // FR-BRP-001
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_validation_required_fields(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/pricing/calculate', []);
        $response->assertStatus(422);
    }

    // FR-BRP-006
    #[\PHPUnit\Framework\Attributes\Test]
    public function test_api_breakdown_not_found(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/pricing/breakdowns/shipment/NONEXIST');
        $response->assertStatus(404);
    }
}
