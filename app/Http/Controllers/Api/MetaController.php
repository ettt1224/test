<?php

namespace App\Http\Controllers\Api;

use App\Enums\ControlCategory;
use App\Enums\HazardLabel;
use App\Enums\ItemState;
use App\Enums\LocationLevel;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;

/** 提供前端下拉選單所需的代碼表 */
class MetaController extends Controller
{
    public function __invoke()
    {
        $options = fn (array $cases) => array_map(fn ($c) => ['value' => $c->value, 'label' => $c->label()], $cases);

        return [
            'location_levels' => array_map(fn (LocationLevel $c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'child' => $c->child()?->value,
            ], LocationLevel::cases()),
            'item_states' => $options(ItemState::cases()),
            'control_categories' => $options(ControlCategory::cases()),
            'hazard_labels' => $options(HazardLabel::cases()),
            'transaction_types' => array_map(fn (TransactionType $c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'requires_from' => $c->requiresFrom(),
                'requires_to' => $c->requiresTo(),
            ], TransactionType::cases()),
            'location_types' => ['物品庫', '原料區', '成品區', '液體儲槽', '氣體鋼瓶區', '廢棄物暫存區'],
            'units' => ['kg', 'g', 'L', 'mL', '桶', '瓶', '支', '包'],
        ];
    }
}
