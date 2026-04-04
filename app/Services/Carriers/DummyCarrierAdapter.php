<?php

namespace App\Services\Carriers;

use App\Models\FeatureFlag;
use App\Services\Contracts\CarrierInterface;
use Illuminate\Support\Str;

/**
 * DummyCarrierAdapter — C-1: Carrier Integration Skeleton
 *
 * Default carrier adapter returning simulated responses.
 * Used as fallback when no real carrier adapter is configured.
 * Also used in staging/testing/sandbox environments.
 *
 * Respects feature flags: config('features.carrier_{code}')
 *
 * Does NOT modify existing CarrierService or CarrierRateAdapter.
 * Those services continue to work as-is. This skeleton is for
 * future activation via the CarrierAdapterFactory.
 */
class DummyCarrierAdapter implements CarrierInterface
{
    private string $carrierCode;
    private string $carrierName;

    public function __construct(string $code = 'dummy', string $name = 'Dummy Carrier')
    {
        $this->carrierCode = $code;
        $this->carrierName = $name;
    }

    public function code(): string
    {
        return $this->carrierCode;
    }

    public function name(): string
    {
        return $this->carrierName;
    }

    public function isEnabled(): bool
    {
        return FeatureFlag::runtimeEnabled("carrier_{$this->carrierCode}");
    }

    // ═════════════════════════════════════════════════════════
    // CREATE SHIPMENT — Simulated
    // ═════════════════════════════════════════════════════════

    public function createShipment(array $payload): array
    {
        if (!$this->isEnabled()) {
            return $this->disabledResponse('createShipment');
        }

        $trackingNumber = strtoupper($this->carrierCode) . rand(1000000000, 9999999999);

        return [
            'success'          => true,
            'shipment_id'      => 'SIM-' . Str::uuid()->toString(),
            'tracking_number'  => $trackingNumber,
            'label_url'        => null,
            'label_content'    => null,
            'error'            => null,
            '_simulated'       => true,
            '_carrier'         => $this->carrierCode,
            '_timestamp'       => now()->toIso8601String(),
        ];
    }

    // ═════════════════════════════════════════════════════════
    // TRACK — Simulated
    // ═════════════════════════════════════════════════════════

    public function track(string $trackingNumber): array
    {
        if (!$this->isEnabled()) {
            return $this->disabledResponse('track');
        }

        return [
            'success' => true,
            'status'  => 'in_transit',
            'events'  => [
                [
                    'status'      => 'picked_up',
                    'description' => 'تم الاستلام من المرسل',
                    'location'    => 'الرياض',
                    'timestamp'   => now()->subHours(24)->toIso8601String(),
                ],
                [
                    'status'      => 'in_transit',
                    'description' => 'في الطريق إلى مركز التوزيع',
                    'location'    => 'جدة',
                    'timestamp'   => now()->subHours(6)->toIso8601String(),
                ],
            ],
            'error'      => null,
            '_simulated' => true,
        ];
    }

    // ═════════════════════════════════════════════════════════
    // CANCEL — Simulated
    // ═════════════════════════════════════════════════════════

    public function cancel(string $shipmentId, string $trackingNumber): array
    {
        if (!$this->isEnabled()) {
            return $this->disabledResponse('cancel');
        }

        return [
            'success'         => true,
            'cancellation_id' => 'CXL-' . Str::random(10),
            'error'           => null,
            '_simulated'      => true,
        ];
    }

    // ═════════════════════════════════════════════════════════
    // GET RATES — Simulated
    // ═════════════════════════════════════════════════════════

    public function getRates(array $params): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        $weight = (float) ($params['weight'] ?? 1);
        $isIntl = ($params['origin_country'] ?? 'SA') !== ($params['destination_country'] ?? 'SA');
        $baseRate = $isIntl ? $weight * 18 : $weight * 8;

        return [
            [
                'service_code'   => 'express',
                'service_name'   => $this->carrierName . ' Express',
                'net_rate'       => round($baseRate * 1.3, 2),
                'currency'       => 'SAR',
                'estimated_days' => $isIntl ? '2-4' : '1-2',
                '_simulated'     => true,
            ],
            [
                'service_code'   => 'economy',
                'service_name'   => $this->carrierName . ' Economy',
                'net_rate'       => round($baseRate * 0.8, 2),
                'currency'       => 'SAR',
                'estimated_days' => $isIntl ? '5-8' : '3-5',
                '_simulated'     => true,
            ],
        ];
    }

    // ═════════════════════════════════════════════════════════
    // GET LABEL — Simulated
    // ═════════════════════════════════════════════════════════

    public function getLabel(string $shipmentId, string $format = 'pdf'): array
    {
        if (!$this->isEnabled()) {
            return $this->disabledResponse('getLabel');
        }

        return [
            'success'    => true,
            'content'    => base64_encode('SIMULATED_LABEL_CONTENT_FOR_' . $shipmentId),
            'format'     => $format,
            'error'      => null,
            '_simulated' => true,
        ];
    }

    // ═════════════════════════════════════════════════════════
    // HELPER
    // ═════════════════════════════════════════════════════════

    private function disabledResponse(string $operation): array
    {
        return [
            'success' => false,
            'error'   => "Carrier [{$this->carrierCode}] is disabled via feature flag (operation: {$operation})",
            '_simulated' => true,
            '_disabled'  => true,
        ];
    }
}
