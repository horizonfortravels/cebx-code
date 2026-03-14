<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\KycVerification;
use App\Models\PricingRule;
use App\Models\RateOption;
use App\Models\RateQuote;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShipmentOfferApiTest extends TestCase
{
    public function test_user_can_list_offers_for_own_shipment(): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createShipmentActor();
        $shipment = $this->createReadyForRatesShipment($user);
        $this->createRetailPricingRule((string) $user->account_id);

        $quote = $this->fetchRatesForShipment($user, (string) $shipment['id'], 'dhl_express');

        $response = $this->getJson('/api/v1/shipments/' . $shipment['id'] . '/offers', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.shipment_id', (string) $shipment['id'])
            ->assertJsonPath('data.shipment_status', Shipment::STATUS_RATED)
            ->assertJsonPath('data.rate_quote_id', (string) $quote->id)
            ->assertJsonPath('data.quote_status', RateQuote::STATUS_COMPLETED)
            ->assertJsonPath('data.offers.0.carrier_code', 'dhl_express')
            ->assertJsonPath('data.offers.0.currency', 'SAR')
            ->assertJsonPath('data.offers.0.is_selected', false);

        $this->assertNotSame('', (string) data_get(
            $response->json(),
            'data.offers.0.service_code',
            ''
        ));
    }

    public function test_user_can_select_one_valid_offer_and_persist_linkage(): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createShipmentActor();
        $shipment = $this->createReadyForRatesShipment($user);
        $this->createRetailPricingRule((string) $user->account_id);
        $quote = $this->fetchRatesForShipment($user, (string) $shipment['id'], 'dhl_express');
        $option = RateOption::query()->where('rate_quote_id', (string) $quote->id)->orderBy('retail_rate')->firstOrFail();

        $this->postJson('/api/v1/rate-quotes/' . $quote->id . '/select', [
            'option_id' => (string) $option->id,
        ], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.id', (string) $quote->id)
            ->assertJsonPath('data.status', RateQuote::STATUS_SELECTED)
            ->assertJsonPath('data.selected_option_id', (string) $option->id);

        $shipment = Shipment::query()->findOrFail($shipment['id']);

        $this->assertSame(Shipment::STATUS_DECLARATION_REQUIRED, (string) $shipment->status);
        $this->assertSame((string) $quote->id, (string) $shipment->rate_quote_id);
        $this->assertSame((string) $option->id, (string) $shipment->selected_rate_option_id);

        $this->getJson('/api/v1/shipments/' . $shipment->id . '/offers', $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.selected_rate_option_id', (string) $option->id)
            ->assertJsonPath('data.offers.0.is_selected', true);
    }

    public function test_cannot_select_another_tenants_offer(): void
    {
        config()->set('features.carrier_fedex', false);

        $accountA = Account::factory()->organization()->create([
            'name' => 'Offer API Org A ' . Str::upper(Str::random(4)),
            'status' => 'active',
        ]);
        $accountB = Account::factory()->organization()->create([
            'name' => 'Offer API Org B ' . Str::upper(Str::random(4)),
            'status' => 'active',
        ]);

        $userA = $this->createShipmentActorForAccount($accountA);
        $userB = $this->createShipmentActorForAccount($accountB);

        $this->assertNotSame((string) $accountA->id, (string) $accountB->id);
        $this->assertNotSame((string) $userA->account_id, (string) $userB->account_id);

        $shipmentB = $this->createReadyForRatesShipment($userB);
        $this->createRetailPricingRule((string) $userB->account_id);
        $quoteB = $this->fetchRatesForShipment($userB, (string) $shipmentB['id'], 'dhl_express');
        $optionB = RateOption::query()->where('rate_quote_id', (string) $quoteB->id)->orderBy('retail_rate')->firstOrFail();

        $this->assertSame((string) $userB->account_id, (string) $quoteB->account_id);
        $this->assertNotSame((string) $userA->account_id, (string) $quoteB->account_id);

        Sanctum::actingAs($userA);

        $this->postJson('/api/v1/rate-quotes/' . $quoteB->id . '/select', [
            'option_id' => (string) $optionB->id,
        ])->assertNotFound();
    }

    public function test_invalid_option_quote_combination_returns_clear_business_error(): void
    {
        config()->set('features.carrier_fedex', false);

        $user = $this->createShipmentActor();
        $this->createRetailPricingRule((string) $user->account_id);

        $shipmentA = $this->createReadyForRatesShipment($user);
        $shipmentB = $this->createReadyForRatesShipment($user);

        $quoteA = $this->fetchRatesForShipment($user, (string) $shipmentA['id'], 'dhl_express');
        $quoteB = $this->fetchRatesForShipment($user, (string) $shipmentB['id'], 'aramex');
        $optionFromQuoteB = RateOption::query()->where('rate_quote_id', (string) $quoteB->id)->orderBy('retail_rate')->firstOrFail();

        $this->postJson('/api/v1/rate-quotes/' . $quoteA->id . '/select', [
            'option_id' => (string) $optionFromQuoteB->id,
        ], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'ERR_OPTION_NOT_IN_QUOTE');
    }

    private function createRetailPricingRule(string $accountId): void
    {
        PricingRule::factory()->create([
            'account_id' => $accountId,
            'is_active' => true,
            'is_default' => true,
            'markup_type' => 'fixed',
            'markup_fixed' => 5,
            'markup_percentage' => 0,
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
            'name' => 'Offer API Org ' . Str::upper(Str::random(4)),
            'status' => 'active',
        ]);

        return $this->createShipmentActorForAccount($account);
    }

    private function createShipmentActorForAccount(Account $account): User
    {
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
        ], 'shipment_offer_actor');

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
            ->assertJsonPath('data.status', Shipment::STATUS_DRAFT)
            ->json('data');

        $this->postJson('/api/v1/shipments/' . $shipment['id'] . '/validate', [], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.status', Shipment::STATUS_READY_FOR_RATES);

        return $shipment;
    }

    private function fetchRatesForShipment(User $user, string $shipmentId, string $carrier): RateQuote
    {
        $quoteId = (string) $this->postJson(
            '/api/v1/shipments/' . $shipmentId . '/rates?carrier=' . $carrier,
            [],
            $this->authHeaders($user)
        )
            ->assertOk()
            ->assertJsonPath('data.status', RateQuote::STATUS_COMPLETED)
            ->json('data.id');

        return RateQuote::query()->findOrFail($quoteId);
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
