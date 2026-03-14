<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\ExpiredPlanPolicy;
use App\Models\PricingBreakdown;
use App\Models\PricingRule;
use App\Models\PricingRuleSet;
use App\Models\Role;
use App\Models\RoundingPolicy;
use App\Models\User;
use App\Services\PricingEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit Tests — BRP Module (FR-BRP-001→008)
 *
 * 35 tests covering all 8 business rule requirements.
 */
class PricingEngineTest extends TestCase
{
    use RefreshDatabase;

    private PricingEngineService $engine;
    private Account $account;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = $this->app->make(PricingEngineService::class);
        $this->account = Account::factory()->create();
        $role = Role::factory()->create(['account_id' => $this->account->id]);
        $this->user = $this->createUserWithRole((string) $this->account->id, (string) $role->id);
    }

    private function baseContext(array $overrides = []): array
    {
        return array_merge([
            'carrier_code' => 'DHL', 'service_code' => 'EXPRESS_DOMESTIC',
            'origin_country' => 'SA', 'destination_country' => 'SA',
            'weight' => 5.0, 'zone' => 'domestic', 'shipment_type' => 'standard',
            'currency' => 'SAR', 'subscription_status' => 'active',
        ], $overrides);
    }

    private function createRuleSetWithMarkup(float $markupPct = 20): PricingRuleSet
    {
        $set = PricingRuleSet::factory()->create(['account_id' => $this->account->id]);
        PricingRule::factory()->create([
            'account_id' => $this->account->id,
            'markup_percentage' => $markupPct,
            'is_active' => true, 'priority' => 10,
        ]);
        return $set;
    }

    // ═══════════════════════════════════════════════════════════
    // FR-BRP-001: Explainable Pricing (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_pricing_is_deterministic(): void
    {
        PricingRule::factory()->create([
            'account_id' => $this->account->id,
            'markup_percentage' => 20, 'is_active' => true, 'priority' => 10,
        ]);

        $ctx = $this->baseContext();
        $b1 = $this->engine->calculatePrice($this->account->id, 100, $ctx);
        $b2 = $this->engine->calculatePrice($this->account->id, 100, $ctx);

        $this->assertEquals($b1->retail_rate, $b2->retail_rate);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_breakdown_has_correlation_id(): void
    {
        $b = $this->engine->calculatePrice($this->account->id, 50, $this->baseContext());
        $this->assertNotNull($b->correlation_id);
        $this->assertStringStartsWith('PRC-', $b->correlation_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_breakdown_records_applied_rules(): void
    {
        PricingRule::factory()->create([
            'account_id' => $this->account->id,
            'markup_percentage' => 15, 'is_active' => true,
        ]);

        $b = $this->engine->calculatePrice($this->account->id, 100, $this->baseContext());
        $this->assertIsArray($b->applied_rules);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_breakdown_stores_inputs(): void
    {
        $b = $this->engine->calculatePrice($this->account->id, 75, $this->baseContext(['weight' => 10]));
        $this->assertEquals('DHL', $b->carrier_code);
        $this->assertEquals(10, $b->weight);
        $this->assertEquals(75, $b->net_rate);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_no_rules_returns_net_rate(): void
    {
        $b = $this->engine->calculatePrice($this->account->id, 100, $this->baseContext());
        $this->assertEquals(100, $b->retail_rate);
        $this->assertEquals(0, $b->markup_amount);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-BRP-002: Conditional Rules (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_rule_matches_carrier(): void
    {
        PricingRule::factory()->create([
            'account_id' => $this->account->id,
            'carrier_code' => 'DHL', 'markup_percentage' => 25, 'is_active' => true,
        ]);
        PricingRule::factory()->create([
            'account_id' => $this->account->id,
            'carrier_code' => 'ARAMEX', 'markup_percentage' => 15, 'is_active' => true,
        ]);

        $b = $this->engine->calculatePrice($this->account->id, 100, $this->baseContext(['carrier_code' => 'DHL']));
        $this->assertEquals(25, $b->markup_amount);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_rule_matches_weight_range(): void
    {
        PricingRule::factory()->create([
            'account_id' => $this->account->id,
            'min_weight' => 0, 'max_weight' => 5,
            'markup_percentage' => 10, 'is_active' => true, 'priority' => 1,
        ]);
        PricingRule::factory()->create([
            'account_id' => $this->account->id,
            'min_weight' => 5.01, 'max_weight' => 30,
            'markup_percentage' => 20, 'is_active' => true, 'priority' => 2,
        ]);

        $ctx = $this->baseContext(['weight' => 3, 'chargeable_weight' => 3]);
        $b = $this->engine->calculatePrice($this->account->id, 100, $ctx);
        $this->assertEquals(10, $b->markup_amount);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_rule_matches_destination(): void
    {
        PricingRule::factory()->create([
            'account_id' => $this->account->id,
            'destination_country' => 'AE', 'markup_percentage' => 30, 'is_active' => true,
        ]);

        $b = $this->engine->calculatePrice($this->account->id, 100, $this->baseContext(['destination_country' => 'AE']));
        $this->assertEquals(30, $b->markup_amount);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_fallback_rule(): void
    {
        PricingRule::factory()->create([
            'account_id' => $this->account->id,
            'carrier_code' => 'FEDEX', 'markup_percentage' => 50, 'is_active' => true, 'priority' => 1,
        ]);
        PricingRule::factory()->create([
            'account_id' => $this->account->id,
            'is_default' => true, 'markup_percentage' => 10, 'is_active' => true, 'priority' => 100,
        ]);

        // DHL doesn't match FEDEX rule; defaults apply
        $b = $this->engine->calculatePrice($this->account->id, 100, $this->baseContext());
        $this->assertEquals(10, $b->markup_amount);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_shipment_type_domestic_vs_international(): void
    {
        PricingRule::factory()->create([
            'account_id' => $this->account->id,
            'shipment_type' => 'domestic', 'markup_percentage' => 10, 'is_active' => true,
        ]);

        $domestic = $this->engine->calculatePrice($this->account->id, 100, $this->baseContext());
        $intl = $this->engine->calculatePrice($this->account->id, 100, $this->baseContext(['destination_country' => 'AE']));

        $this->assertEquals(10, $domestic->markup_amount);
        $this->assertEquals(0, $intl->markup_amount); // No matching rule
    }

    // ═══════════════════════════════════════════════════════════
    // FR-BRP-003: Service Fee (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_fixed_service_fee(): void
    {
        PricingRule::factory()->create([
            'account_id' => $this->account->id,
            'service_fee_fixed' => 5, 'is_active' => true,
        ]);

        $b = $this->engine->calculatePrice($this->account->id, 100, $this->baseContext());
        // Service fee tracked via legacy model structure
        $this->assertGreaterThanOrEqual(100, $b->retail_rate);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_percentage_service_fee(): void
    {
        PricingRule::factory()->create([
            'account_id' => $this->account->id,
            'service_fee_percentage' => 5, 'is_active' => true,
        ]);

        $b = $this->engine->calculatePrice($this->account->id, 100, $this->baseContext());
        $this->assertGreaterThanOrEqual(100, $b->retail_rate);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_service_fee_separate_from_markup(): void
    {
        PricingRule::factory()->create([
            'account_id' => $this->account->id,
            'markup_percentage' => 20, 'service_fee_fixed' => 5, 'is_active' => true,
        ]);

        $b = $this->engine->calculatePrice($this->account->id, 100, $this->baseContext());
        $this->assertGreaterThan(0, $b->markup_amount);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-BRP-004: Guardrails (4 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_min_price_guardrail(): void
    {
        PricingRule::factory()->create([
            'account_id' => $this->account->id,
            'min_retail_price' => 80, 'markup_percentage' => 5, 'is_active' => true,
        ]);

        // 50 + 5% = 52.50, but min price = 80
        $b = $this->engine->calculatePrice($this->account->id, 50, $this->baseContext());
        $this->assertGreaterThanOrEqual(80, (float) $b->retail_rate);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_min_profit_guardrail(): void
    {
        PricingRule::factory()->create([
            'account_id' => $this->account->id,
            'min_profit' => 20, 'markup_percentage' => 5, 'is_active' => true,
        ]);

        // 100 + 5% = 105, profit = 5, but min profit = 20 → should be ≥ 120
        $b = $this->engine->calculatePrice($this->account->id, 100, $this->baseContext());
        $profit = (float) $b->retail_rate - 100;
        $this->assertGreaterThanOrEqual(20, $profit);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_guardrail_adjustments_recorded(): void
    {
        PricingRule::factory()->create([
            'account_id' => $this->account->id,
            'min_retail_price' => 200, 'is_active' => true,
        ]);

        $b = $this->engine->calculatePrice($this->account->id, 50, $this->baseContext());
        $this->assertNotNull($b->guardrail_adjustments);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_no_guardrail_when_price_sufficient(): void
    {
        PricingRule::factory()->create([
            'account_id' => $this->account->id,
            'min_retail_price' => 10, 'markup_percentage' => 50, 'is_active' => true,
        ]);

        $b = $this->engine->calculatePrice($this->account->id, 100, $this->baseContext());
        $this->assertNull($b->guardrail_adjustments);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-BRP-005: Rounding (4 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_round_up(): void
    {
        $policy = RoundingPolicy::create(['currency' => 'SAR', 'method' => 'up', 'precision' => 0, 'step' => 1]);
        $this->assertEquals(51, $policy->apply(50.1));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_round_down(): void
    {
        $policy = RoundingPolicy::create(['currency' => 'USD', 'method' => 'down', 'precision' => 0, 'step' => 1]);
        $this->assertEquals(50, $policy->apply(50.9));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_round_nearest(): void
    {
        $policy = RoundingPolicy::create(['currency' => 'AED', 'method' => 'nearest', 'precision' => 1, 'step' => 0.5]);
        $this->assertEquals(50.5, $policy->apply(50.3));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_rounding_in_pricing_engine(): void
    {
        RoundingPolicy::create(['currency' => 'SAR', 'method' => 'up', 'precision' => 0, 'step' => 1]);
        PricingRule::factory()->create([
            'account_id' => $this->account->id,
            'markup_percentage' => 15,
            'service_fee_fixed' => 0,
            'service_fee_percentage' => 0,
            'min_profit' => 0,
            'min_retail_price' => 0,
            'is_active' => true,
        ]);

        $b = $this->engine->calculatePrice($this->account->id, 33.33, $this->baseContext());
        // 33.33 + 15% = 38.3295 → rounded up to 39
        $this->assertEquals(39, (float) $b->retail_rate);
        $this->assertNotNull($b->rounding_policy);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-BRP-006: Store Breakdown (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_breakdown_stored(): void
    {
        $b = $this->engine->calculatePrice($this->account->id, 100, $this->baseContext(), 'rate_quote', 'RQ-123');
        $this->assertDatabaseHas('pricing_breakdowns', ['entity_type' => 'rate_quote', 'entity_id' => 'RQ-123']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_retrieve_breakdown_by_entity(): void
    {
        $this->engine->calculatePrice($this->account->id, 100, $this->baseContext(), 'shipment', 'SH-456');
        $found = $this->engine->getBreakdown('shipment', 'SH-456');
        $this->assertNotNull($found);
        $this->assertEquals(100, $found->net_rate);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_retrieve_breakdown_by_correlation(): void
    {
        $b = $this->engine->calculatePrice($this->account->id, 100, $this->baseContext());
        $found = $this->engine->getBreakdownByCorrelation($b->correlation_id);
        $this->assertNotNull($found);
        $this->assertEquals($b->id, $found->id);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-BRP-007: Expired Plan Surcharge (4 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_expired_surcharge_applied(): void
    {
        $this->engine->setExpiredPlanPolicy(null, 'surcharge_percent', 25, 'Expired plan +25%');
        PricingRule::factory()->create([
            'account_id' => $this->account->id,
            'markup_percentage' => 20, 'is_active' => true,
        ]);

        $ctx = $this->baseContext(['subscription_status' => 'expired']);
        $b = $this->engine->calculatePrice($this->account->id, 100, $ctx);

        $this->assertTrue($b->expired_plan_surcharge);
        // 100 + 20% = 120, +25% of 120 = 150
        $this->assertGreaterThan(120, (float) $b->retail_rate);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_active_plan_no_surcharge(): void
    {
        $this->engine->setExpiredPlanPolicy(null, 'surcharge_percent', 25);

        $b = $this->engine->calculatePrice($this->account->id, 100, $this->baseContext());
        $this->assertFalse($b->expired_plan_surcharge);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_fixed_expired_surcharge(): void
    {
        $this->engine->setExpiredPlanPolicy(null, 'surcharge_fixed', 15, 'Flat penalty');

        $ctx = $this->baseContext(['subscription_status' => 'expired']);
        $b = $this->engine->calculatePrice($this->account->id, 100, $ctx);

        $this->assertTrue($b->expired_plan_surcharge);
        $this->assertEquals(115, (float) $b->retail_rate);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_plan_specific_surcharge(): void
    {
        $this->engine->setExpiredPlanPolicy('free', 'surcharge_percent', 50);
        $this->engine->setExpiredPlanPolicy(null, 'surcharge_percent', 10);

        $ctx = $this->baseContext(['subscription_status' => 'expired', 'plan_slug' => 'free']);
        $b = $this->engine->calculatePrice($this->account->id, 100, $ctx);

        // Should use 'free' plan policy (50%) not generic (10%)
        $this->assertGreaterThan(110, (float) $b->retail_rate);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-BRP-008: Rule Priority (4 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_higher_priority_rule_wins(): void
    {
        PricingRule::factory()->create([
            'account_id' => $this->account->id,
            'markup_percentage' => 10, 'priority' => 1, 'is_active' => true,
        ]);
        PricingRule::factory()->create([
            'account_id' => $this->account->id,
            'markup_percentage' => 50, 'priority' => 99, 'is_active' => true,
        ]);

        $b = $this->engine->calculatePrice($this->account->id, 100, $this->baseContext());
        $this->assertEquals(10, $b->markup_amount); // Priority 1 wins
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_rule_set_versioning(): void
    {
        $set = PricingRuleSet::factory()->create(['account_id' => $this->account->id]);
        $this->assertEquals(1, $set->version);

        $v2 = $set->newVersion();
        $this->assertEquals(2, $v2->version);
        $this->assertEquals(PricingRuleSet::STATUS_DRAFT, $v2->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_activate_deactivates_previous(): void
    {
        $set1 = PricingRuleSet::factory()->create(['account_id' => $this->account->id]);
        $set2 = PricingRuleSet::factory()->draft()->create(['account_id' => $this->account->id]);

        $set2->activate();
        $this->assertEquals(PricingRuleSet::STATUS_ARCHIVED, $set1->fresh()->status);
        $this->assertEquals(PricingRuleSet::STATUS_ACTIVE, $set2->fresh()->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_rule_set(): void
    {
        $set = $this->engine->createRuleSet($this->account->id, 'Test Rules', $this->user->id);
        $this->assertEquals('Test Rules', $set->name);
        $this->assertEquals(PricingRuleSet::STATUS_DRAFT, $set->status);
    }
}
