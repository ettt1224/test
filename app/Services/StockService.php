<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\Transaction;
use Illuminate\Validation\ValidationException;

/**
 * 依作業紀錄維護即時庫存（stocks 表）。
 * 呼叫端需包在 DB::transaction 內。
 */
class StockService
{
    private const EPSILON = 0.0005;

    /** 套用一筆作業對庫存的影響 */
    public function apply(Transaction $tx): void
    {
        $this->move($tx, 1);
    }

    /** 撤銷一筆作業對庫存的影響（刪除作業時使用） */
    public function reverse(Transaction $tx): void
    {
        $this->move($tx, -1);
    }

    private function move(Transaction $tx, int $direction): void
    {
        $qty = $tx->quantity * $direction;

        // 先扣後加：撤銷時順序相反，避免中途出現負庫存誤判
        $steps = [];
        if ($tx->from_location_id) {
            $steps[] = [$tx->from_location_id, -$qty];
        }
        if ($tx->to_location_id) {
            $steps[] = [$tx->to_location_id, $qty];
        }
        if ($direction < 0) {
            $steps = array_reverse($steps);
        }

        foreach ($steps as [$locationId, $delta]) {
            $this->adjust($tx->item_id, $locationId, $tx->unit, $delta);
        }
    }

    public function adjust(int $itemId, int $locationId, string $unit, float $delta): Stock
    {
        $stock = Stock::where(['item_id' => $itemId, 'location_id' => $locationId, 'unit' => $unit])
            ->lockForUpdate()
            ->first()
            ?? new Stock(['item_id' => $itemId, 'location_id' => $locationId, 'unit' => $unit, 'quantity' => 0]);

        $newQty = round($stock->quantity + $delta, 3);
        if ($newQty < -self::EPSILON) {
            throw ValidationException::withMessages([
                'quantity' => sprintf('庫存不足：目前 %s %s，需扣除 %s %s', $stock->quantity, $unit, abs($delta), $unit),
            ]);
        }

        $stock->quantity = max($newQty, 0);
        $stock->save();

        return $stock;
    }

    /** 由作業紀錄重新計算全部庫存（資料修復用） */
    public function rebuild(): int
    {
        Stock::query()->delete();
        $count = 0;
        Transaction::orderBy('occurred_at')->orderBy('id')->each(function (Transaction $tx) use (&$count) {
            $this->apply($tx);
            $count++;
        });

        return $count;
    }
}
