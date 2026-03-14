<?php
namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case PROCESSING = 'processing';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
    case PARTIALLY_SHIPPED = 'partially_shipped';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'معلّق',
            self::CONFIRMED => 'مؤكد',
            self::PROCESSING => 'قيد المعالجة',
            self::SHIPPED => 'تم الشحن',
            self::DELIVERED => 'تم التسليم',
            self::CANCELLED => 'ملغي',
            self::REFUNDED => 'مسترد',
            self::PARTIALLY_SHIPPED => 'شحن جزئي',
        };
    }

    public function canShip(): bool
    {
        return in_array($this, [self::PENDING, self::CONFIRMED, self::PROCESSING]);
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::PENDING, self::CONFIRMED, self::PROCESSING]);
    }
}
