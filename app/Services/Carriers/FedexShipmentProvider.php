<?php

namespace App\Services\Carriers;

use App\Exceptions\BusinessException;
use App\Services\Carriers\Contracts\CarrierShipmentProvider;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FedexShipmentProvider implements CarrierShipmentProvider
{
    private ?string $accessToken = null;

    public function carrierCode(): string
    {
        return 'fedex';
    }

    public function isEnabled(): bool
    {
        return (bool) config('features.carrier_fedex', false)
            && filled((string) config('services.fedex.client_id'))
            && filled((string) config('services.fedex.client_secret'))
            && filled((string) config('services.fedex.account_number'));
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function createShipment(array $context): array
    {
        $this->ensureConfigured();

        $correlationId = trim((string) ($context['correlation_id'] ?? ''));
        if ($correlationId === '') {
            $correlationId = (string) Str::uuid();
        }

        $payload = $this->buildCreateShipmentPayload($context);
        $response = $this->postFedexJson('/ship/v1/shipments', $payload, $correlationId);
        $body = $response->json() ?? [];

        return $this->normalizeCreateShipmentResponse($payload, is_array($body) ? $body : [], $context, $correlationId);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildCreateShipmentPayload(array $context): array
    {
        return [
            'accountNumber' => [
                'value' => (string) config('services.fedex.account_number'),
            ],
            'labelResponseOptions' => 'LABEL',
            'requestedShipment' => [
                'shipDatestamp' => now()->toDateString(),
                'pickupType' => 'USE_SCHEDULED_PICKUP',
                'serviceType' => (string) ($context['service_code'] ?? ''),
                'packagingType' => $this->resolvePackagingType($context),
                'shippingChargesPayment' => [
                    'paymentType' => 'SENDER',
                    'payor' => [
                        'responsibleParty' => [
                            'accountNumber' => [
                                'value' => (string) config('services.fedex.account_number'),
                            ],
                            'address' => [
                                'countryCode' => (string) ($context['sender_country'] ?? ''),
                            ],
                        ],
                    ],
                ],
                'shipper' => $this->fedexParty($context, 'sender', true),
                'recipients' => [
                    $this->fedexParty($context, 'recipient'),
                ],
                'totalWeight' => (float) ($context['total_weight'] ?? $context['chargeable_weight'] ?? 1),
                'labelSpecification' => [
                    'imageType' => $this->mapImageType((string) ($context['label_format'] ?? 'pdf')),
                    'labelStockType' => $this->mapLabelStockType((string) ($context['label_size'] ?? '4x6')),
                    'labelFormatType' => 'COMMON2D',
                ],
                'requestedPackageLineItems' => $this->requestedPackageLineItems($context),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $responseBody
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function normalizeCreateShipmentResponse(array $payload, array $responseBody, array $context, string $correlationId): array
    {
        $transaction = (array) data_get($responseBody, 'output.transactionShipments.0', []);
        $completedDetail = (array) data_get($transaction, 'completedShipmentDetail', []);
        $pieceResponse = (array) data_get($transaction, 'pieceResponses.0', []);
        $packageDocuments = collect((array) data_get($transaction, 'pieceResponses', []))
            ->flatMap(static fn (array $piece): array => array_values((array) ($piece['packageDocuments'] ?? [])))
            ->values()
            ->all();

        $trackingNumber = trim((string) (
            data_get($pieceResponse, 'trackingNumber')
            ?? data_get($transaction, 'masterTrackingNumber')
            ?? data_get($completedDetail, 'masterTrackingNumber')
            ?? ''
        ));

        $carrierShipmentId = trim((string) (
            data_get($transaction, 'masterTrackingNumber')
            ?? data_get($completedDetail, 'masterTrackingNumber')
            ?? data_get($responseBody, 'output.jobId')
            ?? $trackingNumber
        ));

        if ($carrierShipmentId === '' && $trackingNumber === '') {
            throw new BusinessException(
                'FedEx shipment response did not include a shipment reference or tracking number.',
                'ERR_FEDEX_SHIP_INVALID_RESPONSE',
                502,
                [
                    'carrier_code' => 'fedex',
                    'http_status' => 502,
                    'endpoint_url' => rtrim((string) config('services.fedex.base_url'), '/') . '/ship/v1/shipments',
                    'method' => 'POST',
                    'request_payload' => $payload,
                    'response_body' => $responseBody,
                ]
            );
        }

        $alerts = array_merge(
            $this->extractAlerts($responseBody),
            $this->extractAlerts($transaction),
            $this->extractAlerts($pieceResponse),
        );

        $providerCarrierCode = strtoupper((string) (
            data_get($completedDetail, 'carrierCode')
            ?? data_get($pieceResponse, 'carrierCode')
            ?? 'FDXE'
        ));

        return [
            'carrier_code' => 'fedex',
            'carrier_name' => 'FedEx',
            'carrier_shipment_id' => $carrierShipmentId !== '' ? $carrierShipmentId : $trackingNumber,
            'tracking_number' => $trackingNumber !== '' ? $trackingNumber : null,
            'awb_number' => $trackingNumber !== '' ? $trackingNumber : null,
            'service_code' => (string) (
                data_get($transaction, 'serviceType')
                ?? $context['service_code']
                ?? ''
            ),
            'service_name' => (string) (
                data_get($transaction, 'serviceName')
                ?? $context['service_name']
                ?? data_get($transaction, 'serviceType')
                ?? ''
            ),
            'initial_carrier_status' => $trackingNumber !== '' ? 'created' : 'pending',
            'request_payload' => $payload,
            'response_payload' => $responseBody,
            'correlation_id' => $correlationId,
            'carrier_metadata' => [
                'provider' => 'fedex',
                'provider_carrier_code' => $providerCarrierCode,
                'alerts' => $alerts,
                'virtualized_response' => $this->hasVirtualizedResponseAlert($responseBody, $transaction, $pieceResponse),
                'job_id' => data_get($responseBody, 'output.jobId'),
                'transaction_id' => data_get($responseBody, 'transactionId'),
                'shipment_documents' => data_get($transaction, 'shipmentDocuments', []),
                'package_documents' => $packageDocuments,
                'piece_alerts' => data_get($pieceResponse, 'alerts', []),
                'request_contract' => 'ship/v1/shipments',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function fedexParty(array $context, string $prefix, bool $includeAccountNumber = false): array
    {
        $party = [
            'address' => $this->fedexAddress($context, $prefix),
            'contact' => [
                'personName' => (string) ($context["{$prefix}_name"] ?? ucfirst($prefix)),
                'phoneNumber' => (string) ($context["{$prefix}_phone"] ?? '+0000000000'),
                'companyName' => (string) ($context["{$prefix}_company_name"] ?? $context["{$prefix}_company"] ?? $context["{$prefix}_name"] ?? ucfirst($prefix)),
            ],
        ];

        $email = trim((string) ($context["{$prefix}_email"] ?? ''));
        if ($email !== '') {
            $party['contact']['emailAddress'] = $email;
        }

        if ($includeAccountNumber) {
            $party['accountNumber'] = [
                'value' => (string) config('services.fedex.account_number'),
            ];
        }

        return $party;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function fedexAddress(array $context, string $prefix): array
    {
        $cityFallback = $prefix === 'sender' ? 'origin_city' : 'destination_city';
        $countryFallback = $prefix === 'sender' ? 'origin_country' : 'destination_country';
        $stateFallback = $prefix === 'sender' ? 'origin_state' : 'destination_state';

        $address = [
            'streetLines' => array_values(array_filter([
                (string) ($context["{$prefix}_address_1"] ?? ''),
                (string) ($context["{$prefix}_address_2"] ?? ''),
            ])),
            'city' => (string) ($context["{$prefix}_city"] ?? $context[$cityFallback] ?? ''),
            'postalCode' => (string) ($context["{$prefix}_postal_code"] ?? ''),
            'countryCode' => (string) ($context["{$prefix}_country"] ?? $context[$countryFallback] ?? ''),
            'residential' => false,
        ];

        $state = trim((string) ($context["{$prefix}_state"] ?? $context[$stateFallback] ?? ''));
        if ($state !== '') {
            $address['stateOrProvinceCode'] = $state;
        }

        return $address;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<int, array<string, mixed>>
     */
    private function requestedPackageLineItems(array $context): array
    {
        $parcels = is_array($context['parcels'] ?? null) ? $context['parcels'] : [];

        if ($parcels === []) {
            $parcels = [[
                'weight' => (float) ($context['total_weight'] ?? 1),
                'length' => null,
                'width' => null,
                'height' => null,
            ]];
        }

        return array_map(function (array $parcel, int $index): array {
            $item = [
                'sequenceNumber' => $index + 1,
                'groupPackageCount' => 1,
                'weight' => [
                    'units' => $this->weightUnits(),
                    'value' => (float) ($parcel['weight'] ?? 1),
                ],
            ];

            $length = $parcel['length'] ?? null;
            $width = $parcel['width'] ?? null;
            $height = $parcel['height'] ?? null;
            if ($length && $width && $height) {
                $item['dimensions'] = [
                    'length' => (int) round((float) $length),
                    'width' => (int) round((float) $width),
                    'height' => (int) round((float) $height),
                    'units' => $this->dimensionUnits(),
                ];
            }

            return $item;
        }, $parcels, array_keys($parcels));
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $correlationId): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->accessToken(),
            'Content-Type' => 'application/json',
            'X-locale' => (string) config('services.fedex.locale', 'en_US'),
            'x-customer-transaction-id' => $correlationId,
        ];
    }

    private function accessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $response = Http::timeout((int) config('services.fedex.timeout', 20))
            ->asForm()
            ->post((string) config('services.fedex.oauth_url'), [
                'grant_type' => 'client_credentials',
                'client_id' => (string) config('services.fedex.client_id'),
                'client_secret' => (string) config('services.fedex.client_secret'),
            ]);

        if ($response->failed()) {
            throw $this->fedexFailure('FedEx authentication failed.', $response, null, null);
        }

        $token = trim((string) data_get($response->json(), 'access_token'));
        if ($token === '') {
            throw new BusinessException(
                'FedEx authentication succeeded without returning an access token.',
                'ERR_FEDEX_AUTH_INVALID',
                502,
                [
                    'carrier_code' => 'fedex',
                    'http_status' => 502,
                    'endpoint_url' => (string) config('services.fedex.oauth_url'),
                    'method' => 'POST',
                    'response_body' => $response->json(),
                ]
            );
        }

        return $this->accessToken = $token;
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function postFedexJson(string $path, array $payload, string $correlationId): Response
    {
        $url = rtrim((string) config('services.fedex.base_url'), '/') . $path;

        $response = Http::timeout((int) config('services.fedex.timeout', 20))
            ->withHeaders($this->headers($correlationId))
            ->post($url, $payload);

        if ($response->failed()) {
            throw $this->fedexFailure('FedEx shipment creation failed.', $response, $payload, $url);
        }

        return $response;
    }

    private function ensureConfigured(): void
    {
        if ($this->isEnabled()) {
            return;
        }

        throw new BusinessException(
            'FedEx shipment creation is not enabled for this environment.',
            'ERR_FEDEX_NOT_ENABLED',
            503,
            [
                'carrier_code' => 'fedex',
            ]
        );
    }

    private function resolvePackagingType(array $context): string
    {
        $parcels = is_array($context['parcels'] ?? null) ? $context['parcels'] : [];
        $firstPackagingType = strtoupper(trim((string) ($parcels[0]['packaging_type'] ?? '')));

        return match ($firstPackagingType) {
            'BOX', 'CUSTOM', '' => 'YOUR_PACKAGING',
            'ENVELOPE' => 'FEDEX_ENVELOPE',
            'TUBE' => 'FEDEX_TUBE',
            default => $firstPackagingType,
        };
    }

    private function mapImageType(string $labelFormat): string
    {
        return match (strtolower(trim($labelFormat))) {
            'zpl' => 'ZPLII',
            'png' => 'PNG',
            'epl' => 'EPL2',
            default => 'PDF',
        };
    }

    private function mapLabelStockType(string $labelSize): string
    {
        return match (strtolower(trim($labelSize))) {
            '4x8' => 'PAPER_4X8',
            'a4' => 'PAPER_LETTER',
            'a5' => 'PAPER_4X6',
            default => 'PAPER_4X6',
        };
    }

    private function weightUnits(): string
    {
        return 'KG';
    }

    private function dimensionUnits(): string
    {
        return 'CM';
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<int, array<string, mixed>>
     */
    private function extractAlerts(?array $payload): array
    {
        $alerts = [];
        foreach ((array) data_get($payload, 'alerts', []) as $alert) {
            $code = trim((string) data_get($alert, 'code'));
            if ($code === '') {
                continue;
            }

            $alerts[] = [
                'code' => $code,
                'message' => (string) data_get($alert, 'message', ''),
                'alert_type' => (string) data_get($alert, 'alertType', ''),
            ];
        }

        return $alerts;
    }

    /**
     * @param array<string, mixed> ...$payloads
     */
    private function hasVirtualizedResponseAlert(array ...$payloads): bool
    {
        foreach ($payloads as $payload) {
            foreach ($this->extractAlerts($payload) as $alert) {
                if (($alert['code'] ?? null) === 'VIRTUAL.RESPONSE') {
                    return true;
                }
            }
        }

        return false;
    }

    private function fedexFailure(
        string $message,
        Response $response,
        ?array $requestPayload,
        ?string $url
    ): BusinessException {
        $responseBody = $response->json();
        $carrierErrorCode = trim((string) (
            data_get($responseBody, 'errors.0.code')
            ?? data_get($responseBody, 'alerts.0.code')
            ?? ''
        ));
        $carrierErrorMessage = trim((string) (
            data_get($responseBody, 'errors.0.message')
            ?? data_get($responseBody, 'alerts.0.message')
            ?? $message
        ));

        return new BusinessException(
            $carrierErrorMessage === '' ? $message : $carrierErrorMessage,
            'ERR_FEDEX_SHIP_FAILED',
            $response->status() > 0 ? $response->status() : 502,
            [
                'carrier_code' => 'fedex',
                'carrier_error_code' => $carrierErrorCode !== '' ? $carrierErrorCode : null,
                'carrier_error_message' => $carrierErrorMessage !== '' ? $carrierErrorMessage : $message,
                'http_status' => $response->status() > 0 ? $response->status() : 502,
                'endpoint_url' => $url,
                'method' => 'POST',
                'request_payload' => $requestPayload,
                'response_body' => $responseBody,
            ]
        );
    }
}
