<?php

namespace App\Http\Controllers\Api;

use App\Enums\ControlCategory;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\ExcelService;
use App\Services\LocationPaths;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports)
    {
    }

    /** 申報預覽 */
    public function summary(Request $request)
    {
        return $this->reports->summary($this->filters($request));
    }

    /** 一鍵匯出 Excel（彙整表 + 異動明細） */
    public function export(Request $request, ExcelService $excel, LocationPaths $paths)
    {
        $filters = $this->filters($request);
        $summary = $this->reports->summary($filters);
        $category = isset($filters['category']) ? ControlCategory::from($filters['category'])->label() : '全部類別';
        $period = substr($summary['start'], 0, 10).' ~ '.substr($summary['end'], 0, 10);

        $file = $excel->write([
            '異動彙整表' => [
                'title' => ['物品運作量彙整申報表', "申報期間：{$period}　管制分類：{$category}", ''],
                'headers' => ['序號', '物品代號', '中文名稱', '英文名稱', 'CAS No./管制編號', '管制分類', '單位', '期初存量', '進貨量', '使用量', '移出量', '期末存量'],
                'rows' => collect($summary['rows'])->values()->map(fn ($r, $i) => [
                    $i + 1, $r['code'], $r['name_zh'], $r['name_en'], $r['cas_no'], $r['control_category_label'], $r['unit'],
                    $r['opening'], $r['in_qty'], $r['use_qty'], $r['out_qty'], $r['closing'],
                ]),
            ],
            '異動明細' => [
                'headers' => ['異動單號', '作業類型', '作業時間', '物品代號', '中文名稱', '數量', '單位', '來源儲位', '目的儲位', '操作人員', '批號', '備註'],
                'rows' => $this->reports->details($filters)->map(fn (Transaction $t) => [
                    $t->tx_no, $t->type->label(), $t->occurred_at->format('Y-m-d H:i'), $t->item->code, $t->item->name_zh,
                    $t->quantity, $t->unit, $paths->path($t->from_location_id), $paths->path($t->to_location_id),
                    $t->operator, $t->batch_no, $t->remark,
                ]),
            ],
        ]);

        $name = '申報彙整_'.str_replace(['-', ' ~ '], ['', '_'], $period).'.xlsx';

        return response()->download($file, $name)->deleteFileAfterSend();
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
            'category' => ['nullable', Rule::enum(ControlCategory::class)],
            'item_id' => ['nullable', 'integer'],
        ], [], ['start' => '起始日', 'end' => '結束日']);
    }
}
