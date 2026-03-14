<?php

namespace App\Services\Carriers;

use App\Services\Contracts\CarrierInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AramexCarrierAdapter — D-1: Real Carrier Adapter (Aramex Pilot)
 *
 * Production-ready Aramex adapter implementing CarrierInterface.
 * Uses Aramex SOAP/REST API v1 for shipment operations.
 *
 * Guards:
 *   - Feature flag: config('features.carrier_aramex') must be true
 *   - EnvironmentSafetyGuard middleware on routes
 *   - All responses marked _live=true when hitting real API
 *
 * Config (config/services.php → aramex):
 *   ARAMEX_USERNAME, ARAMEX_PASSWORD, ARAMEX_ACCOUNT_NUMBER,
 *   ARAMEX_ACCOUNT_PIN, ARAMEX_ACCOUNT_ENTITY, ARAMEX_ACCOUNT_COUNTRY_CODE
 *
 * Registration: Add to CarrierAdapterFactory::$adapters:
 *   'aramex' => \App\Services\Carriers\AramexCarrierAdapter::class,
 *
 * Does NOT modify existing CarrierService, CarrierRateAdapter, or DummyCarrierAdapter.
 */
class AramexCarrierAdapter implements CarrierInterface
{
    private string $baseUrl;
    private array  $credentials;
    private string $correlationId;

    public function __construct()
    {
        $config = config('services.aramex', []);

        $this->baseUrl = env('ARAMEX_BASE_URL', 'https://ws.aramex.net/ShippingAPI.V2');
        $this->credentials = [
            'UserName'    => $config['username'] ?? '',
            'Password'    => $config['password'] ?? '',
            'Version'     => 'v1',
            'AccountNumber'    => $config['account_number'] ?? '',
            'AccountPin'       => $config['account_pin'] ?? '',
            'AccountEntity'    => env('ARAMEX_ACCOUNT_ENTITY', 'RUH'),
            'AccountCountryCode' => env('ARAMEX_ACCOUNT_COUNTRY_CODE', 'SA'),
            'Source'           => null,
        ];
        $this->correlationId = Str::uuid()->toString();
    }

    public function code(): string
    {
        return 'aramex';
    }

    public function name(): string
    {
        return 'Aramex';
    }

    public function isEnabled(): bool
    {
        return (bool) config('features.carrier_aramex', false);
    }

    // ═════════════════════════════════════════════════════════
    // CREATE SHIPMENT
    // ═════════════════════════════════════════════════════════

    public function createShipment(array $payload): array
    {
        if (!$this->guardEnabled('createShipment')) {
            return $this->disabledResponse('createShipment');
        }

        $correlationId = $this->correlationId;

        try {
            $body = [
                'ClientInfo' => $this->credentials,
                'LabelInfo'  => [
                    'ReportID'   => 9201,
                    'ReportType' => 'URL',
                ],
                'Shipments'  => [[
                    'Reference1' => $payload['reference'] ?? '',
                    'Reference2' => $correlationId,
                    'Shipper'    => [
                        'Reference1'   => $payload['sender_name'] ?? '',
                        'AccountNumber' => $this->credentials['AccountNumber'],
                        'PartyAddress' => [
                            'Line1'       => $payload['sender_address'] ?? $payload['sender_address_1'] ?? '',
                            'City'        => $payload['sender_city'] ?? '',
                            'CountryCode' => $payload['sender_country'] ?? 'SA',
                            'PostCode'    => $payload['sender_postal_code'] ?? '',
                        ],
                        'Contact' => [
                            'Department'     => '',
                            'PersonName'     => $payload['sender_name'] ?? '',
                            'Title'          => '',
                            'CompanyName'    => $payload['sender_company'] ?? $payload['sender_name'] ?? '',
                            'PhoneNumber1'   => $payload['sender_phone'] ?? '',
                            'CellPhone'      => $payload['sender_phone'] ?? '',
                            'EmailAddress'   => $payload['sender_email'] ?? '',
                        ],
                    ],
                    'Consignee' => [
                        'Reference1'   => $payload['recipient_name'] ?? '',
                        'AccountNumber' => '',
                        'PartyAddress' => [
                            'Line1'       => $payload['recipient_address'] ?? $payload['recipient_address_1'] ?? '',
                            'City'        => $payload['recipient_city'] ?? '',
                            'CountryCode' => $payload['recipient_country'] ?? 'SA',
                            'PostCode'    => $payload['recipient_postal_code'] ?? '',
                        ],
                        'Contact' => [
                            'Department'     => '',
                            'PersonName'     => $payload['recipient_name'] ?? '',
                            'Title'          => '',
                            'CompanyName'    => $payload['recipient_company'] ?? $payload['recipient_name'] ?? '',
                            'PhoneNumber1'   => $payload['recipient_phone'] ?? '',
                            'CellPhone'      => $payload['recipient_phone'] ?? '',
                            'EmailAddress'   => $payload['recipient_email'] ?? '',
                        ],
                    ],
                    'ThirdParty' => [
                        'Reference1'    => '',
                        'AccountNumber' => '',
                        'PartyAddress'  => ['Line1' => '', 'City' => '', 'CountryCode' => ''],
                        'Contact'       => ['PersonName' => '', 'CompanyName' => '', 'PhoneNumber1' => ''],
                    ],
                    'ShippingDateTime'  => now()->addHour()->format('/\D\a\t\e(U)/')  ,
                    'DueDate'           => now()->addDays(3)->format('/\D\a\t\e(U)/'),
                    'Comments'          => $payload['description'] ?? '',
                    'PickupLocation'    => '',
                    'OperationsInstructions' => '',
                    'AccountingInstrcutions' => '',
                    'Details' => [
                        'Dimensions' => [
                            'Length' => $payload['length'] ?? 0,
                            'Width'  => $payload['width'] ?? 0,
                            'Height' => $payload['height'] ?? 0,
                            'Unit'   => 'CM',
                        ],
                        'ActualWeight' => [
                            'Unit'  => 'KG',
                            'Value' => $payload['weight'] ?? 1,
                        ],
                        'ChargeableWeight' => null,
                        'DescriptionOfGoods' => $payload['description'] ?? 'شحنة',
                        'GoodsOriginCountry' => $payload['sender_country'] ?? 'SA',
                        'NumberOfPieces'     => $payload['pieces'] ?? 1,
                        'ProductGroup'       => $this->isInternational($payload) ? 'EXP' : 'DOM',
                        'ProductType'        => $this->isInternational($payload) ? 'PPX' : 'ONP',
                        'PaymentType'        => 'P', // Prepaid
                        'PaymentOptions'     => '',
                        'Items'              => [],
                    ],
                ]],
                'Transaction' => [
                    'Reference1' => $correlationId,
                    'Reference2' => '',
                    'Reference3' => '',
                    'Reference4' => '',
                    'Reference5' => '',
                ],
            ];

            $this->logIntegration('aramex.createShipment.request', $correlationId, [
                'sender_city'    => $payload['sender_city'] ?? '',
                'recipient_city' => $payload['recipient_city'] ?? '',
                'weight'         => $payload['weight'] ?? 0,
            ]);

            $response = Http::timeout(30)
                ->post("{$this->baseUrl}/Shipping/Service_1_0.svc/json/CreateShipments", $body);

            $data = $response->json();

            // Check for Aramex errors
            if (!$response->successful() || !empty($data['Notifications'] ?? []) || !empty($data['HasErrors'])) {
                $error = $this->extractError($data);
                $this->logIntegration('aramex.createShipment.error', $correlationId, [
                    'status' => $response->status(),
                    'error'  => $error,
                ]);

                return [
                    'success'         => false,
                    'shipment_id'     => null,
                    'tracking_number' => null,
                    'label_url'       => null,
                    'label_content'   => null,
                    'error'           => $error,
                    '_live'           => true,
                    '_correlation_id' => $correlationId,
                ];
            }

            $shipment = $data['Shipments'][0] ?? [];
            $trackingNumber = $shipment['ID'] ?? null;
            $labelUrl = $shipment['ShipmentLabel']['LabelURL'] ?? null;

            $this->logIntegration('aramex.createShipment.success', $correlationId, [
                'tracking_number' => $trackingNumber,
                'has_label'       => !empty($labelUrl),
            ]);

            return [
                'success'          => true,
                'shipment_id'      => $trackingNumber,
                'tracking_number'  => $trackingNumber,
                'label_url'        => $labelUrl,
                'label_content'    => $shipment['ShipmentLabel']['LabelFileContents'] ?? null,
                'error'            => null,
                '_live'            => true,
                '_correlation_id'  => $correlationId,
            ];

        } catch (\Throwable $e) {
            $this->logIntegration('aramex.createShipment.exception', $correlationId, [
                'error' => $e->getMessage(),
            ], 'error');

            return [
                'success'         => false,
                'shipment_id'     => null,
                'tracking_number' => null,
                'label_url'       => null,
                'label_content'   => null,
                'error'           => 'Aramex API error: ' . $e->getMessage(),
                '_live'           => true,
                '_correlation_id' => $correlationId,
            ];
        }
    }

    // ═════════════════════════════════════════════════════════
    // TRACK
    // ═════════════════════════════════════════════════════════

    public function track(string $trackingNumber): array
    {
        if (!$this->guardEnabled('track')) {
            return $this->disabledResponse('track');
        }

        $correlationId = Str::uuid()->toString();

        try {
            $body = [
                'ClientInfo'       => $this->credentials,
                'GetLastTrackingUpdateOnly' => false,
                'Shipments'        => [$trackingNumber],
                'Transaction'      => ['Reference1' => $correlationId],
            ];

            $this->logIntegration('aramex.track.request', $correlationId, [
                'tracking_number' => $trackingNumber,
            ]);

            $response = Http::timeout(20)
                ->post("{$this->baseUrl}/Tracking/Service_1_0.svc/json/TrackShipments", $body);

            $data = $response->json();

            if (!$response->successful() || !empty($data['HasErrors'])) {
                $error = $this->extractError($data);
                return [
                    'success' => false,
                    'status'  => 'unknown',
                    'events'  => [],
                    'error'   => $error,
                    '_live'   => true,
                    '_correlation_id' => $correlationId,
                ];
            }

            $results = $data['TrackingResults'][0] ?? [];
            $events = [];
            $latestStatus = 'unknown';

            foreach (($results['Value'] ?? []) as $event) {
                $unifiedStatus = $this->mapAramexStatus($event['UpdateCode'] ?? '');
                $events[] = [
                    'status'      => $unifiedStatus,
                    'description' => $event['UpdateDescription'] ?? '',
                    'location'    => $event['UpdateLocation'] ?? '',
                    'timestamp'   => $event['UpdateDateTime'] ?? '',
                ];
                $latestStatus = $unifiedStatus;
            }

            $this->logIntegration('aramex.track.success', $correlationId, [
                'tracking_number' => $trackingNumber,
                'events_count'    => count($events),
                'latest_status'   => $latestStatus,
            ]);

            return [
                'success' => true,
                'status'  => $latestStatus,
                'events'  => $events,
                'error'   => null,
                '_live'   => true,
                '_correlation_id' => $correlationId,
            ];

        } catch (\Throwable $e) {
            $this->logIntegration('aramex.track.exception', $correlationId, [
                'error' => $e->getMessage(),
            ], 'error');

            return [
                'success' => false,
                'status'  => 'unknown',
                'events'  => [],
                'error'   => 'Aramex tracking error: ' . $e->getMessage(),
                '_live'   => true,
                '_correlation_id' => $correlationId,
            ];
        }
    }

    // ═════════════════════════════════════════════════════════
    // CANCEL
    // ═════════════════════════════════════════════════════════

    public function cancel(string $shipmentId, string $trackingNumber): array
    {
        if (!$this->guardEnabled('cancel')) {
            return $this->disabledResponse('cancel');
        }

        // Aramex does not have a cancel API — shipments must be voided via support
        // We return a controlled response indicating manual intervention needed
        $this->logIntegration('aramex.cancel.manual_required', $this->correlationId, [
            'shipment_id'     => $shipmentId,
            'tracking_number' => $trackingNumber,
        ], 'warning');

        return [
            'success'         => false,
            'cancellation_id' => null,
            'error'           => 'Aramex does not support API cancellation. Contact Aramex support or use the Aramex portal.',
            '_live'           => true,
            '_note'           => 'manual_cancellation_required',
            '_correlation_id' => $this->correlationId,
        ];
    }

    // ═════════════════════════════════════════════════════════
    // GET RATES
    // ═════════════════════════════════════════════════════════

    public function getRates(array $params): array
    {
        if (!$this->guardEnabled('getRates')) {
            return [];
        }

        $correlationId = Str::uuid()->toString();

        try {
            $body = [
                'ClientInfo' => $this->credentials,
                'OriginAddress' => [
                    'City'        => $params['origin_city'] ?? '',
                    'CountryCode' => $params['origin_country'] ?? 'SA',
                ],
                'DestinationAddress' => [
                    'City'        => $params['destination_city'] ?? '',
                    'CountryCode' => $params['destination_country'] ?? 'SA',
                ],
                'ShipmentDetails' => [
                    'PaymentType'       => 'P',
                    'ProductGroup'      => $this->isInternational($params) ? 'EXP' : 'DOM',
                    'ProductType'       => $this->isInternational($params) ? 'PPX' : 'ONP',
                    'ActualWeight'      => ['Unit' => 'KG', 'Value' => $params['weight'] ?? 1],
                    'ChargeableWeight'  => ['Unit' => 'KG', 'Value' => $params['weight'] ?? 1],
                    'NumberOfPieces'    => $params['pieces'] ?? 1,
                ],
                'PreferredCurrencyCode' => $params['currency'] ?? 'SAR',
                'Transaction' => ['Reference1' => $correlationId],
            ];

            $response = Http::timeout(15)
                ->post("{$this->baseUrl}/RateCalculator/Service_1_0.svc/json/CalculateRate", $body);

            $data = $response->json();

            if (!$response->successful() || !empty($data['HasErrors'])) {
                return [];
            }

            $total = $data['TotalAmount']['Value'] ?? 0;

            return [[
                'service_code'   => $this->isInternational($params) ? 'PPX' : 'ONP',
                'service_name'   => $this->isInternational($params) ? 'Aramex Priority Express' : 'Aramex Domestic',
                'net_rate'       => (float) $total,
                'currency'       => $data['TotalAmount']['CurrencyCode'] ?? 'SAR',
                'estimated_days' => $this->isInternational($params) ? '3-6' : '1-3',
                '_live'          => true,
            ]];

        } catch (\Throwable $e) {
            $this->logIntegration('aramex.getRates.exception', $correlationId, [
                'error' => $e->getMessage(),
            ], 'error');
            return [];
        }
    }

    // ═════════════════════════════════════════════════════════
    // GET LABEL
    // ═════════════════════════════════════════════════════════

    public function getLabel(string $shipmentId, string $format = 'pdf'): array
    {
        if (!$this->guardEnabled('getLabel')) {
            return $this->disabledResponse('getLabel');
        }

        $correlationId = Str::uuid()->toString();

        try {
            $body = [
                'ClientInfo' => $this->credentials,
                'LabelInfo'  => [
                    'ReportID'   => 9201,
                    'ReportType' => 'URL',
                ],
                'ShipmentNumber' => $shipmentId,
                'Transaction'    => ['Reference1' => $correlationId],
            ];

            $response = Http::timeout(20)
                ->post("{$this->baseUrl}/Shipping/Service_1_0.svc/json/PrintLabel", $body);

            $data = $response->json();

            if (!$response->successful() || !empty($data['HasErrors'])) {
                return [
                    'success' => false,
                    'content' => null,
                    'format'  => $format,
                    'error'   => $this->extractError($data),
                    '_live'   => true,
                ];
            }

            $labelUrl = $data['ShipmentLabel']['LabelURL'] ?? null;

            return [
                'success' => true,
                'content' => $data['ShipmentLabel']['LabelFileContents'] ?? null,
                'format'  => $format,
                'url'     => $labelUrl,
                'error'   => null,
                '_live'   => true,
                '_correlation_id' => $correlationId,
            ];

        } catch (\Throwable $e) {
            $this->logIntegration('aramex.getLabel.exception', $correlationId, [
                'error' => $e->getMessage(),
            ], 'error');

            return [
                'success' => false,
                'content' => null,
                'format'  => $format,
                'error'   => 'Aramex label error: ' . $e->getMessage(),
                '_live'   => true,
            ];
        }
    }

    // ═════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═════════════════════════════════════════════════════════

    private function guardEnabled(string $operation): bool
    {
        if (!$this->isEnabled()) {
            Log::channel('integration')->info("Aramex.{$operation}: blocked — feature flag disabled");
            return false;
        }

        // Environment guard: block real API outside production unless sandbox flag is on
        if (!app()->environment('production') && !config('features.sandbox_mode', false)) {
            Log::channel('integration')->info("Aramex.{$operation}: blocked — non-production + sandbox_mode off");
            return false;
        }

        // Verify credentials present
        if (empty($this->credentials['UserName']) || empty($this->credentials['Password'])) {
            Log::channel('integration')->warning("Aramex.{$operation}: blocked — missing credentials");
            return false;
        }

        return true;
    }

    private function isInternational(array $params): bool
    {
        $origin = $params['sender_country'] ?? $params['origin_country'] ?? 'SA';
        $dest   = $params['recipient_country'] ?? $params['destination_country'] ?? 'SA';

        return strtoupper($origin) !== strtoupper($dest);
    }

    private function mapAramexStatus(string $code): string
    {
        return match ($code) {
            'IN'           => 'processing',
            'OUT'          => 'shipped',
            'SH003', 'SH005', 'SH006', 'SH008' => 'in_transit',
            'SH009', 'SH010', 'SH014' => 'out_for_delivery',
            'SH011', 'DL'  => 'delivered',
            'RT'           => 'returned',
            'SH007'        => 'exception',
            default        => 'in_transit',
        };
    }

    private function extractError(array $data): string
    {
        $notifications = $data['Notifications'] ?? [];

        if (!empty($notifications)) {
            $messages = array_map(fn($n) => ($n['Code'] ?? '') . ': ' . ($n['Message'] ?? ''), $notifications);
            return implode('; ', $messages);
        }

        return $data['error'] ?? $data['message'] ?? 'Unknown Aramex error';
    }

    private function logIntegration(string $event, string $correlationId, array $context, string $level = 'info'): void
    {
        $logData = array_merge($context, [
            'correlation_id' => $correlationId,
            'carrier'        => 'aramex',
            'timestamp'      => now()->toIso8601String(),
        ]);

        // Use integration channel if available, fallback to default
        try {
            Log::channel('integration')->{$level}($event, $logData);
        } catch (\Throwable) {
            Log::$level($event, $logData);
        }
    }

    private function disabledResponse(string $operation): array
    {
        return [
            'success'  => false,
            'error'    => "Aramex carrier is disabled (operation: {$operation}). Enable via feature flag.",
            '_live'    => false,
            '_disabled' => true,
        ];
    }
}
