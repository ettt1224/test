<?php

namespace App\Enums;

enum ControlCategory: string
{
    case General = 'general';
    case Toxic = 'toxic';
    case Priority = 'priority';
    case Dangerous = 'dangerous';

    public function label(): string
    {
        return match ($this) {
            self::General => '一般物品',
            self::Toxic => '毒化物',
            self::Priority => '優先管理物品',
            self::Dangerous => '危險物品',
        };
    }
}
