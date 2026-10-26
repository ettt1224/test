<?php

namespace App\Enums;

enum LocationLevel: string
{
    case Factory = 'factory';
    case Area = 'area';
    case Building = 'building';
    case Floor = 'floor';
    case Location = 'location';

    public function label(): string
    {
        return match ($this) {
            self::Factory => '廠區',
            self::Area => '區域',
            self::Building => '棟別',
            self::Floor => '樓層',
            self::Location => '儲位/儲槽',
        };
    }

    /** 下一層級；儲位為最末層 */
    public function child(): ?self
    {
        return match ($this) {
            self::Factory => self::Area,
            self::Area => self::Building,
            self::Building => self::Floor,
            self::Floor => self::Location,
            self::Location => null,
        };
    }
}
