<?php

namespace Tests\Unit;

use App\Services\Carriers\FedexShipmentProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FedexShipmentProviderTest extends TestCase
{
    public function test_create_shipment_parses_fedex_response_into_normalized_fields(): void
    {
        $this->configureFedex();

        Http::preventStrayRequests();
        Http::fake($this->fedexFakeResponses($this->shipSuccessBody()));

        /** @var FedexShipmentProvider $provider */
        $provider = app(FedexShipmentProvider::class);

        $result = $provider->createShipment($this->fedexContext([
            'correlation_id' => 'REQ-FDX-SHIP-001',
        ]));

        $this->assertSame('fedex', $result['carrier_code']);
        $this->assertSame('FedEx', $result['carrier_name']);
        $this->assertSame('794699999999', $result['carrier_shipment_id']);
        $this->assertSame('794699999999', $result['tracking_number']);
        $this->assertSame('794699999999', $result['awb_number']);
        $this->assertSame('INTERNATIONAL_PRIORITY', $result['service_code']);
        $this->assertSame('FedEx International Priority', $result['service_name']);
        $this->assertSame('created', $result['initial_carrier_status']);
        $this->assertSame('REQ-FDX-SHIP-001', $result['correlation_id']);
        $this->assertSame('FDXE', data_get($result, 'carrier_metadata.provider_carrier_code'));
        $this->assertFalse((bool) data_get($result, 'carrier_metadata.virtualized_response'));
        $this->assertNotEmpty(data_get($result, 'request_payload.requestedShipment.requestedPackageLineItems'));
        $this->assertNotEmpty(data_get($result, 'response_payload.output.transactionShipments.0.shipmentDocuments'));
        $this->assertNotEmpty(data_get($result, 'carrier_metadata.package_documents'));
        $this->assertSame('LABEL', data_get($result, 'carrier_metadata.package_documents.0.contentType'));
        $this->assertSame('PDF', data_get($result, 'carrier_metadata.package_documents.0.docType'));

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://apis-sandbox.fedex.com/ship/v1/shipments') {
                return false;
            }

            $body = $request->body();

            return $request->hasHeader('x-customer-transaction-id', 'REQ-FDX-SHIP-001')
                && str_contains($body, '"serviceType":"INTERNATIONAL_PRIORITY"')
                && str_contains($body, '"paymentType":"SENDER"')
                && str_contains($body, '"labelResponseOptions":"LABEL"')
                && str_contains($body, '"packagingType":"YOUR_PACKAGING"')
                && str_contains($body, '"totalWeight":1.5')
                && ! str_contains($body, '"totalWeight":{"units"')
                && str_contains($body, '"stateOrProvinceCode":"RI"')
                && str_contains($body, '"stateOrProvinceCode":"NY"');
        });
    }

    public function test_virtualized_response_alert_is_tolerated_for_shipment_creation(): void
    {
        $this->configureFedex();

        Http::preventStrayRequests();
        Http::fake($this->fedexFakeResponses($this->shipSuccessBody(true)));

        /** @var FedexShipmentProvider $provider */
        $provider = app(FedexShipmentProvider::class);

        $result = $provider->createShipment($this->fedexContext());

        $this->assertTrue((bool) data_get($result, 'carrier_metadata.virtualized_response'));
        $this->assertTrue(collect(data_get($result, 'carrier_metadata.alerts', []))->contains(
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
        config()->set('services.fedex.oauth_url', 'https://apis-base.test.cloud.fedex.com/oauth/token');
        config()->set('services.fedex.locale', 'en_US');
        config()->set('services.fedex.carrier_codes', ['FDXE']);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function fedexContext(array $overrides = []): array
    {
        return array_merge([
            'shipment_id' => 'ship-e2-fedex-001',
            'account_id' => 'acct-e2-fedex-001',
            'rate_quote_id' => 'quote-e2-fedex-001',
            'selected_rate_option_id' => 'option-e2-fedex-001',
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
            'service_code' => 'INTERNATIONAL_PRIORITY',
            'service_name' => 'FedEx International Priority',
            'currency' => 'USD',
            'label_format' => 'pdf',
            'label_size' => '4x6',
            'idempotency_key' => 'SHIP-IDEMP-001',
            'sender_name' => 'Sender',
            'sender_company' => 'Sender Co',
            'sender_phone' => '+966500000001',
            'sender_email' => 'sender@example.test',
            'sender_address_1' => 'Origin Street',
            'sender_city' => 'Providence',
            'sender_state' => 'RI',
            'sender_postal_code' => '02903',
            'sender_country' => 'US',
            'recipient_name' => 'Recipient',
            'recipient_company' => 'Recipient Co',
            'recipient_phone' => '+12025550123',
            'recipient_email' => 'recipient@example.test',
            'recipient_address_1' => 'Destination Street',
            'recipient_city' => 'New York',
            'recipient_state' => 'NY',
            'recipient_postal_code' => '10001',
            'recipient_country' => 'US',
            'total_weight' => 1.5,
            'chargeable_weight' => 1.5,
            'parcels' => [[
                'weight' => 1.5,
                'length' => 20,
                'width' => 15,
                'height' => 10,
                'packaging_type' => 'custom',
            ]],
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $shipBody
     * @return array<string, \Closure>
     */
    private function fedexFakeResponses(array $shipBody): array
    {
        return [
            'https://apis-base.test.cloud.fedex.com/oauth/token' => fn () => Http::response([
                'access_token' => 'fedex-access-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://apis-sandbox.fedex.com/ship/v1/shipments' => fn () => Http::response($shipBody, 200),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shipSuccessBody(bool $virtualized = false): array
    {
        $alerts = $virtualized ? [[
            'code' => 'VIRTUAL.RESPONSE',
            'message' => 'This is a Virtual Response.',
            'alertType' => 'NOTE',
        ]] : [];

        return [
            'transactionId' => 'fedex-ship-tx-001',
            'customerTransactionId' => 'REQ-FDX-SHIP-001',
            'output' => [
                'alerts' => $alerts,
                'transactionShipments' => [[
                    'serviceType' => 'INTERNATIONAL_PRIORITY',
                    'serviceName' => 'FedEx International Priority',
                    'masterTrackingNumber' => '794699999999',
                    'alerts' => $alerts,
                    'pieceResponses' => [[
                        'trackingNumber' => '794699999999',
                        'alerts' => $alerts,
                        'packageDocuments' => [[
                            'docType' => 'PDF',
                            'contentType' => 'LABEL',
                            'copiesToPrint' => 1,
                            'encodedLabel' => base64_encode('fake-fedex-package-label'),
                        ]],
                    ]],
                    'completedShipmentDetail' => [
                        'carrierCode' => 'FDXE',
                        'masterTrackingNumber' => '794699999999',
                    ],
                    'shipmentDocuments' => [[
                        'docType' => 'COMMERCIAL_INVOICE',
                        'url' => 'https://sandbox-docs.fedex.test/invoice-001.pdf',
                    ]],
                ]],
            ],
        ];
    }
}
