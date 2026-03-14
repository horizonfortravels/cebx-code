<?php
namespace App\Enums;

enum ClaimType: string
{
    case DAMAGE = 'damage';
    case LOSS = 'loss';
    case DELAY = 'delay';
    case WRONG_DELIVERY = 'wrong_delivery';
    case PARTIAL_DELIVERY = 'partial_delivery';
    case OVERCHARGE = 'overcharge';

    public function label(): string
    {
        return match ($this) {
            self::DAMAGE => 'تلف', self::LOSS => 'فقدان', self::DELAY => 'تأخير',
            self::WRONG_DELIVERY => 'تسليم خاطئ', self::PARTIAL_DELIVERY => 'تسليم جزئي',
            self::OVERCHARGE => 'زيادة تسعير',
        };
    }
}
