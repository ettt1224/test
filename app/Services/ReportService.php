<?php

namespace App\Services;

use App\Enums\ControlCategory;
use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 申報統計：期初存量 + 進貨量 - 使用量 - 移出量 = 期末存量
 * （分裝/移轉為廠內移動，不影響全廠總量，僅列出筆數供參考）
 */
class ReportService
{
    /**
     * @param  array{start: string, end: string, category?: string|null, item_id?: int|null}  $filters
     */
    public function summary(array $filters): array
    {
        [$start, $end] = $this->range($filters);

        $in = TransactionType::In->value;
        $use = TransactionType::Use->value;
        $out = TransactionType::Out->value;
        $split = TransactionType::Split->value;
        $transfer = TransactionType::Transfer->value;

        $query = DB::table('transactions as t')
            ->join('items as i', 'i.id', '=', 't.item_id')
            ->where('t.occurred_at', '<=', $end)
            ->groupBy('i.id', 'i.code', 'i.name_zh', 'i.name_en', 'i.cas_no', 'i.control_category', 't.unit')
            ->orderBy('i.code')
            ->orderBy('t.unit')
            ->select('i.id as item_id', 'i.code', 'i.name_zh', 'i.name_en', 'i.cas_no', 'i.control_category', 't.unit')
            ->selectRaw(
                "SUM(CASE WHEN t.occurred_at < ? AND t.type = ? THEN t.quantity
                          WHEN t.occurred_at < ? AND t.type IN (?, ?) THEN -t.quantity ELSE 0 END) AS opening",
                [$start, $in, $start, $use, $out]
            )
            ->selectRaw('SUM(CASE WHEN t.occurred_at >= ? AND t.type = ? THEN t.quantity ELSE 0 END) AS in_qty', [$start, $in])
            ->selectRaw('SUM(CASE WHEN t.occurred_at >= ? AND t.type = ? THEN t.quantity ELSE 0 END) AS use_qty', [$start, $use])
            ->selectRaw('SUM(CASE WHEN t.occurred_at >= ? AND t.type = ? THEN t.quantity ELSE 0 END) AS out_qty', [$start, $out])
            ->selectRaw('SUM(CASE WHEN t.occurred_at >= ? AND t.type IN (?, ?) THEN 1 ELSE 0 END) AS internal_moves', [$start, $split, $transfer]);

        if (! empty($filters['category'])) {
            $query->where('i.control_category', $filters['category']);
        }
        if (! empty($filters['item_id'])) {
            $query->where('i.id', $filters['item_id']);
        }

        $rows = [];
        $warnings = [];
        foreach ($query->get() as $r) {
            $opening = round((float) $r->opening, 3);
            $inQty = round((float) $r->in_qty, 3);
            $useQty = round((float) $r->use_qty, 3);
            $outQty = round((float) $r->out_qty, 3);
            $closing = round($opening + $inQty - $useQty - $outQty, 3);

            // 期初為 0 且期間無異動者不列入
            if ($opening == 0 && $inQty == 0 && $useQty == 0 && $outQty == 0 && (int) $r->internal_moves === 0) {
                continue;
            }

            $row = [
                'item_id' => $r->item_id,
                'code' => $r->code,
                'name_zh' => $r->name_zh,
                'name_en' => $r->name_en,
                'cas_no' => $r->cas_no,
                'control_category' => $r->control_category,
                'control_category_label' => ControlCategory::tryFrom($r->control_category)?->label(),
                'unit' => $r->unit,
                'opening' => $opening,
                'in_qty' => $inQty,
                'use_qty' => $useQty,
                'out_qty' => $outQty,
                'closing' => $closing,
                'internal_moves' => (int) $r->internal_moves,
            ];
            $rows[] = $row;

            if ($opening < 0 || $closing < 0) {
                $warnings[] = "{$r->code} {$r->name_zh} ({$r->unit}) 存量出現負值，請檢查作業紀錄是否漏登入庫。";
            }
        }

        return [
            'start' => $start,
            'end' => $end,
            'rows' => $rows,
            'warnings' => $warnings,
        ];
    }

    /** 期間內的異動明細 */
    public function details(array $filters)
    {
        [$start, $end] = $this->range($filters);

        return Transaction::with(['item', 'fromLocation', 'toLocation'])
            ->whereBetween('occurred_at', [$start, $end])
            ->when(! empty($filters['category']), fn ($q) => $q->whereHas('item', fn ($i) => $i->where('control_category', $filters['category'])))
            ->when(! empty($filters['item_id']), fn ($q) => $q->where('item_id', $filters['item_id']))
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();
    }

    private function range(array $filters): array
    {
        return [
            Carbon::parse($filters['start'])->startOfDay()->format('Y-m-d H:i:s'),
            Carbon::parse($filters['end'])->endOfDay()->format('Y-m-d H:i:s'),
        ];
    }
}
