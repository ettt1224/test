<?php

namespace App\Services;

use App\Enums\ControlCategory;
use App\Enums\HazardLabel;
use App\Enums\ItemState;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

/**
 * 物品主檔 Excel 批次匯入。
 * 以「物品代號」為鍵：已存在則更新、不存在則新增。
 * 任一列驗證失敗則整批不寫入，並回傳逐列錯誤。
 */
class ItemImportService
{
    /** Excel 表頭 => 欄位 */
    public const COLUMNS = [
        '物品代號' => 'code',
        '中文名稱' => 'name_zh',
        '英文名稱' => 'name_en',
        'CAS No./管制編號' => 'cas_no',
        '物品狀態' => 'state',
        '管制分類' => 'control_category',
        '危害標示' => 'hazard_labels',
        '預設單位' => 'default_unit',
    ];

    public function __construct(private ExcelService $excel)
    {
    }

    public function templateSheets(): array
    {
        return [
            '物品主檔' => [
                'headers' => array_keys(self::COLUMNS),
                'rows' => [
                    ['CHEM-001', '甲醇', 'Methanol', '67-56-1', '液態', '危險物品', '極燃/易燃、毒性', 'L'],
                    ['CHEM-002', '氫氧化鈉', 'Sodium hydroxide', '1310-73-2', '固態', '一般物品', '腐蝕', 'kg'],
                ],
            ],
            '填寫說明' => [
                'headers' => ['欄位', '說明'],
                'rows' => [
                    ['物品代號', '必填，唯一。已存在者會被更新'],
                    ['中文名稱', '必填'],
                    ['物品狀態', implode(' / ', array_map(fn ($c) => $c->label(), ItemState::cases()))],
                    ['管制分類', implode(' / ', array_map(fn ($c) => $c->label(), ControlCategory::cases())).'（空白預設一般物品）'],
                    ['危害標示', '多個以頓號或逗號分隔：'.implode('、', array_map(fn ($c) => $c->label(), HazardLabel::cases()))],
                ],
            ],
        ];
    }

    /**
     * @return array{created: int, updated: int, errors: array<int, array{row: int, messages: string[]}>}
     */
    public function import(string $path): array
    {
        $rows = $this->excel->readAssoc($path);
        $parsed = [];
        $errors = [];
        $seen = [];

        foreach ($rows as $rowNo => $raw) {
            [$data, $messages] = $this->parseRow($raw);
            if ($data['code'] !== null && isset($seen[$data['code']])) {
                $messages[] = "物品代號 {$data['code']} 與第 {$seen[$data['code']]} 列重複";
            }
            if ($data['code'] !== null) {
                $seen[$data['code']] = $rowNo;
            }
            if ($messages) {
                $errors[] = ['row' => $rowNo, 'messages' => $messages];
            } else {
                $parsed[] = $data;
            }
        }

        if ($errors || ! $parsed) {
            if (! $parsed && ! $errors) {
                $errors[] = ['row' => 0, 'messages' => ['檔案中沒有資料列']];
            }

            return ['created' => 0, 'updated' => 0, 'errors' => $errors];
        }

        $created = 0;
        $updated = 0;
        DB::transaction(function () use ($parsed, &$created, &$updated) {
            foreach ($parsed as $data) {
                $item = Item::firstOrNew(['code' => $data['code']]);
                $item->exists ? $updated++ : $created++;
                $item->fill($data)->save();
            }
        });

        return ['created' => $created, 'updated' => $updated, 'errors' => []];
    }

    private function parseRow(array $raw): array
    {
        $data = [];
        foreach (self::COLUMNS as $header => $field) {
            $value = $raw[$header] ?? null;
            $data[$field] = ($value === null || $value === '') ? null : (string) $value;
        }

        $messages = [];
        if ($data['code'] === null) {
            $messages[] = '物品代號為必填';
        }
        if ($data['name_zh'] === null) {
            $messages[] = '中文名稱為必填';
        }

        if ($data['state'] !== null) {
            $state = $this->matchEnum(ItemState::cases(), $data['state']);
            $state ? $data['state'] = $state->value : $messages[] = "物品狀態「{$data['state']}」無法辨識";
        }

        $category = $data['control_category'] === null
            ? ControlCategory::General
            : $this->matchEnum(ControlCategory::cases(), $data['control_category']);
        $category ? $data['control_category'] = $category->value : $messages[] = "管制分類「{$data['control_category']}」無法辨識";

        $labels = [];
        foreach (preg_split('/[、,，;；]+/u', (string) $data['hazard_labels'], -1, PREG_SPLIT_NO_EMPTY) as $text) {
            $label = HazardLabel::fromInput($text);
            $label ? $labels[] = $label->value : $messages[] = "危害標示「{$text}」無法辨識";
        }
        $data['hazard_labels'] = array_values(array_unique($labels));

        return [$data, $messages];
    }

    private function matchEnum(array $cases, string $value): mixed
    {
        foreach ($cases as $case) {
            if ($case->value === $value || $case->label() === $value) {
                return $case;
            }
        }

        return null;
    }
}
