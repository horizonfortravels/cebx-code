<?php

namespace App\Services\Carriers;

use App\Exceptions\BusinessException;
use App\Models\FeatureFlag;
use App\Services\Carriers\Contracts\CarrierRateProvider;
use App\Support\FedexEndpointResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FedexRateProvider implements CarrierRateProvider
{
    private ?string $accessToken = null;

    public function carrierCode(): string
    {
        return 'fedex';
    }

    public function isEnabled(): bool
    {
        return FeatureFlag::runtimeEnabled('carrier_fedex')
            && filled((string) config('services.fedex.client_id'))
            && filled((string) config('services.fedex.client_secret'))
            && filled((string) config('services.fedex.account_number'));
    }

    public function fetchServiceAvailability(array $context): array
    {
        $this->ensureConfigured();

        $packageResponse = $this->postFedexJson(
            '/availability/v1/packageandserviceoptions',
            $this->buildPackageAndServiceOptionsPayload($context)
        );

        $transitResponse = $this->postFedexJson(
            '/availability/v1/transittimes',
            $this->buildTransitTimesPayload($context)
        );

        $services = $this->parsePackageAndServiceOptions($packageResponse->json());
        $transitTimes = $this->parseTransitTimes($transitResponse->json());

        $merged = [];

        foreach ($services as $serviceCode => $service) {
            $merged[$serviceCode] = array_merge($service, $transitTimes[$serviceCode] ?? []);
        }

        foreach ($transitTimes as $serviceCode => $transit) {
            $merged[$serviceCode] = array_merge([
                'carrier_code' => 'fedex',
                'carrier_name' => 'FedEx',
                'service_code' => $serviceCode,
                'service_name' => $transit['service_name'] ?? $serviceCode,
                'is_available' => true,
                'unavailable_reason' => null,
            ], $merged[$serviceCode] ?? [], $transit);
        }

        return [
            'services' => array_values($merged),
            'alerts' => array_merge(
                $this->extractAlerts($packageResponse->json()),
                $this->extractAlerts($transitResponse->json()),
            ),
        ];
    }

    public function fetchNetRates(array $context): array
    {
        $this->ensureConfigured();

        $rateResponse = $this->postFedexJson(
            '/rate/v1/rates/quotes',
            $this->buildRatePayload($context)
        );

        return [
            'offers' => $this->parseRateReplyDetails($rateResponse->json()),
            'alerts' => $this->extractAlerts($rateResponse->json()),
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildPackageAndServiceOptionsPayload(array $context): array
    {
        return [
            'accountNumber' => [
                'value' => (string) config('services.fedex.account_number'),
            ],
            'systemOfMeasureType' => $this->systemOfMeasureType($context),
            'carrierCodes' => $this->requestedCarrierCodes($context),
            'requestedShipment' => [
                'shipper' => $this->fedexParty($context, 'sender'),
                'recipients' => [
                    $this->fedexParty($context, 'recipient'),
                ],
                'packagingType' => $this->resolvePackagingType($context),
                'requestedPackageLineItems' => $this->requestedPackageLineItems($context),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildTransitTimesPayload(array $context): array
    {
        return [
            'carrierCodes' => $this->requestedCarrierCodes($context),
            'requestedShipment' => [
                'shipper' => [
                    'address' => $this->fedexAddress($context, 'sender'),
                ],
                'recipients' => [[
                    'address' => $this->fedexAddress($context, 'recipient'),
                ]],
                'packagingType' => $this->resolvePackagingType($context),
                'requestedPackageLineItems' => $this->requestedPackageLineItems($context),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildRatePayload(array $context): array
    {
        return [
            'accountNumber' => [
                'value' => (string) config('services.fedex.account_number'),
            ],
            'carrierCodes' => $this->requestedCarrierCodes($context),
            'requestedShipment' => [
                'shipper' => $this->fedexParty($context, 'sender', true),
                'recipient' => $this->fedexParty($context, 'recipient'),
                'pickupType' => 'USE_SCHEDULED_PICKUP',
                'rateRequestType' => ['ACCOUNT'],
                'preferredCurrency' => (string) ($context['currency'] ?? 'USD'),
                'shipDateStamp' => now()->toDateString(),
                'packagingType' => $this->resolvePackagingType($context),
                'requestedPackageLineItems' => $this->requestedPackageLineItems($context),
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
                'companyName' => (string) (
                    $context["{$prefix}_company_name"]
                    ?? $context["{$prefix}_company"]
                    ?? $context["{$prefix}_name"]
                    ?? ucfirst($prefix)
                ),
            ],
        ];

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
            'city' => (string) ($context["{$prefix}_city"] ?? $context[$cityFallback] ?? ''),
            'countryCode' => (string) ($context["{$prefix}_country"] ?? $context[$countryFallback] ?? ''),
            'postalCode' => (string) ($context["{$prefix}_postal_code"] ?? ''),
            'streetLines' => array_values(array_filter([
                (string) ($context["{$prefix}_address_1"] ?? ''),
                (string) ($context["{$prefix}_address_2"] ?? ''),
            ])),
        ];

        $state = (string) ($context["{$prefix}_state"] ?? $context[$stateFallback] ?? '');
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

        return array_map(function (array $parcel): array {
            $item = [
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
        }, $parcels);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<int, string>
     */
    private function requestedCarrierCodes(array $context): array
    {
        $configured = array_values(array_filter(array_map(
            static fn ($code): string => trim((string) $code),
            (array) config('services.fedex.carrier_codes', [])
        )));

        if ($configured !== []) {
            return $configured;
        }

        return !empty($context['is_international']) ? ['FDXE'] : ['FDXE', 'FDXG'];
    }

    /**
     * @param array<string, mixed> $context
     */
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

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->accessToken(),
            'Content-Type' => 'application/json',
            'X-locale' => (string) config('services.fedex.locale', 'en_US'),
            'x-customer-transaction-id' => (string) Str::uuid(),
        ];
    }

    private function accessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $response = Http::timeout((int) config('services.fedex.timeout', 20))
            ->asForm()
            ->post(FedexEndpointResolver::oauthUrl(), [
                'grant_type' => 'client_credentials',
                'client_id' => (string) config('services.fedex.client_id'),
                'client_secret' => (string) config('services.fedex.client_secret'),
            ]);

        if ($response->failed()) {
            throw $this->fedexFailure('ERR_FEDEX_AUTH_FAILED', $response, 'FedEx authentication failed.');
        }

        $token = trim((string) data_get($response->json(), 'access_token'));
        if ($token === '') {
            throw new BusinessException(
                'FedEx authentication succeeded without returning an access token.',
                'ERR_FEDEX_AUTH_INVALID',
                502
            );
        }

        return $this->accessToken = $token;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postFedexJson(string $path, array $payload): Response
    {
        $response = Http::timeout((int) config('services.fedex.timeout', 20))
            ->withHeaders($this->headers())
            ->post(rtrim((string) config('services.fedex.base_url'), '/') . $path, $payload);

        if ($response->failed()) {
            throw $this->fedexFailure('ERR_FEDEX_REQUEST_FAILED', $response, 'FedEx request failed.');
        }

        return $response;
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, array<string, mixed>>
     */
    private function parsePackageAndServiceOptions(?array $payload): array
    {
        $services = [];

        foreach ((array) data_get($payload, 'output.serviceOptions', []) as $service) {
            $serviceCode = (string) data_get($service, 'key', '');
            if ($serviceCode === '') {
                continue;
            }

            $services[$serviceCode] = [
                'carrier_code' => 'fedex',
                'carrier_name' => 'FedEx',
                'service_code' => $serviceCode,
                'service_name' => (string) data_get($service, 'displayText', $serviceCode),
                'is_available' => true,
                'unavailable_reason' => null,
            ];
        }

        foreach ((array) data_get($payload, 'output.packageOptions', []) as $packageOption) {
            foreach ((array) data_get($packageOption, 'serviceOptions', []) as $service) {
                $serviceCode = (string) data_get($service, 'key', '');
                if ($serviceCode === '') {
                    continue;
                }

                $services[$serviceCode] = [
                    'carrier_code' => 'fedex',
                    'carrier_name' => 'FedEx',
                    'service_code' => $serviceCode,
                    'service_name' => (string) data_get($service, 'displayText', $serviceCode),
                    'is_available' => true,
                    'unavailable_reason' => null,
                ];
            }
        }

        foreach ((array) data_get($payload, 'output.serviceOptionsList', []) as $service) {
            $serviceCode = (string) data_get($service, 'serviceType', '');
            if ($serviceCode === '') {
                continue;
            }

            $services[$serviceCode] = [
                'carrier_code' => 'fedex',
                'carrier_name' => 'FedEx',
                'service_code' => $serviceCode,
                'service_name' => (string) data_get($service, 'serviceName', $serviceCode),
                'is_available' => true,
                'unavailable_reason' => null,
            ];
        }

        return $services;
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, array<string, mixed>>
     */
    private function parseTransitTimes(?array $payload): array
    {
        $transitByService = [];

        foreach ((array) data_get($payload, 'output.transitTimes', []) as $transit) {
            foreach ((array) data_get($transit, 'transitTimeDetails', []) as $detail) {
                $serviceCode = (string) data_get($detail, 'serviceType', '');
                if ($serviceCode === '') {
                    continue;
                }

                $transitByService[$serviceCode] = [
                    'service_code' => $serviceCode,
                    'service_name' => (string) data_get($detail, 'serviceName', $serviceCode),
                    'estimated_delivery_at' => $this->resolveEstimatedDelivery(
                        data_get($detail, 'commit')
                    ),
                    'estimated_days_min' => $this->resolveTransitDays(data_get($detail, 'commit'), data_get($detail, 'commit.transitTime')),
                    'estimated_days_max' => $this->resolveTransitDays(data_get($detail, 'commit'), data_get($detail, 'commit.transitTime')),
                ];
            }
        }

        return $transitByService;
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<int, array<string, mixed>>
     */
    private function parseRateReplyDetails(?array $payload): array
    {
        $offers = [];

        foreach ((array) data_get($payload, 'output.rateReplyDetails', []) as $detail) {
            $serviceCode = (string) data_get($detail, 'serviceType', '');
            if ($serviceCode === '') {
                continue;
            }

            $ratedDetail = $this->selectAccountRatedShipmentDetail((array) data_get($detail, 'ratedShipmentDetails', []));
            $shipmentRateDetail = (array) data_get($ratedDetail, 'shipmentRateDetail', []);
            $surcharges = (array) data_get($shipmentRateDetail, 'surCharges', []);

            $fuelSurcharge = 0.0;
            $otherSurcharges = 0.0;

            foreach ($surcharges as $surcharge) {
                $amount = $this->moneyAmount(data_get($surcharge, 'amount'));
                if (strtoupper((string) data_get($surcharge, 'type')) === 'FUEL') {
                    $fuelSurcharge += $amount;
                    continue;
                }

                $otherSurcharges += $amount;
            }

            $totalNetCharge = $this->moneyAmount(data_get($ratedDetail, 'totalNetCharge'));
            $baseCharge = $this->moneyAmount(data_get($ratedDetail, 'totalBaseCharge'));
            if ($baseCharge <= 0 && $totalNetCharge > 0) {
                $baseCharge = max(0.0, $totalNetCharge - $fuelSurcharge - $otherSurcharges);
            }

            $estimatedDeliveryAt = $this->resolveEstimatedDelivery(
                data_get($detail, 'operationalDetail'),
                data_get($detail, 'commit')
            );
            $estimatedDays = $this->resolveTransitDays(
                data_get($detail, 'commit'),
                data_get($detail, 'operationalDetail.transitTime')
                    ?? data_get($detail, 'commit.transitTime')
            );

            $offers[] = [
                'carrier_code' => 'fedex',
                'carrier_name' => 'FedEx',
                'service_code' => $serviceCode,
                'service_name' => (string) data_get($detail, 'serviceName', $serviceCode),
                'net_rate' => $baseCharge,
                'fuel_surcharge' => round($fuelSurcharge, 2),
                'other_surcharges' => round($otherSurcharges, 2),
                'total_net_rate' => round($totalNetCharge, 2),
                'currency' => (string) (
                    data_get($shipmentRateDetail, 'currency')
                    ?? data_get($ratedDetail, 'currency')
                    ?? 'USD'
                ),
                'estimated_delivery_at' => $estimatedDeliveryAt,
                'estimated_days_min' => $estimatedDays,
                'estimated_days_max' => $estimatedDays,
                'is_available' => true,
                'unavailable_reason' => null,
                'pricing_stage' => 'net_only',
                'virtualized_response' => $this->hasVirtualizedResponseAlert($payload),
            ];
        }

        return $offers;
    }

    /**
     * @param array<int, array<string, mixed>> $availabilityAlerts
     * @param array<int, array<string, mixed>> $rateAlerts
     * @param array<int, array<string, mixed>> $offers
     * @param array<int, array<string, mixed>> $services
     * @return array<int, array<string, mixed>>
     */
    public function mergeAvailabilityAndRates(array $services, array $availabilityAlerts, array $offers, array $rateAlerts): array
    {
        $availabilityByService = [];
        foreach ($services as $service) {
            $serviceCode = (string) ($service['service_code'] ?? '');
            if ($serviceCode === '') {
                continue;
            }

            $availabilityByService[$serviceCode] = $service;
        }

        return array_map(function (array $offer) use ($availabilityByService, $availabilityAlerts, $rateAlerts): array {
            $serviceCode = (string) ($offer['service_code'] ?? '');
            $availability = $availabilityByService[$serviceCode] ?? null;

            $offer['service_name'] = (string) ($offer['service_name'] ?: ($availability['service_name'] ?? $serviceCode));
            $offer['is_available'] = (bool) ($availability['is_available'] ?? $offer['is_available'] ?? true);
            $offer['unavailable_reason'] = $availability['unavailable_reason'] ?? $offer['unavailable_reason'] ?? null;
            $offer['estimated_delivery_at'] = $offer['estimated_delivery_at'] ?? ($availability['estimated_delivery_at'] ?? null);
            $offer['estimated_days_min'] = $offer['estimated_days_min'] ?? ($availability['estimated_days_min'] ?? null);
            $offer['estimated_days_max'] = $offer['estimated_days_max'] ?? ($availability['estimated_days_max'] ?? null);
            $offer['carrier_alerts'] = array_values(array_map(static fn (array $alert): array => [
                'code' => (string) ($alert['code'] ?? ''),
                'message' => (string) ($alert['message'] ?? ''),
                'alert_type' => (string) ($alert['alertType'] ?? ''),
            ], array_merge($availabilityAlerts, $rateAlerts)));

            return $offer;
        }, $offers);
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<int, array<string, mixed>>
     */
    private function extractAlerts(?array $payload): array
    {
        $alerts = [];

        foreach ((array) data_get($payload, 'alerts', []) as $alert) {
            $alerts[] = (array) $alert;
        }

        foreach ((array) data_get($payload, 'output.alerts', []) as $alert) {
            $alerts[] = (array) $alert;
        }

        foreach ((array) data_get($payload, 'output.packageOptions', []) as $option) {
            foreach ((array) data_get($option, 'alerts', []) as $alert) {
                $alerts[] = (array) $alert;
            }
        }

        foreach ((array) data_get($payload, 'output.transitTimes', []) as $transit) {
            foreach ((array) data_get($transit, 'alerts', []) as $alert) {
                $alerts[] = (array) $alert;
            }
        }

        return $alerts;
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function hasVirtualizedResponseAlert(?array $payload): bool
    {
        foreach ($this->extractAlerts($payload) as $alert) {
            if (strtoupper((string) ($alert['code'] ?? '')) === 'VIRTUAL.RESPONSE') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $ratedShipmentDetails
     * @return array<string, mixed>
     */
    private function selectAccountRatedShipmentDetail(array $ratedShipmentDetails): array
    {
        foreach ($ratedShipmentDetails as $detail) {
            if (strtoupper((string) data_get($detail, 'rateType')) === 'ACCOUNT') {
                return (array) $detail;
            }
        }

        return (array) ($ratedShipmentDetails[0] ?? []);
    }

    /**
     * @param mixed ...$candidates
     */
    private function resolveEstimatedDelivery(...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $commitDate = data_get($candidate, 'commitDate')
                ?? data_get($candidate, 'dateDetail');

            if (is_string($commitDate) && trim($commitDate) !== '') {
                return $commitDate;
            }
        }

        return null;
    }

    /**
     * @param mixed $commit
     * @param mixed $transitTime
     */
    private function resolveTransitDays($commit, $transitTime): ?int
    {
        $days = data_get($commit, 'transitDays');
        if (is_numeric($days)) {
            return (int) $days;
        }

        $value = strtoupper((string) $transitTime);
        if (preg_match('/(\\d+)/', $value, $matches) === 1) {
            return (int) $matches[1];
        }

        $map = [
            'ONE_DAY' => 1,
            'TWO_DAYS' => 2,
            'THREE_DAYS' => 3,
            'FOUR_DAYS' => 4,
            'FIVE_DAYS' => 5,
        ];

        return $map[$value] ?? null;
    }

    /**
     * @param mixed $value
     */
    private function moneyAmount($value): float
    {
        if (is_array($value)) {
            $value = $value['amount'] ?? 0;
        }

        return round((float) $value, 2);
    }

    private function systemOfMeasureType(array $context): string
    {
        return strtoupper((string) ($context['system_of_measure_type'] ?? 'METRIC'));
    }

    private function weightUnits(): string
    {
        return 'KG';
    }

    private function dimensionUnits(): string
    {
        return 'CM';
    }

    private function ensureConfigured(): void
    {
        if (!$this->isEnabled()) {
            throw new BusinessException(
                'FedEx is not enabled or fully configured for the platform environment.',
                'ERR_FEDEX_NOT_CONFIGURED',
                503
            );
        }
    }

    private function fedexFailure(string $errorCode, Response $response, string $fallbackMessage): BusinessException
    {
        $message = (string) data_get($response->json(), 'errors.0.message', $fallbackMessage);
        $providerCode = (string) data_get($response->json(), 'errors.0.code', '');

        return new BusinessException(
            $message,
            $errorCode,
            $response->status() >= 500 ? 502 : $response->status(),
            array_filter([
                'carrier' => 'fedex',
                'provider_code' => $providerCode !== '' ? $providerCode : null,
                'status' => $response->status(),
            ])
        );
    }
}
