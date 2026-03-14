<?php

namespace App\Services\Carriers\Contracts;

interface CarrierShipmentProvider
{
    public function carrierCode(): string;

    public function isEnabled(): bool;

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function createShipment(array $context): array;
}
