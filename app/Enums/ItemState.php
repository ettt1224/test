<?php

namespace App\Enums;

enum ItemState: string
{
    case Solid = 'solid';
    case Liquid = 'liquid';
    case Gas = 'gas';

    public function label(): string
    {
        return match ($this) {
            self::Solid => '固態',
            self::Liquid => '液態',
            self::Gas => '氣態',
        };
    }
}
