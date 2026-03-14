<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\KycVerification;
use App\Models\PricingBreakdown;
use App\Models\PricingRule;
use App\Models\RateOption;
use App\Models\RateQuote;
use App\Models\User;
use App\Services\PricingEngineService;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShipmentCanonicalPricingPathTest extends TestCase
{
    public function test_non_fedex_shipment_quotes_use_the_canonical_pricing_engine_path_with_retail_pricing_when_rules_exist(): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createShipmentActor();
        $shipment = $this->createReadyForRatesShipment($user);
        $this->createRetailPricingRule((string) $user->account_id);

        $response = $this->postJson('/api/v1/shipments/' . $shipment['id'] . '/rates?carrier=dhl_express', [], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.status', RateQuote::STATUS_COMPLETED)
            ->assertJsonPath('data.options.0.rule_evaluation_log.canonical_engine', PricingEngineService::class)
            ->assertJsonPath('data.options.0.rule_evaluation_log.pricing_path', 'shipment_quote')
            ->assertJsonPath('data.options.0.rule_evaluation_log.pricing_stage', 'retail');

        $option = RateOption::query()
            ->where('rate_quote_id', (string) $response->json('data.id'))
            ->orderBy('retail_rate')
            ->firstOrFail();

        $breakdown = PricingBreakdown::query()->findOrFail($option->pricing_breakdown_id);

        $this->assertSame('retail', data_get($option->pricing_breakdown, 'stage'));
        $this->assertSame(PricingEngineService::class, data_get($option->rule_evaluation_log, 'canonical_engine'));
        $this->assertNotSame((string) $option->total_net_rate, (string) $option->retail_rate);
        $this->assertSame('retail', (string) $breakdown->pricing_stage);
        $this->assertSame((string) $option->id, (string) $breakdown->rate_option_id);
    }

    public function test_reprice_keeps_the_same_canonical_shipment_pricing_path_with_retail_rules(): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createShipmentActor();
        $shipment = $this->createReadyForRatesShipment($user);
        $this->createRetailPricingRule((string) $user->account_id);

        $firstQuote = $this->postJson('/api/v1/shipments/' . $shipment['id'] . '/rates?carrier=aramex', [], $this->authHeaders($user))
            ->assertOk()
            ->json('data');

        $secondQuote = $this->postJson('/api/v1/shipments/' . $shipment['id'] . '/reprice?carrier=aramex', [], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.options.0.rule_evaluation_log.canonical_engine', PricingEngineService::class)
            ->assertJsonPath('data.options.0.rule_evaluation_log.pricing_stage', 'retail')
            ->json('data');

        $this->assertNotSame($firstQuote['id'], $secondQuote['id']);
        $this->assertSame($firstQuote['options'][0]['retail_rate'], $secondQuote['options'][0]['retail_rate']);
        $this->assertSame($firstQuote['options'][0]['service_code'], $secondQuote['options'][0]['service_code']);
    }

    private function createRetailPricingRule(string $accountId): void
    {
        PricingRule::factory()->create([
            'account_id' => $accountId,
            'is_active' => true,
            'is_default' => true,
            'markup_type' => 'percentage',
            'markup_percentage' => 10,
            'markup_fixed' => 0,
            'service_fee_fixed' => 2,
            'service_fee_percentage' => 0,
            'min_profit' => 0,
            'min_retail_price' => 0,
            'rounding_mode' => 'none',
            'rounding_precision' => 1,
        ]);
    }

    private function createShipmentActor(): User
    {
        $account = Account::factory()->organization()->create([
            'name' => 'Canonical Pricing Org ' . Str::upper(Str::random(4)),
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'account_id' => $account->id,
            'user_type' => 'external',
            'status' => 'active',
        ]);

        $this->grantTenantPermissions($user, [
            'shipments.create',
            'shipments.update_draft',
            'rates.read',
            'quotes.read',
            'quotes.manage',
        ], 'canonical_pricing_actor');

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function createReadyForRatesShipment(User $user): array
    {
        KycVerification::query()->create([
            'account_id' => $user->account_id,
            'status' => KycVerification::STATUS_APPROVED,
            'verification_type' => 'account',
            'verification_level' => 'enhanced',
            'submitted_at' => now(),
            'reviewed_at' => now(),
        ]);

        $shipment = $this->postJson('/api/v1/shipments', $this->shipmentPayload(), $this->authHeaders($user))
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->json('data');

        $this->postJson('/api/v1/shipments/' . $shipment['id'] . '/validate', [], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.status', 'ready_for_rates');

        return $shipment;
    }

    /**
     * @return array<string, mixed>
     */
    private function shipmentPayload(): array
    {
        return [
            'sender_name' => 'Sender',
            'sender_phone' => '+966500000001',
            'sender_address_1' => 'Origin Street',
            'sender_city' => 'Riyadh',
            'sender_postal_code' => '12211',
            'sender_country' => 'SA',
            'recipient_name' => 'Recipient',
            'recipient_phone' => '+12025550123',
            'recipient_address_1' => 'Destination Street',
            'recipient_city' => 'Dubai',
            'recipient_postal_code' => '00000',
            'recipient_country' => 'AE',
            'parcels' => [[
                'weight' => 2.0,
                'length' => 25,
                'width' => 20,
                'height' => 15,
            ]],
        ];
    }
}
