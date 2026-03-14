<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Account;
use App\Models\Order;
use App\Models\Parcel;
use App\Models\PricingRule;
use App\Models\RateOption;
use App\Models\RateQuote;
use App\Models\Shipment;
use App\Models\User;
use App\Services\PricingEngine;
use App\Services\RateService;
use App\Exceptions\BusinessException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * RateTest — Unit tests for FR-RT-001→012 (12 FRs) + FR-BRP-001→008
 */
class RateTest extends TestCase
{
    use RefreshDatabase;

    protected Account $account;
    protected User $owner;
    protected User $manager;
    protected User $member;
    protected RateService $rateService;
    protected PricingEngine $pricingEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create(['status' => 'active', 'kyc_status' => 'verified']);
        $this->owner   = User::factory()->create(['account_id' => $this->account->id, 'is_owner' => true]);
        $this->manager = User::factory()->create(['account_id' => $this->account->id, 'is_owner' => false]);
        $this->member  = User::factory()->create(['account_id' => $this->account->id, 'is_owner' => false]);

        $this->manager->grantPermission('shipments:manage');
        $this->manager->grantPermission('rates:manage_rules');
        $this->manager->grantPermission('rates:view_breakdown');

        $this->rateService   = app(RateService::class);
        $this->pricingEngine = app(PricingEngine::class);

        // Create default pricing rule
        PricingRule::factory()->default()->create(['account_id' => $this->account->id]);
    }

    private function createShipment(array $overrides = []): Shipment
    {
        $shipment = Shipment::factory()->create(array_merge([
            'account_id'       => $this->account->id,
            'created_by'       => $this->owner->id,
            'status'           => Shipment::STATUS_DRAFT,
            'sender_country'   => 'SA',
            'sender_city'      => 'الرياض',
            'recipient_country'=> 'SA',
            'recipient_city'   => 'جدة',
            'total_weight'     => 2.5,
            'chargeable_weight'=> 2.5,
            'parcels_count'    => 1,
        ], $overrides));

        Parcel::create(['shipment_id' => $shipment->id, 'sequence' => 1, 'weight' => 2.5]);

        return $shipment;
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-RT-001: Fetch Net Rates from Carriers
    // ═══════════════════════════════════════════════════════════════

    public function test_fetch_rates_for_domestic_shipment(): void
    {
        $shipment = $this->createShipment();

        $quote = $this->rateService->fetchRates(
            $this->account->id, $shipment->id, $this->owner
        );

        $this->assertEquals(RateQuote::STATUS_COMPLETED, $quote->status);
        $this->assertGreaterThan(0, $quote->options_count);
        $this->assertNotEmpty($quote->correlation_id);
    }

    public function test_fetch_rates_for_international_shipment(): void
    {
        $shipment = $this->createShipment([
            'recipient_country' => 'AE',
            'is_international'  => true,
        ]);

        $quote = $this->rateService->fetchRates(
            $this->account->id, $shipment->id, $this->owner
        );

        // International should have more options (DHL Express 9:00)
        $this->assertGreaterThanOrEqual(3, $quote->options_count);
    }

    public function test_fetch_rates_specific_carrier(): void
    {
        $shipment = $this->createShipment();

        $quote = $this->rateService->fetchRates(
            $this->account->id, $shipment->id, $this->owner, 'dhl_express'
        );

        $this->assertTrue($quote->options->every(fn($o) => $o->carrier_code === 'dhl_express'));
    }

    public function test_cannot_fetch_rates_for_purchased_shipment(): void
    {
        $shipment = $this->createShipment(['status' => Shipment::STATUS_PURCHASED]);

        $this->expectException(BusinessException::class);
        $this->rateService->fetchRates($this->account->id, $shipment->id, $this->owner);
    }

    public function test_shipment_transitions_to_rated(): void
    {
        $shipment = $this->createShipment();

        $this->rateService->fetchRates($this->account->id, $shipment->id, $this->owner);

        $shipment->refresh();
        $this->assertEquals(Shipment::STATUS_RATED, $shipment->status);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-RT-002: Retail Rate Calculation with Markup
    // ═══════════════════════════════════════════════════════════════

    public function test_retail_rate_higher_than_net_rate(): void
    {
        $shipment = $this->createShipment();

        $quote = $this->rateService->fetchRates(
            $this->account->id, $shipment->id, $this->owner
        );

        foreach ($quote->options as $option) {
            $this->assertGreaterThan(
                (float) $option->total_net_rate,
                (float) $option->retail_rate,
                "Retail rate must exceed net rate for {$option->service_name}"
            );
        }
    }

    public function test_markup_applied_correctly_percentage(): void
    {
        $rules = collect([
            PricingRule::factory()->make([
                'markup_type'       => 'percentage',
                'markup_percentage' => 20.0,
                'markup_fixed'      => 0,
                'service_fee_fixed' => 0,
                'service_fee_percentage' => 0,
                'min_profit'        => 0,
                'min_retail_price'  => 0,
                'rounding_mode'     => 'none',
                'is_default'        => true,
            ]),
        ]);

        $result = $this->pricingEngine->calculate(100.0, [], $rules);

        $this->assertEquals(120.0, $result['retail_rate']);
        $this->assertEquals(20.0, $result['markup_amount']);
        $this->assertEquals(20.0, $result['profit_margin']);
    }

    public function test_markup_applied_correctly_fixed(): void
    {
        $rules = collect([
            PricingRule::factory()->make([
                'markup_type'       => 'fixed',
                'markup_fixed'      => 15.0,
                'markup_percentage' => 0,
                'service_fee_fixed' => 0,
                'service_fee_percentage' => 0,
                'min_profit'        => 0,
                'min_retail_price'  => 0,
                'rounding_mode'     => 'none',
                'is_default'        => true,
            ]),
        ]);

        $result = $this->pricingEngine->calculate(50.0, [], $rules);

        $this->assertEquals(65.0, $result['retail_rate']);
        $this->assertEquals(15.0, $result['markup_amount']);
    }

    public function test_markup_applied_correctly_both(): void
    {
        $rules = collect([
            PricingRule::factory()->make([
                'markup_type'       => 'both',
                'markup_percentage' => 10.0,
                'markup_fixed'      => 5.0,
                'service_fee_fixed' => 0,
                'service_fee_percentage' => 0,
                'min_profit'        => 0,
                'min_retail_price'  => 0,
                'rounding_mode'     => 'none',
                'is_default'        => true,
            ]),
        ]);

        $result = $this->pricingEngine->calculate(100.0, [], $rules);

        // 100 + 10% (10) + 5 fixed = 115
        $this->assertEquals(115.0, $result['retail_rate']);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-RT-003: Markup Types — Min Profit, Min/Max Retail
    // ═══════════════════════════════════════════════════════════════

    public function test_min_profit_enforced(): void
    {
        $rules = collect([
            PricingRule::factory()->make([
                'markup_type'       => 'percentage',
                'markup_percentage' => 1.0,  // Only 1%
                'min_profit'        => 10.0, // But min profit is 10
                'min_retail_price'  => 0,
                'service_fee_fixed' => 0,
                'service_fee_percentage' => 0,
                'rounding_mode'     => 'none',
                'is_default'        => true,
            ]),
        ]);

        $result = $this->pricingEngine->calculate(100.0, [], $rules);

        // 1% = 1.0 profit, but min is 10, so retail = 110
        $this->assertEquals(110.0, $result['retail_rate']);
        $this->assertEquals(10.0, $result['profit_margin']);
    }

    public function test_min_retail_price_enforced(): void
    {
        $rules = collect([
            PricingRule::factory()->make([
                'markup_type'       => 'percentage',
                'markup_percentage' => 5.0,
                'min_profit'        => 0,
                'min_retail_price'  => 25.0,
                'service_fee_fixed' => 0,
                'service_fee_percentage' => 0,
                'rounding_mode'     => 'none',
                'is_default'        => true,
            ]),
        ]);

        // Net=10, 5% markup = 10.5, but min retail = 25
        $result = $this->pricingEngine->calculate(10.0, [], $rules);

        $this->assertEquals(25.0, $result['retail_rate']);
    }

    public function test_max_retail_price_enforced(): void
    {
        $rules = collect([
            PricingRule::factory()->make([
                'markup_type'       => 'percentage',
                'markup_percentage' => 50.0,
                'min_profit'        => 0,
                'min_retail_price'  => 0,
                'max_retail_price'  => 120.0,
                'service_fee_fixed' => 0,
                'service_fee_percentage' => 0,
                'rounding_mode'     => 'none',
                'is_default'        => true,
            ]),
        ]);

        // Net=100, 50% = 150, but max = 120
        $result = $this->pricingEngine->calculate(100.0, [], $rules);

        $this->assertEquals(120.0, $result['retail_rate']);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-RT-004: Rounding
    // ═══════════════════════════════════════════════════════════════

    public function test_rounding_ceil(): void
    {
        $rules = collect([
            PricingRule::factory()->make([
                'markup_type'       => 'percentage',
                'markup_percentage' => 10.0,
                'min_profit'        => 0,
                'min_retail_price'  => 0,
                'service_fee_fixed' => 0,
                'service_fee_percentage' => 0,
                'rounding_mode'     => 'ceil',
                'rounding_precision'=> 5,
                'is_default'        => true,
            ]),
        ]);

        // Net=33, 10% = 36.3 → ceil to nearest 5 = 40
        $result = $this->pricingEngine->calculate(33.0, [], $rules);

        $this->assertEquals(40.0, $result['retail_rate']);
    }

    public function test_rounding_floor(): void
    {
        $rules = collect([
            PricingRule::factory()->make([
                'markup_type'       => 'percentage',
                'markup_percentage' => 10.0,
                'min_profit'        => 0,
                'min_retail_price'  => 0,
                'service_fee_fixed' => 0,
                'service_fee_percentage' => 0,
                'rounding_mode'     => 'floor',
                'rounding_precision'=> 5,
                'is_default'        => true,
            ]),
        ]);

        // Net=33, 10% = 36.3 → floor to nearest 5 = 35
        $result = $this->pricingEngine->calculate(33.0, [], $rules);

        $this->assertEquals(35.0, $result['retail_rate']);
    }

    public function test_rounding_to_nearest_1(): void
    {
        $rules = collect([
            PricingRule::factory()->make([
                'markup_type'       => 'percentage',
                'markup_percentage' => 15.0,
                'min_profit'        => 0,
                'min_retail_price'  => 0,
                'service_fee_fixed' => 0,
                'service_fee_percentage' => 0,
                'rounding_mode'     => 'round',
                'rounding_precision'=> 1,
                'is_default'        => true,
            ]),
        ]);

        // Net=33, 15% = 37.95 → round to nearest 1 = 38
        $result = $this->pricingEngine->calculate(33.0, [], $rules);

        $this->assertEquals(38.0, $result['retail_rate']);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-RT-005: Pricing Breakdown Storage (FR-BRP-001: Explainable)
    // ═══════════════════════════════════════════════════════════════

    public function test_pricing_breakdown_stored(): void
    {
        $shipment = $this->createShipment();

        $quote = $this->rateService->fetchRates(
            $this->account->id, $shipment->id, $this->owner
        );

        foreach ($quote->options as $option) {
            $this->assertNotNull($option->pricing_breakdown);
            $this->assertIsArray($option->pricing_breakdown);
            $this->assertNotEmpty($option->pricing_breakdown);

            // Must have net_rate and final steps
            $steps = collect($option->pricing_breakdown)->pluck('step');
            $this->assertTrue($steps->contains('net_rate'));
            $this->assertTrue($steps->contains('final'));
        }
    }

    public function test_correlation_id_generated(): void
    {
        $rules = collect([
            PricingRule::factory()->make(['is_default' => true, 'rounding_mode' => 'none',
                'service_fee_fixed' => 0, 'service_fee_percentage' => 0, 'min_profit' => 0, 'min_retail_price' => 0]),
        ]);

        $result = $this->pricingEngine->calculate(100.0, [], $rules);

        $this->assertNotNull($result['correlation_id']);
        $this->assertStringStartsWith('PRC-', $result['correlation_id']);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-RT-006: Badges (Cheapest/Fastest/Best Value/Recommended)
    // ═══════════════════════════════════════════════════════════════

    public function test_badges_assigned_to_options(): void
    {
        $shipment = $this->createShipment();

        $quote = $this->rateService->fetchRates(
            $this->account->id, $shipment->id, $this->owner
        );

        $options = $quote->options;
        $this->assertTrue($options->contains('is_cheapest', true), 'Must have cheapest badge');
        $this->assertTrue($options->contains('is_fastest', true), 'Must have fastest badge');
        $this->assertTrue($options->contains('is_best_value', true), 'Must have best_value badge');
        $this->assertTrue($options->contains('is_recommended', true), 'Must have recommended badge');
    }

    public function test_cheapest_has_lowest_price(): void
    {
        $shipment = $this->createShipment();
        $quote = $this->rateService->fetchRates($this->account->id, $shipment->id, $this->owner);

        $cheapest = $quote->options->firstWhere('is_cheapest', true);
        $minPrice = $quote->options->where('is_available', true)->min('retail_rate');

        $this->assertEquals((float) $minPrice, (float) $cheapest->retail_rate);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-RT-007: Quote TTL & Expiry
    // ═══════════════════════════════════════════════════════════════

    public function test_quote_has_expiry(): void
    {
        $shipment = $this->createShipment();
        $quote = $this->rateService->fetchRates($this->account->id, $shipment->id, $this->owner);

        $this->assertNotNull($quote->expires_at);
        $this->assertTrue($quote->expires_at->isFuture());
    }

    public function test_expired_quote_cannot_be_selected(): void
    {
        $shipment = $this->createShipment();
        $quote = $this->rateService->fetchRates($this->account->id, $shipment->id, $this->owner);

        // Force expire
        $quote->update(['expires_at' => now()->subMinute(), 'is_expired' => true]);

        $this->expectException(BusinessException::class);
        $this->rateService->selectOption(
            $this->account->id, $quote->id, $quote->options->first()->id, 'cheapest', $this->owner
        );
    }

    public function test_reprice_creates_new_quote(): void
    {
        $shipment = $this->createShipment();
        $oldQuote = $this->rateService->fetchRates($this->account->id, $shipment->id, $this->owner);

        // Reprice
        $newQuote = $this->rateService->reprice($this->account->id, $shipment->id, $this->owner);

        $this->assertNotEquals($oldQuote->id, $newQuote->id);
        $oldQuote->refresh();
        $this->assertEquals(RateQuote::STATUS_EXPIRED, $oldQuote->status);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-RT-008: Conditional Pricing Rules (Destination/Weight/Service)
    // ═══════════════════════════════════════════════════════════════

    public function test_rule_matches_by_carrier(): void
    {
        $rule = PricingRule::factory()->make([
            'carrier_code' => 'dhl_express',
            'is_default' => false,
        ]);

        $this->assertTrue($rule->matches(['carrier_code' => 'dhl_express']));
        $this->assertFalse($rule->matches(['carrier_code' => 'aramex']));
    }

    public function test_rule_matches_by_shipment_type(): void
    {
        $rule = PricingRule::factory()->make([
            'shipment_type' => 'international',
            'is_default'    => false,
        ]);

        $this->assertTrue($rule->matches(['origin_country' => 'SA', 'destination_country' => 'AE']));
        $this->assertFalse($rule->matches(['origin_country' => 'SA', 'destination_country' => 'SA']));
    }

    public function test_rule_matches_by_weight_range(): void
    {
        $rule = PricingRule::factory()->make([
            'min_weight' => 5.0,
            'max_weight' => 30.0,
            'is_default' => false,
        ]);

        $this->assertTrue($rule->matches(['total_weight' => 10.0]));
        $this->assertFalse($rule->matches(['total_weight' => 2.0]));
        $this->assertFalse($rule->matches(['total_weight' => 50.0]));
    }

    public function test_higher_priority_rule_applied_first(): void
    {
        // High priority (lower number) specific rule
        $specificRule = PricingRule::factory()->make([
            'carrier_code'      => 'dhl_express',
            'markup_percentage' => 10.0,
            'priority'          => 10,
            'is_default'        => false,
            'markup_type'       => 'percentage',
            'markup_fixed'      => 0,
            'service_fee_fixed' => 0,
            'service_fee_percentage' => 0,
            'min_profit'        => 0,
            'min_retail_price'  => 0,
            'rounding_mode'     => 'none',
        ]);

        $defaultRule = PricingRule::factory()->make([
            'markup_percentage' => 30.0,
            'priority'          => 9999,
            'is_default'        => true,
            'markup_type'       => 'percentage',
            'markup_fixed'      => 0,
            'service_fee_fixed' => 0,
            'service_fee_percentage' => 0,
            'min_profit'        => 0,
            'min_retail_price'  => 0,
            'rounding_mode'     => 'none',
        ]);

        $rules = collect([$specificRule, $defaultRule])->sortBy('priority');

        $result = $this->pricingEngine->calculate(
            100.0,
            ['carrier_code' => 'dhl_express'],
            $rules
        );

        // Should use specific rule (10%) not default (30%)
        $this->assertEquals(110.0, $result['retail_rate']);
    }

    public function test_fallback_to_default_rule(): void
    {
        $specificRule = PricingRule::factory()->make([
            'carrier_code'      => 'fedex',
            'markup_percentage' => 10.0,
            'priority'          => 10,
            'is_default'        => false,
            'markup_type'       => 'percentage',
            'markup_fixed'      => 0,
            'service_fee_fixed' => 0,
            'service_fee_percentage' => 0,
            'min_profit'        => 0,
            'min_retail_price'  => 0,
            'rounding_mode'     => 'none',
        ]);

        $defaultRule = PricingRule::factory()->make([
            'markup_percentage' => 25.0,
            'priority'          => 9999,
            'is_default'        => true,
            'markup_type'       => 'percentage',
            'markup_fixed'      => 0,
            'service_fee_fixed' => 0,
            'service_fee_percentage' => 0,
            'min_profit'        => 0,
            'min_retail_price'  => 0,
            'rounding_mode'     => 'none',
        ]);

        $rules = collect([$specificRule, $defaultRule])->sortBy('priority');

        // DHL won't match fedex-only rule, should fallback to default
        $result = $this->pricingEngine->calculate(100.0, ['carrier_code' => 'dhl_express'], $rules);

        $this->assertEquals(125.0, $result['retail_rate']);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-RT-009: Expired Subscription Surcharge
    // ═══════════════════════════════════════════════════════════════

    public function test_expired_surcharge_applied(): void
    {
        $rules = collect([
            PricingRule::factory()->make([
                'markup_type'                  => 'percentage',
                'markup_percentage'            => 15.0,
                'markup_fixed'                 => 0,
                'is_expired_surcharge'         => true,
                'expired_surcharge_percentage' => 25.0,
                'service_fee_fixed'            => 0,
                'service_fee_percentage'       => 0,
                'min_profit'                   => 0,
                'min_retail_price'             => 0,
                'rounding_mode'                => 'none',
                'is_default'                   => true,
            ]),
        ]);

        $resultActive  = $this->pricingEngine->calculate(100.0, [], $rules, false);
        $resultExpired = $this->pricingEngine->calculate(100.0, [], $rules, true);

        // Active: 100 + 15% = 115
        $this->assertEquals(115.0, $resultActive['retail_rate']);

        // Expired: 100 + 15% + 25% surcharge on net = 115 + 25 = 140
        $this->assertEquals(140.0, $resultExpired['retail_rate']);
        $this->assertEquals(25.0, $resultExpired['surcharge']);
    }

    public function test_no_surcharge_when_not_expired(): void
    {
        $rules = collect([
            PricingRule::factory()->make([
                'markup_type'                  => 'percentage',
                'markup_percentage'            => 10.0,
                'is_expired_surcharge'         => true,
                'expired_surcharge_percentage' => 30.0,
                'markup_fixed' => 0, 'service_fee_fixed' => 0, 'service_fee_percentage' => 0,
                'min_profit' => 0, 'min_retail_price' => 0, 'rounding_mode' => 'none',
                'is_default' => true,
            ]),
        ]);

        $result = $this->pricingEngine->calculate(100.0, [], $rules, false);

        $this->assertEquals(0.0, $result['surcharge']);
        $this->assertEquals(110.0, $result['retail_rate']);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-RT-010: Select Rate Option (Manual / Auto)
    // ═══════════════════════════════════════════════════════════════

    public function test_select_option_manually(): void
    {
        $shipment = $this->createShipment();
        $quote = $this->rateService->fetchRates($this->account->id, $shipment->id, $this->owner);

        $optionId = $quote->options->first()->id;

        $updated = $this->rateService->selectOption(
            $this->account->id, $quote->id, $optionId, 'cheapest', $this->owner
        );

        $this->assertEquals(RateQuote::STATUS_SELECTED, $updated->status);
        $this->assertEquals($optionId, $updated->selected_option_id);
    }

    public function test_auto_select_cheapest(): void
    {
        $shipment = $this->createShipment();
        $quote = $this->rateService->fetchRates($this->account->id, $shipment->id, $this->owner);

        $updated = $this->rateService->selectOption(
            $this->account->id, $quote->id, null, 'cheapest', $this->owner
        );

        $selected = $updated->selectedOption;
        $cheapest = $quote->options->where('is_available', true)->sortBy('retail_rate')->first();
        $this->assertEquals($cheapest->id, $selected->id);
    }

    public function test_auto_select_fastest(): void
    {
        $shipment = $this->createShipment();
        $quote = $this->rateService->fetchRates($this->account->id, $shipment->id, $this->owner);

        $updated = $this->rateService->selectOption(
            $this->account->id, $quote->id, null, 'fastest', $this->owner
        );

        $selected = $updated->selectedOption;
        $fastest = $quote->options->where('is_available', true)->sortBy('estimated_days_min')->first();
        $this->assertEquals($fastest->id, $selected->id);
    }

    public function test_select_updates_shipment_carrier(): void
    {
        $shipment = $this->createShipment();
        $quote = $this->rateService->fetchRates($this->account->id, $shipment->id, $this->owner);

        $this->rateService->selectOption(
            $this->account->id, $quote->id, null, 'cheapest', $this->owner
        );

        $shipment->refresh();
        $this->assertNotNull($shipment->carrier_code);
        $this->assertNotNull($shipment->carrier_name);
        $this->assertNotNull($shipment->total_charge);
        $this->assertGreaterThan(0, (float) $shipment->total_charge);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-RT-011: Pricing Detail Visibility (RBAC)
    // ═══════════════════════════════════════════════════════════════

    public function test_owner_sees_full_breakdown(): void
    {
        $shipment = $this->createShipment();
        $quote = $this->rateService->fetchRates($this->account->id, $shipment->id, $this->owner);

        $result = $this->rateService->getQuote(
            $this->account->id, $quote->id, $this->owner
        );

        $option = $result['options']->first();
        $this->assertArrayHasKey('net_rate', $option);
        $this->assertArrayHasKey('markup_amount', $option);
        $this->assertArrayHasKey('profit_margin', $option);
        $this->assertArrayHasKey('pricing_breakdown', $option);
    }

    public function test_member_sees_only_retail_rate(): void
    {
        $shipment = $this->createShipment();
        $quote = $this->rateService->fetchRates($this->account->id, $shipment->id, $this->owner);

        $result = $this->rateService->getQuote(
            $this->account->id, $quote->id, $this->member
        );

        $option = $result['options']->first();
        $this->assertArrayNotHasKey('pricing_breakdown', $option);
        $this->assertArrayNotHasKey('rule_evaluation_log', $option);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-RT-012: KYC-based Service Restriction
    // ═══════════════════════════════════════════════════════════════

    public function test_unverified_kyc_filters_premium_intl_services(): void
    {
        $unverified = Account::factory()->create(['kyc_status' => 'pending']);
        $user = User::factory()->create(['account_id' => $unverified->id, 'is_owner' => true]);
        PricingRule::factory()->default()->create(['account_id' => $unverified->id]);

        $shipment = Shipment::factory()->create([
            'account_id'        => $unverified->id,
            'created_by'        => $user->id,
            'status'            => Shipment::STATUS_DRAFT,
            'sender_country'    => 'SA',
            'recipient_country' => 'AE',
            'is_international'  => true,
            'total_weight'      => 2.0,
            'chargeable_weight' => 2.0,
            'parcels_count'     => 1,
        ]);
        Parcel::create(['shipment_id' => $shipment->id, 'sequence' => 1, 'weight' => 2.0]);

        $quote = $this->rateService->fetchRates($unverified->id, $shipment->id, $user);

        // Express 9:00 should be filtered out for unverified accounts
        $serviceCodes = $quote->options->pluck('service_code')->toArray();
        $this->assertNotContains('express_9_00', $serviceCodes);
    }

    // ═══════════════════════════════════════════════════════════════
    // FR-BRP-003: Service Fee (Independent)
    // ═══════════════════════════════════════════════════════════════

    public function test_service_fee_applied_independently(): void
    {
        $rules = collect([
            PricingRule::factory()->make([
                'markup_type'           => 'percentage',
                'markup_percentage'     => 10.0,
                'markup_fixed'          => 0,
                'service_fee_fixed'     => 5.0,
                'service_fee_percentage'=> 2.0,
                'min_profit'            => 0,
                'min_retail_price'      => 0,
                'rounding_mode'         => 'none',
                'is_default'            => true,
            ]),
        ]);

        // Net=100, markup=10%, fee=5+2%=7
        $result = $this->pricingEngine->calculate(100.0, [], $rules);

        $this->assertEquals(7.0, $result['service_fee']);
        $this->assertEquals(117.0, $result['retail_rate']);
    }

    // ═══════════════════════════════════════════════════════════════
    // Pricing Rules CRUD (FR-RT-008)
    // ═══════════════════════════════════════════════════════════════

    public function test_create_pricing_rule(): void
    {
        $rule = $this->rateService->createPricingRule($this->account->id, [
            'name'              => 'قاعدة DHL محلي',
            'carrier_code'      => 'dhl_express',
            'shipment_type'     => 'domestic',
            'markup_type'       => 'percentage',
            'markup_percentage' => 12.0,
            'priority'          => 50,
        ], $this->owner);

        $this->assertNotNull($rule->id);
        $this->assertEquals('dhl_express', $rule->carrier_code);
    }

    public function test_update_pricing_rule(): void
    {
        $rule = PricingRule::factory()->create(['account_id' => $this->account->id]);

        $updated = $this->rateService->updatePricingRule(
            $this->account->id, $rule->id, ['markup_percentage' => 20.0], $this->owner
        );

        $this->assertEquals(20.0, (float) $updated->markup_percentage);
    }

    public function test_delete_pricing_rule(): void
    {
        $rule = PricingRule::factory()->create(['account_id' => $this->account->id]);

        $this->rateService->deletePricingRule($this->account->id, $rule->id, $this->owner);

        $this->assertSoftDeleted('pricing_rules', ['id' => $rule->id]);
    }

    public function test_member_cannot_manage_rules(): void
    {
        $this->expectException(BusinessException::class);
        $this->rateService->createPricingRule($this->account->id, [
            'name' => 'Test', 'markup_type' => 'percentage',
        ], $this->member);
    }

    public function test_list_pricing_rules_includes_platform_defaults(): void
    {
        // Create platform-wide rule
        PricingRule::factory()->platform()->create(['name' => 'Platform Default', 'is_default' => true]);
        // Account-specific rule
        PricingRule::factory()->create(['account_id' => $this->account->id, 'name' => 'Account Rule']);

        $rules = $this->rateService->listPricingRules($this->account->id);

        // Should include both platform (null account_id) and account-specific
        $this->assertGreaterThanOrEqual(2, $rules->count());
    }

    // ═══════════════════════════════════════════════════════════════
    // Statistics & Audit
    // ═══════════════════════════════════════════════════════════════

    public function test_rate_fetch_is_audited(): void
    {
        $shipment = $this->createShipment();
        $this->rateService->fetchRates($this->account->id, $shipment->id, $this->owner);

        $this->assertDatabaseHas('audit_logs', [
            'account_id' => $this->account->id,
            'action'     => 'rate.fetched',
        ]);
    }

    public function test_rate_selection_is_audited(): void
    {
        $shipment = $this->createShipment();
        $quote = $this->rateService->fetchRates($this->account->id, $shipment->id, $this->owner);

        $this->rateService->selectOption(
            $this->account->id, $quote->id, null, 'cheapest', $this->owner
        );

        $this->assertDatabaseHas('audit_logs', [
            'account_id' => $this->account->id,
            'action'     => 'rate.selected',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // Edge Cases
    // ═══════════════════════════════════════════════════════════════

    public function test_no_matching_rule_passes_through_net_rate(): void
    {
        // Empty rules collection
        $result = $this->pricingEngine->calculate(100.0, [], collect([]));

        $this->assertEquals(100.0, $result['retail_rate']);
        $this->assertNull($result['pricing_rule_id']);
    }

    public function test_zero_net_rate_still_applies_min_retail(): void
    {
        $rules = collect([
            PricingRule::factory()->make([
                'markup_type'       => 'percentage',
                'markup_percentage' => 10.0,
                'markup_fixed'      => 0,
                'min_retail_price'  => 15.0,
                'min_profit'        => 0,
                'service_fee_fixed' => 0,
                'service_fee_percentage' => 0,
                'rounding_mode'     => 'none',
                'is_default'        => true,
            ]),
        ]);

        $result = $this->pricingEngine->calculate(0.0, [], $rules);

        $this->assertEquals(15.0, $result['retail_rate']);
    }
}
