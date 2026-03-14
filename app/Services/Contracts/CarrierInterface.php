<?php

namespace App\Services\Contracts;

interface CarrierInterface
{
    public function code(): string;

    public function name(): string;

    public function isEnabled(): bool;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createShipment(array $payload): array;

    /**
     * @return array<string, mixed>
     */
    public function track(string $trackingNumber): array;

    /**
     * @return array<string, mixed>
     */
    public function cancel(string $shipmentId, string $trackingNumber): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function getRates(array $params): array;

    /**
     * @return array<string, mixed>
     */
    public function getLabel(string $shipmentId, string $format = 'pdf'): array;
}
