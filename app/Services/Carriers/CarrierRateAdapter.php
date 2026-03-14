<?php

namespace App\Services\Carriers;

use App\Exceptions\BusinessException;

/**
 * CarrierRateAdapter
 *
 * Orchestrates net-rate fetching across provider implementations.
 * FedEx is the first real provider. Legacy DHL/Aramex branches remain simulated
 * until the rest of the carrier roadmap is implemented.
 */
class CarrierRateAdapter
{
    public function __construct(
        protected FedexRateProvider $fedexProvider,
    ) {}

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function fetchRates(array $params): array
    {
        $carrier = strtolower(trim((string) ($params['carrier_code'] ?? '')));

        if ($carrier === 'fedex') {
            return $this->fetchFedexRates($params);
        }

        if ($carrier === '') {
            if ($this->fedexProvider->isEnabled()) {
                return $this->fetchFedexRates($params);
            }

            return array_merge($this->fetchDhlRates($params), $this->fetchAramexRates($params));
        }

        return match ($carrier) {
            'dhl_express' => $this->fetchDhlRates($params),
            'aramex' => $this->fetchAramexRates($params),
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    private function fetchFedexRates(array $params): array
    {
        if (!$this->fedexProvider->isEnabled()) {
            throw new BusinessException(
                'FedEx rates are not enabled for this environment.',
                'ERR_FEDEX_NOT_ENABLED',
                503
            );
        }

        $availability = $this->fedexProvider->fetchServiceAvailability($params);
        $rates = $this->fedexProvider->fetchNetRates($params);

        return $this->fedexProvider->mergeAvailabilityAndRates(
            $availability['services'],
            $availability['alerts'],
            $rates['offers'],
            $rates['alerts'],
        );
    }

    /**
     * Simulated DHL Express rates.
     *
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    private function fetchDhlRates(array $params): array
    {
        $weight = (float) ($params['chargeable_weight'] ?? $params['total_weight'] ?? 1);
        $isIntl  = ($params['origin_country'] ?? 'SA') !== ($params['destination_country'] ?? 'SA');
        $baseMultiplier = $isIntl ? 18.0 : 8.0;

        $services = [];

        $expressBase = round($weight * $baseMultiplier * 1.2, 2);
        $fuelSurcharge = round($expressBase * 0.145, 2);
        $services[] = [
            'carrier_code' => 'dhl_express',
            'carrier_name' => 'DHL Express',
            'service_code' => 'express_worldwide',
            'service_name' => $isIntl ? 'DHL Express Worldwide' : 'DHL Express Domestic',
            'net_rate' => $expressBase,
            'fuel_surcharge' => $fuelSurcharge,
            'other_surcharges' => 0,
            'total_net_rate' => round($expressBase + $fuelSurcharge, 2),
            'estimated_days_min' => $isIntl ? 2 : 1,
            'estimated_days_max' => $isIntl ? 4 : 2,
            'is_available' => true,
        ];

        $econBase = round($weight * $baseMultiplier * 0.7, 2);
        $econFuel = round($econBase * 0.12, 2);
        $services[] = [
            'carrier_code' => 'dhl_express',
            'carrier_name' => 'DHL Express',
            'service_code' => 'economy_select',
            'service_name' => $isIntl ? 'DHL Economy Select' : 'DHL Economy Domestic',
            'net_rate' => $econBase,
            'fuel_surcharge' => $econFuel,
            'other_surcharges' => 0,
            'total_net_rate' => round($econBase + $econFuel, 2),
            'estimated_days_min' => $isIntl ? 5 : 3,
            'estimated_days_max' => $isIntl ? 8 : 5,
            'is_available' => true,
        ];

        if ($isIntl) {
            $premBase = round($weight * $baseMultiplier * 1.8, 2);
            $premFuel = round($premBase * 0.16, 2);
            $services[] = [
                'carrier_code' => 'dhl_express',
                'carrier_name' => 'DHL Express',
                'service_code' => 'express_9_00',
                'service_name' => 'DHL Express 9:00',
                'net_rate' => $premBase,
                'fuel_surcharge' => $premFuel,
                'other_surcharges' => round($weight * 2.5, 2),
                'total_net_rate' => round($premBase + $premFuel + $weight * 2.5, 2),
                'estimated_days_min' => 1,
                'estimated_days_max' => 2,
                'is_available' => true,
            ];
        }

        return $services;
    }

    /**
     * Simulated Aramex rates.
     *
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    private function fetchAramexRates(array $params): array
    {
        $weight = (float) ($params['chargeable_weight'] ?? $params['total_weight'] ?? 1);
        $isIntl  = ($params['origin_country'] ?? 'SA') !== ($params['destination_country'] ?? 'SA');
        $baseMultiplier = $isIntl ? 16.0 : 6.5;

        $services = [];

        $prioBase = round($weight * $baseMultiplier * 1.1, 2);
        $prioFuel = round($prioBase * 0.13, 2);
        $services[] = [
            'carrier_code' => 'aramex',
            'carrier_name' => 'Aramex',
            'service_code' => 'priority_express',
            'service_name' => 'Aramex Priority Express',
            'net_rate' => $prioBase,
            'fuel_surcharge' => $prioFuel,
            'other_surcharges' => 0,
            'total_net_rate' => round($prioBase + $prioFuel, 2),
            'estimated_days_min' => $isIntl ? 3 : 1,
            'estimated_days_max' => $isIntl ? 5 : 3,
            'is_available' => true,
        ];

        $defBase = round($weight * $baseMultiplier * 0.65, 2);
        $defFuel = round($defBase * 0.10, 2);
        $services[] = [
            'carrier_code' => 'aramex',
            'carrier_name' => 'Aramex',
            'service_code' => 'deferred',
            'service_name' => 'Aramex Deferred',
            'net_rate' => $defBase,
            'fuel_surcharge' => $defFuel,
            'other_surcharges' => 0,
            'total_net_rate' => round($defBase + $defFuel, 2),
            'estimated_days_min' => $isIntl ? 6 : 3,
            'estimated_days_max' => $isIntl ? 10 : 6,
            'is_available' => true,
        ];

        return $services;
    }
}
