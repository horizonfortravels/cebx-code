<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\KycVerification;
use App\Models\PricingBreakdown;
use App\Models\PricingRule;
use App\Models\RateOption;
use App\Models\RateQuote;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class FedexRateFetchApiTest extends TestCase
{
    public function test_shipment_not_ready_for_rates_cannot_fetch_fedex_rates(): void
    {
        $this->configureFedex();
        Http::preventStrayRequests();
        Http::fake();

        $user = $this->createShipmentActor();
        $shipment = $this->createDraftShipment($user);

        $this->postJson('/api/v1/shipments/' . $shipment['id'] . '/rates?carrier=fedex', [], $this->authHeaders($user))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'ERR_INVALID_STATE_FOR_RATES');

        Http::assertNothingSent();
    }

    public function test_ready_for_rates_shipment_fetches_normalized_fedex_offers_without_live_dependency(): void
    {
        $this->configureFedex();
        Http::preventStrayRequests();
        Http::fake($this->fedexFakeResponses());

        $user = $this->createShipmentActor();
        $shipment = $this->createReadyForRatesShipment($user);

        $response = $this->postJson('/api/v1/shipments/' . $shipment['id'] . '/rates?carrier=fedex', [], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.status', RateQuote::STATUS_COMPLETED)
            ->assertJsonPath('data.options_count', 1)
            ->assertJsonPath('data.options.0.carrier_code', 'fedex')
            ->assertJsonPath('data.options.0.service_code', 'INTERNATIONAL_PRIORITY')
            ->assertJsonPath('data.options.0.service_name', 'FedEx International Priority')
            ->assertJsonPath('data.options.0.estimated_days_min', 3);

        $quoteId = (string) $response->json('data.id');
        $option = RateOption::query()->where('rate_quote_id', $quoteId)->firstOrFail();

        $this->assertSame('fedex', $option->carrier_code);
        $this->assertSame('FedEx', $option->carrier_name);
        $this->assertSame('INTERNATIONAL_PRIORITY', $option->service_code);
        $this->assertSame('345.15', (string) $option->total_net_rate);
        $this->assertSame('345.15', (string) $option->retail_rate);
        $this->assertSame('345.15', (string) $option->retail_rate_before_rounding);
        $this->assertSame('0.00', (string) $option->markup_amount);
        $this->assertSame('0.00', (string) $option->service_fee);
        $this->assertSame('USD', $option->currency);
        $this->assertTrue((bool) $option->is_available);
        $this->assertSame('net_only', data_get($option->pricing_breakdown, 'stage'));
        $this->assertSame(\App\Services\PricingEngineService::class, data_get($option->rule_evaluation_log, 'canonical_engine'));
        $this->assertSame('shipment_quote', data_get($option->rule_evaluation_log, 'pricing_path'));
        $this->assertTrue((bool) data_get($option->rule_evaluation_log, 'virtualized_response'));

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://apis-sandbox.fedex.com/availability/v1/packageandserviceoptions') {
                return false;
            }

            return data_get($request->data(), 'requestedShipment.recipient') === null
                && data_get($request->data(), 'requestedShipment.recipients.0.address.countryCode') === 'US'
                && data_get($request->data(), 'requestedShipment.recipients.0.address.city') === 'New York'
                && data_get($request->data(), 'requestedShipment.packagingType') === 'YOUR_PACKAGING';
        });

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://apis-sandbox.fedex.com/rate/v1/rates/quotes') {
                return false;
            }

            return data_get($request->data(), 'requestedShipment.rateRequestType.0') === 'ACCOUNT'
                && data_get($request->data(), 'requestedShipment.rateRequestTypes') === null
                && data_get($request->data(), 'requestedShipment.shipper.address.countryCode') === 'SA'
                && data_get($request->data(), 'requestedShipment.recipient.address.countryCode') === 'US'
                && data_get($request->data(), 'requestedShipment.packagingType') === 'YOUR_PACKAGING';
        });

        Http::assertSentCount(4);
    }

    public function test_ready_for_rates_shipment_uses_retail_stage_when_account_pricing_rule_exists(): void
    {
        $this->configureFedex();
        Http::preventStrayRequests();
        Http::fake($this->fedexFakeResponses());

        $user = $this->createShipmentActor();
        $shipment = $this->createReadyForRatesShipment($user);

        PricingRule::factory()->create([
            'account_id' => $user->account_id,
            'is_active' => true,
            'is_default' => true,
            'markup_type' => 'fixed',
            'markup_fixed' => 10,
            'markup_percentage' => 0,
            'service_fee_fixed' => 2,
            'service_fee_percentage' => 0,
            'min_profit' => 0,
            'min_retail_price' => 0,
            'rounding_mode' => 'none',
            'rounding_precision' => 1,
        ]);

        $response = $this->postJson('/api/v1/shipments/' . $shipment['id'] . '/rates?carrier=fedex', [], $this->authHeaders($user))
            ->assertOk()
            ->assertJsonPath('data.status', RateQuote::STATUS_COMPLETED)
            ->assertJsonPath('data.options_count', 1)
            ->assertJsonPath('data.options.0.rule_evaluation_log.pricing_stage', 'retail');

        $option = RateOption::query()
            ->where('rate_quote_id', (string) $response->json('data.id'))
            ->firstOrFail();

        $breakdown = PricingBreakdown::query()->findOrFail($option->pricing_breakdown_id);

        $this->assertSame('retail', data_get($option->pricing_breakdown, 'stage'));
        $this->assertSame('357.15', (string) $option->retail_rate);
        $this->assertSame('10.00', (string) $option->markup_amount);
        $this->assertSame('2.00', (string) $option->service_fee);
        $this->assertSame('retail', (string) $breakdown->pricing_stage);
        $this->assertSame((string) $option->id, (string) $breakdown->rate_option_id);
        $this->assertSame('shipment_quote', (string) $breakdown->pricing_path);

        Http::assertSentCount(4);
    }

    private function configureFedex(): void
    {
        config()->set('features.carrier_fedex', true);
        config()->set('services.fedex.client_id', 'fedex-test-client');
        config()->set('services.fedex.client_secret', 'fedex-test-secret');
        config()->set('services.fedex.account_number', '123456789');
        config()->set('services.fedex.base_url', 'https://apis-sandbox.fedex.com');
        config()->set('services.fedex.oauth_url', 'https://apis-base.test.cloud.fedex.com/oauth/token');
        config()->set('services.fedex.locale', 'en_US');
        config()->set('services.fedex.carrier_codes', ['FDXE']);
    }

    private function createShipmentActor(): User
    {
        $account = Account::factory()->organization()->create([
            'name' => 'FedEx Rates Org ' . Str::upper(Str::random(4)),
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
        ], 'fedex_rate_actor');

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function createDraftShipment(User $user): array
    {
        $response = $this->postJson('/api/v1/shipments', $this->shipmentPayload(), $this->authHeaders($user))
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        return $response->json('data');
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

        $shipment = $this->createDraftShipment($user);

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
            'recipient_city' => 'New York',
            'recipient_postal_code' => '10001',
            'recipient_country' => 'US',
            'recipient_state' => 'NY',
            'parcels' => [[
                'weight' => 1.5,
                'length' => 20,
                'width' => 15,
                'height' => 10,
            ]],
        ];
    }

    /**
     * @return array<string, \Closure>
     */
    private function fedexFakeResponses(): array
    {
        return [
            'https://apis-base.test.cloud.fedex.com/oauth/token' => fn () => Http::response([
                'access_token' => 'fedex-access-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://apis-sandbox.fedex.com/availability/v1/packageandserviceoptions' => fn () => Http::response([
                'output' => [
                    'serviceOptions' => [
                        ['key' => 'INTERNATIONAL_PRIORITY', 'displayText' => 'FedEx International Priority'],
                    ],
                    'alerts' => [[
                        'code' => 'VIRTUAL.RESPONSE',
                        'message' => 'This is a Virtual Response.',
                        'alertType' => 'NOTE',
                    ]],
                ],
            ], 200),
            'https://apis-sandbox.fedex.com/availability/v1/transittimes' => fn () => Http::response([
                'output' => [
                    'transitTimes' => [[
                        'transitTimeDetails' => [[
                            'serviceType' => 'INTERNATIONAL_PRIORITY',
                            'serviceName' => 'FedEx International Priority',
                            'commit' => [
                                'commitDate' => '2026-03-15T18:00:00Z',
                                'transitTime' => 'THREE_DAYS',
                                'transitDays' => 3,
                            ],
                        ]],
                    ]],
                ],
            ], 200),
            'https://apis-sandbox.fedex.com/rate/v1/rates/quotes' => fn () => Http::response([
                'output' => [
                    'alerts' => [[
                        'code' => 'VIRTUAL.RESPONSE',
                        'message' => 'This is a Virtual Response.',
                        'alertType' => 'NOTE',
                    ]],
                    'rateReplyDetails' => [[
                        'serviceType' => 'INTERNATIONAL_PRIORITY',
                        'serviceName' => 'FedEx International Priority',
                        'operationalDetail' => [
                            'commitDate' => '2026-03-15T18:00:00Z',
                            'transitTime' => 'THREE_DAYS',
                        ],
                        'ratedShipmentDetails' => [[
                            'rateType' => 'ACCOUNT',
                            'totalBaseCharge' => 300.00,
                            'totalNetCharge' => 345.15,
                            'shipmentRateDetail' => [
                                'currency' => 'USD',
                                'surCharges' => [[
                                    'type' => 'FUEL',
                                    'amount' => 45.15,
                                ]],
                            ],
                        ]],
                    ]],
                ],
            ], 200),
        ];
    }
}
