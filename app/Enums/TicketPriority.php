<?php
namespace App\Enums;

enum TicketPriority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case URGENT = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'منخفض', self::MEDIUM => 'متوسط',
            self::HIGH => 'عالي', self::URGENT => 'عاجل',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LOW => '#6B7280', self::MEDIUM => '#3B82F6',
            self::HIGH => '#F59E0B', self::URGENT => '#EF4444',
        };
    }

    public function slaHours(): int
    {
        return match ($this) {
            self::LOW => 72, self::MEDIUM => 48,
            self::HIGH => 24, self::URGENT => 4,
        };
    }
}
