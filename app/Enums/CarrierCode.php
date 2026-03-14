<?php
namespace App\Enums;

enum CarrierCode: string
{
    case DHL = 'dhl';
    case ARAMEX = 'aramex';
    case SMSA = 'smsa';
    case FEDEX = 'fedex';
    case UPS = 'ups';
    case SPL = 'spl';
    case NAQEL = 'naqel';
    case ZAJIL = 'zajil';
    case IMILE = 'imile';
    case JANDT = 'jandt';

    public function label(): string
    {
        return match ($this) {
            self::DHL => 'DHL Express',
            self::ARAMEX => 'Aramex',
            self::SMSA => 'SMSA Express',
            self::FEDEX => 'FedEx',
            self::UPS => 'UPS',
            self::SPL => 'البريد السعودي SPL',
            self::NAQEL => 'ناقل Naqel',
            self::ZAJIL => 'زاجل Zajil',
            self::IMILE => 'iMile',
            self::JANDT => 'J&T Express',
        };
    }

    public function trackingUrl(): string
    {
        return match ($this) {
            self::DHL => 'https://www.dhl.com/sa-ar/home/tracking.html?tracking-id=',
            self::ARAMEX => 'https://www.aramex.com/sa/ar/track/shipments?q=',
            self::SMSA => 'https://www.smsaexpress.com/sa/trackingdetails?tracknumbers=',
            self::FEDEX => 'https://www.fedex.com/fedextrack/?tracknumbers=',
            self::UPS => 'https://www.ups.com/track?tracknum=',
            default => '',
        };
    }

    public function supportsInternational(): bool
    {
        return in_array($this, [self::DHL, self::ARAMEX, self::FEDEX, self::UPS]);
    }
}
