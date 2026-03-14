<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\KycVerification;
use App\Models\PricingBreakdown;
use App\Models\PricingRule;
use App\Models\RateOption;
use App\Models\RateQuote;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShipmentRetailPricingPersistenceTest extends TestCase
{
    public function test_fixed_markup_is_applied_and_persisted_durably(): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createShipmentActor();
        $shipment = $this->createReadyForRatesShipment($user);

        PricingRule::factory()->create([
            'account_id' => $user->account_id,
            'is_active' => true,
            'is_default' => true,
            'markup_type' => 'fixed',
            'markup_fixed' => 12.00,
            'markup_percentage' => 0,
            'service_fee_fixed' => 0,
            'service_fee_percentage' => 0,
            'min_profit' => 0,
            'min_retail_price' => 0,
            'rounding_mode' => 'none',
            'rounding_precision' => 1,
        ]);

        $option = $this->fetchFirstOption($user, (string) $shipment['id'], 'dhl_express');
        $breakdown = PricingBreakdown::query()->findOrFail($option->pricing_breakdown_id);

        $this->assertSame('retail', data_get($option->pricing_breakdown, 'stage'));
        $this->assertSame('12.00', (string) $option->markup_amount);
        $this->assertSame('0.00', (string) $option->service_fee);
        $this->assertSame('61.46', (string) $option->retail_rate);
        $this->assertSame((string) $shipment['id'], (string) $breakdown->shipment_id);
        $this->assertSame((string) $option->rate_quote_id, (string) $breakdown->rate_quote_id);
        $this->assertSame((string) $option->id, (string) $breakdown->rate_option_id);
        $this->assertSame('shipment_quote', $breakdown->pricing_path);
    }

    public function test_percentage_markup_moves_shipment_quote_to_retail_stage(): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createShipmentActor();
        $shipment = $this->createReadyForRatesShipment($user);

        PricingRule::factory()->create([
            'account_id' => $user->account_id,
            'is_active' => true,
            'is_default' => true,
            'markup_type' => 'percentage',
            'markup_percentage' => 10,
            'markup_fixed' => 0,
            'service_fee_fixed' => 0,
            'service_fee_percentage' => 0,
            'min_profit' => 0,
            'min_retail_price' => 0,
            'rounding_mode' => 'none',
            'rounding_precision' => 1,
        ]);

        $option = $this->fetchFirstOption($user, (string) $shipment['id'], 'dhl_express');

        $this->assertSame('retail', data_get($option->pricing_breakdown, 'stage'));
        $this->assertSame('4.95', (string) $option->markup_amount);
        $this->assertSame('54.41', (string) $option->retail_rate);
        $this->assertNotNull($option->pricing_breakdown_id);
    }

    public function test_service_fee_is_applied_and_persisted(): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createShipmentActor();
        $shipment = $this->createReadyForRatesShipment($user);

        PricingRule::factory()->create([
            'account_id' => $user->account_id,
            'is_active' => true,
            'is_default' => true,
            'markup_type' => 'fixed',
            'markup_fixed' => 0,
            'markup_percentage' => 0,
            'service_fee_fixed' => 5,
            'service_fee_percentage' => 0,
            'min_profit' => 0,
            'min_retail_price' => 0,
            'rounding_mode' => 'none',
            'rounding_precision' => 1,
        ]);

        $option = $this->fetchFirstOption($user, (string) $shipment['id'], 'dhl_express');
        $breakdown = PricingBreakdown::query()->findOrFail($option->pricing_breakdown_id);

        $this->assertSame('0.00', (string) $option->markup_amount);
        $this->assertSame('5.00', (string) $option->service_fee);
        $this->assertSame('54.46', (string) $option->retail_rate);
        $this->assertSame('5.00', (string) $breakdown->service_fee);
        $this->assertSame('0.00', (string) $breakdown->rounding_adjustment);
    }

    public function test_rounding_adjustment_is_persisted_durably(): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createShipmentActor();
        $shipment = $this->createReadyForRatesShipment($user);

        PricingRule::factory()->create([
            'account_id' => $user->account_id,
            'is_active' => true,
            'is_default' => true,
            'markup_type' => 'fixed',
            'markup_fixed' => 0.51,
            'markup_percentage' => 0,
            'service_fee_fixed' => 0,
            'service_fee_percentage' => 0,
            'min_profit' => 0,
            'min_retail_price' => 0,
            'rounding_mode' => 'ceil',
            'rounding_precision' => 1,
        ]);

        $option = $this->fetchFirstOption($user, (string) $shipment['id'], 'dhl_express');
        $breakdown = PricingBreakdown::query()->findOrFail($option->pricing_breakdown_id);

        $this->assertSame('50.00', (string) $option->retail_rate);
        $this->assertSame('49.97', (string) $option->retail_rate_before_rounding);
        $this->assertSame('0.03', (string) $breakdown->rounding_adjustment);
        $this->assertSame('ceil/1.00', (string) $breakdown->rounding_policy);
    }

    public function test_minimum_charge_adjustment_is_persisted_durably(): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createShipmentActor();
        $shipment = $this->createReadyForRatesShipment($user);

        PricingRule::factory()->create([
            'account_id' => $user->account_id,
            'is_active' => true,
            'is_default' => true,
            'markup_type' => 'fixed',
            'markup_fixed' => 0,
            'markup_percentage' => 0,
            'service_fee_fixed' => 0,
            'service_fee_percentage' => 0,
            'min_profit' => 0,
            'min_retail_price' => 60,
            'rounding_mode' => 'none',
            'rounding_precision' => 1,
        ]);

        $option = $this->fetchFirstOption($user, (string) $shipment['id'], 'dhl_express');
        $breakdown = PricingBreakdown::query()->findOrFail($option->pricing_breakdown_id);

        $this->assertSame('60.00', (string) $option->retail_rate);
        $this->assertSame('43.20', (string) $breakdown->carrier_net_rate);
        $this->assertSame('49.46', (string) $breakdown->net_rate);
        $this->assertSame('10.54', (string) $breakdown->minimum_charge_adjustment);
        $this->assertSame('60.00', (string) $breakdown->pre_rounding_total);
        $this->assertSame('retail', (string) $breakdown->pricing_stage);
    }

    private function createShipmentActor(): User
    {
        $account = Account::factory()->organization()->create([
            'name' => 'Retail Pricing Org ' . Str::upper(Str::random(4)),
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
        ], 'shipment_retail_pricing_actor');

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
            ->json('data');

        $this->postJson('/api/v1/shipments/' . $shipment['id'] . '/validate', [], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.status', 'ready_for_rates');

        return $shipment;
    }

    private function fetchFirstOption(User $user, string $shipmentId, string $carrier): RateOption
    {
        $quoteId = (string) $this->postJson(
            '/api/v1/shipments/' . $shipmentId . '/rates?carrier=' . $carrier,
            [],
            $this->authHeaders($user)
        )
            ->assertOk()
            ->assertJsonPath('data.status', RateQuote::STATUS_COMPLETED)
            ->json('data.id');

        return RateOption::query()
            ->where('rate_quote_id', $quoteId)
            ->orderBy('created_at')
            ->firstOrFail();
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
            'recipient_phone' => '+971501234567',
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
