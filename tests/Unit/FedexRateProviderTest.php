<?php

namespace Tests\Unit;

use App\Services\Carriers\FedexRateProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FedexRateProviderTest extends TestCase
{
    public function test_oauth_requests_use_base_url_derived_endpoint_even_if_legacy_oauth_config_differs(): void
    {
        $this->configureFedex();
        config()->set('services.fedex.oauth_url', 'https://legacy.invalid/oauth/token');

        Http::preventStrayRequests();
        Http::fake($this->fedexFakeResponses());

        /** @var FedexRateProvider $provider */
        $provider = app(FedexRateProvider::class);

        $provider->fetchNetRates($this->fedexContext());

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://apis-sandbox.fedex.com/oauth/token');
    }

    public function test_mocked_fedex_availability_and_rate_parsing_produces_normalized_offers(): void
    {
        $this->configureFedex();
        Http::preventStrayRequests();
        Http::fake($this->fedexFakeResponses());

        /** @var FedexRateProvider $provider */
        $provider = app(FedexRateProvider::class);

        $availability = $provider->fetchServiceAvailability($this->fedexContext());
        $rates = $provider->fetchNetRates($this->fedexContext());
        $offers = $provider->mergeAvailabilityAndRates(
            $availability['services'],
            $availability['alerts'],
            $rates['offers'],
            $rates['alerts'],
        );

        $this->assertCount(1, $offers);
        $this->assertSame('fedex', $offers[0]['carrier_code']);
        $this->assertSame('FedEx', $offers[0]['carrier_name']);
        $this->assertSame('INTERNATIONAL_PRIORITY', $offers[0]['service_code']);
        $this->assertSame('FedEx International Priority', $offers[0]['service_name']);
        $this->assertTrue($offers[0]['is_available']);
        $this->assertSame(300.00, $offers[0]['net_rate']);
        $this->assertSame(45.15, $offers[0]['fuel_surcharge']);
        $this->assertSame(0.00, $offers[0]['other_surcharges']);
        $this->assertSame(345.15, $offers[0]['total_net_rate']);
        $this->assertSame('USD', $offers[0]['currency']);
        $this->assertSame(3, $offers[0]['estimated_days_min']);
        $this->assertSame(3, $offers[0]['estimated_days_max']);
        $this->assertSame('2026-03-15T18:00:00Z', $offers[0]['estimated_delivery_at']);
        $this->assertTrue($offers[0]['virtualized_response']);
        $this->assertNotEmpty($offers[0]['carrier_alerts']);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://apis-sandbox.fedex.com/availability/v1/packageandserviceoptions') {
                return false;
            }

            return data_get($request->data(), 'requestedShipment.recipient') === null
                && data_get($request->data(), 'requestedShipment.recipients.0.address.countryCode') === 'US'
                && data_get($request->data(), 'requestedShipment.recipients.0.address.stateOrProvinceCode') === 'NY'
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
    }

    public function test_virtualized_response_alert_is_tolerated_gracefully(): void
    {
        $this->configureFedex();
        Http::preventStrayRequests();
        Http::fake($this->fedexFakeResponses());

        /** @var FedexRateProvider $provider */
        $provider = app(FedexRateProvider::class);

        $rates = $provider->fetchNetRates($this->fedexContext());

        $this->assertCount(1, $rates['offers']);
        $this->assertTrue($rates['offers'][0]['virtualized_response']);
        $this->assertTrue(collect($rates['alerts'])->contains(
            fn (array $alert): bool => ($alert['code'] ?? null) === 'VIRTUAL.RESPONSE'
        ));
    }

    private function configureFedex(): void
    {
        config()->set('features.carrier_fedex', true);
        config()->set('services.fedex.client_id', 'fedex-test-client');
        config()->set('services.fedex.client_secret', 'fedex-test-secret');
        config()->set('services.fedex.account_number', '123456789');
        config()->set('services.fedex.base_url', 'https://apis-sandbox.fedex.com');
        config()->set('services.fedex.oauth_url', 'https://apis-sandbox.fedex.com/oauth/token');
        config()->set('services.fedex.locale', 'en_US');
        config()->set('services.fedex.carrier_codes', ['FDXE']);
    }

    /**
     * @return array<string, mixed>
     */
    private function fedexContext(): array
    {
        return [
            'carrier_code' => 'fedex',
            'currency' => 'USD',
            'is_international' => true,
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
                'packaging_type' => 'custom',
            ]],
            'total_weight' => 1.5,
            'chargeable_weight' => 1.5,
            'system_of_measure_type' => 'METRIC',
        ];
    }

    /**
     * @return array<string, \Closure>
     */
    private function fedexFakeResponses(): array
    {
        return [
            'https://apis-sandbox.fedex.com/oauth/token' => fn () => Http::response([
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
