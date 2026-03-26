<?php

namespace App\Support;

use App\Models\CarrierShipment;
use App\Models\Shipment;

final class CanonicalShipmentStatus
{
    public const PURCHASED = 'purchased';
    public const LABEL_READY = 'label_ready';
    public const IN_TRANSIT = 'in_transit';
    public const OUT_FOR_DELIVERY = 'out_for_delivery';
    public const DELIVERED = 'delivered';
    public const EXCEPTION = 'exception';
    public const CANCELLED = 'cancelled';
    public const RETURNED = 'returned';
    public const UNKNOWN = 'unknown';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::PURCHASED,
            self::LABEL_READY,
            self::IN_TRANSIT,
            self::OUT_FOR_DELIVERY,
            self::DELIVERED,
            self::EXCEPTION,
            self::CANCELLED,
            self::RETURNED,
            self::UNKNOWN,
        ];
    }

    public static function normalize(?string $status): string
    {
        $value = strtolower(trim((string) $status));

        return match ($value) {
            self::PURCHASED,
            'ready_for_pickup' => self::PURCHASED,
            self::LABEL_READY,
            'label_created',
            'label_pending',
            'created',
            'booked' => self::LABEL_READY,
            self::IN_TRANSIT,
            'picked_up',
            'processed',
            'customs',
            'customs_released',
            'clearance' => self::IN_TRANSIT,
            self::OUT_FOR_DELIVERY => self::OUT_FOR_DELIVERY,
            self::DELIVERED => self::DELIVERED,
            self::EXCEPTION,
            'failed_attempt',
            'failed_delivery',
            'on_hold',
            'lost',
            'failed' => self::EXCEPTION,
            self::CANCELLED => self::CANCELLED,
            self::RETURNED => self::RETURNED,
            default => self::UNKNOWN,
        };
    }

    public static function label(?string $status): string
    {
        return match (self::normalize($status)) {
            self::PURCHASED => 'تم إصدار الشحنة',
            self::LABEL_READY => 'الملصق والمستندات جاهزة',
            self::IN_TRANSIT => 'الشحنة في الطريق',
            self::OUT_FOR_DELIVERY => 'خرجت للتسليم',
            self::DELIVERED => 'تم التسليم',
            self::EXCEPTION => 'يوجد استثناء أو مشكلة',
            self::CANCELLED => 'تم إلغاء الشحنة',
            self::RETURNED => 'تمت إعادة الشحنة',
            default => 'الحالة غير معروفة',
        };
    }

    public static function fromShipment(Shipment $shipment): string
    {
        $trackingStatus = trim((string) ($shipment->tracking_status ?? ''));
        if ($trackingStatus !== '' && self::normalize($trackingStatus) !== self::UNKNOWN) {
            return self::normalize($trackingStatus);
        }

        $shipmentStatus = strtolower(trim((string) ($shipment->status ?? '')));
        $carrierStatus = strtolower(trim((string) ($shipment->carrierShipment?->status ?? '')));

        if ($carrierStatus === CarrierShipment::STATUS_LABEL_READY) {
            return self::LABEL_READY;
        }

        return match ($shipmentStatus) {
            Shipment::STATUS_PURCHASED,
            Shipment::STATUS_READY_FOR_PICKUP => self::PURCHASED,
            Shipment::STATUS_PICKED_UP,
            Shipment::STATUS_IN_TRANSIT => self::IN_TRANSIT,
            Shipment::STATUS_OUT_FOR_DELIVERY => self::OUT_FOR_DELIVERY,
            Shipment::STATUS_DELIVERED => self::DELIVERED,
            Shipment::STATUS_EXCEPTION,
            Shipment::STATUS_FAILED => self::EXCEPTION,
            Shipment::STATUS_CANCELLED => self::CANCELLED,
            Shipment::STATUS_RETURNED => self::RETURNED,
            default => self::UNKNOWN,
        };
    }
}
