<?php

namespace App\Services\Carriers\Contracts;

interface CarrierRateProvider
{
    public function carrierCode(): string;

    public function isEnabled(): bool;

    /**
     * @param array<string, mixed> $context
     * @return array{
     *     services: array<int, array<string, mixed>>,
     *     alerts: array<int, array<string, mixed>>
     * }
     */
    public function fetchServiceAvailability(array $context): array;

    /**
     * @param array<string, mixed> $context
     * @return array{
     *     offers: array<int, array<string, mixed>>,
     *     alerts: array<int, array<string, mixed>>
     * }
     */
    public function fetchNetRates(array $context): array;
}
