<?php
namespace App\Enums;

enum DgClass: string
{
    case CLASS_1 = 'class_1';
    case CLASS_2 = 'class_2';
    case CLASS_3 = 'class_3';
    case CLASS_4 = 'class_4';
    case CLASS_5 = 'class_5';
    case CLASS_6 = 'class_6';
    case CLASS_7 = 'class_7';
    case CLASS_8 = 'class_8';
    case CLASS_9 = 'class_9';

    public function label(): string
    {
        return match ($this) {
            self::CLASS_1 => 'متفجرات', self::CLASS_2 => 'غازات',
            self::CLASS_3 => 'سوائل قابلة للاشتعال', self::CLASS_4 => 'مواد صلبة قابلة للاشتعال',
            self::CLASS_5 => 'مواد مؤكسدة', self::CLASS_6 => 'مواد سامة',
            self::CLASS_7 => 'مواد مشعة', self::CLASS_8 => 'مواد أكّالة',
            self::CLASS_9 => 'مواد خطرة متنوعة',
        };
    }

    public function restricted(): bool
    {
        return in_array($this, [self::CLASS_1, self::CLASS_7]);
    }
}
