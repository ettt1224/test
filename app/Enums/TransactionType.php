<?php

namespace App\Enums;

enum TransactionType: string
{
    case In = 'in';
    case Use = 'use';
    case Split = 'split';
    case Transfer = 'transfer';
    case Out = 'out';

    public function label(): string
    {
        return match ($this) {
            self::In => '入庫',
            self::Use => '使用',
            self::Split => '分裝',
            self::Transfer => '移轉',
            self::Out => '移出',
        };
    }

    /** 異動單號前綴 */
    public function prefix(): string
    {
        return match ($this) {
            self::In => 'IN',
            self::Use => 'US',
            self::Split => 'SP',
            self::Transfer => 'TR',
            self::Out => 'OT',
        };
    }

    /** 是否需要來源儲位（扣庫存） */
    public function requiresFrom(): bool
    {
        return $this !== self::In;
    }

    /** 是否需要目的儲位（加庫存） */
    public function requiresTo(): bool
    {
        return in_array($this, [self::In, self::Split, self::Transfer], true);
    }
}
