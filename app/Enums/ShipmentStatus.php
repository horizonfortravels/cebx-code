<?php
namespace App\Enums;

enum ShipmentStatus: string
{
    case DRAFT = 'draft';
    case PROCESSING = 'processing';
    case LABEL_CREATED = 'label_created';
    case PICKED_UP = 'picked_up';
    case SHIPPED = 'shipped';
    case IN_TRANSIT = 'in_transit';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERED = 'delivered';
    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';
    case EXCEPTION = 'exception';
    case ON_HOLD = 'on_hold';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'مسودة',
            self::PROCESSING => 'قيد المعالجة',
            self::LABEL_CREATED => 'تم إنشاء الملصق',
            self::PICKED_UP => 'تم الاستلام',
            self::SHIPPED => 'تم الشحن',
            self::IN_TRANSIT => 'في الطريق',
            self::OUT_FOR_DELIVERY => 'خارج للتسليم',
            self::DELIVERED => 'تم التسليم',
            self::RETURNED => 'مرتجع',
            self::CANCELLED => 'ملغي',
            self::EXCEPTION => 'استثناء',
            self::ON_HOLD => 'معلّق',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => '#6B7280',
            self::PROCESSING => '#F59E0B',
            self::LABEL_CREATED => '#8B5CF6',
            self::PICKED_UP => '#3B82F6',
            self::SHIPPED => '#06B6D4',
            self::IN_TRANSIT => '#2563EB',
            self::OUT_FOR_DELIVERY => '#F97316',
            self::DELIVERED => '#10B981',
            self::RETURNED => '#EF4444',
            self::CANCELLED => '#DC2626',
            self::EXCEPTION => '#EF4444',
            self::ON_HOLD => '#FBBF24',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::DELIVERED, self::RETURNED, self::CANCELLED]);
    }

    public function isCancellable(): bool
    {
        return in_array($this, [self::DRAFT, self::PROCESSING, self::LABEL_CREATED]);
    }
}
