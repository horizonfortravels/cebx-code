<?php
namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case OPERATOR = 'operator';
    case VIEWER = 'viewer';
    case FINANCE = 'finance';
    case SUPPORT = 'support';
    case WAREHOUSE = 'warehouse';
    case DRIVER = 'driver';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'مدير النظام',
            self::ADMIN => 'مدير',
            self::MANAGER => 'مشرف',
            self::OPERATOR => 'مشغّل',
            self::VIEWER => 'عارض',
            self::FINANCE => 'مالي',
            self::SUPPORT => 'دعم',
            self::WAREHOUSE => 'مستودع',
            self::DRIVER => 'سائق',
        };
    }

    public function level(): int
    {
        return match ($this) {
            self::SUPER_ADMIN => 100,
            self::ADMIN => 90,
            self::MANAGER => 70,
            self::OPERATOR => 50,
            self::FINANCE => 50,
            self::SUPPORT => 40,
            self::WAREHOUSE => 30,
            self::DRIVER => 20,
            self::VIEWER => 10,
        };
    }
}
