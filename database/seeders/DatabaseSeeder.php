<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\Location;
use App\Services\TransactionService;
use Illuminate\Database\Seeder;

/** 示範資料：php artisan migrate:fresh --seed */
class DatabaseSeeder extends Seeder
{
    public function run(TransactionService $transactions): void
    {
        $factory = Location::create(['code' => 'F01', 'name' => '台中廠', 'level' => 'factory']);
        $area = Location::create(['code' => 'F01-A', 'name' => '北區', 'level' => 'area', 'parent_id' => $factory->id]);
        $building = Location::create(['code' => 'F01-A-B1', 'name' => '1棟', 'level' => 'building', 'parent_id' => $area->id]);
        $floor1 = Location::create(['code' => 'F01-A-B1-1F', 'name' => '1F', 'level' => 'floor', 'parent_id' => $building->id]);
        $floor2 = Location::create(['code' => 'F01-A-B1-2F', 'name' => '2F', 'level' => 'floor', 'parent_id' => $building->id]);
        $tank = Location::create([
            'code' => 'T-101', 'name' => '甲醇儲槽', 'level' => 'location', 'parent_id' => $floor1->id,
            'type' => '液體儲槽', 'capacity' => 5000, 'capacity_unit' => 'L',
        ]);
        $raw = Location::create([
            'code' => 'R-101', 'name' => '原料區 A', 'level' => 'location', 'parent_id' => $floor1->id,
            'type' => '原料區', 'capacity' => 2000, 'capacity_unit' => 'kg',
        ]);
        $lab = Location::create([
            'code' => 'R-201', 'name' => '實驗室物品櫃', 'level' => 'location', 'parent_id' => $floor2->id,
            'type' => '物品庫',
        ]);

        $methanol = Item::create([
            'code' => 'CHEM-001', 'name_zh' => '甲醇', 'name_en' => 'Methanol', 'cas_no' => '67-56-1',
            'state' => 'liquid', 'control_category' => 'dangerous', 'hazard_labels' => ['flammable', 'toxic'], 'default_unit' => 'L',
        ]);
        $naoh = Item::create([
            'code' => 'CHEM-002', 'name_zh' => '氫氧化鈉', 'name_en' => 'Sodium hydroxide', 'cas_no' => '1310-73-2',
            'state' => 'solid', 'control_category' => 'general', 'hazard_labels' => ['corrosive'], 'default_unit' => 'kg',
        ]);
        Item::create([
            'code' => 'CHEM-003', 'name_zh' => '甲醛', 'name_en' => 'Formaldehyde', 'cas_no' => '50-00-0',
            'state' => 'liquid', 'control_category' => 'toxic', 'hazard_labels' => ['toxic', 'health_hazard'], 'default_unit' => 'L',
        ]);

        $base = ['operator' => '系統管理員'];
        $transactions->create([...$base, 'type' => 'in', 'item_id' => $methanol->id, 'quantity' => 1000, 'unit' => 'L', 'to_location_id' => $tank->id, 'occurred_at' => now()->subMonths(2)->toDateTimeString(), 'batch_no' => 'B240801']);
        $transactions->create([...$base, 'type' => 'use', 'item_id' => $methanol->id, 'quantity' => 150, 'unit' => 'L', 'from_location_id' => $tank->id, 'occurred_at' => now()->subMonth()->toDateTimeString()]);
        $transactions->create([...$base, 'type' => 'split', 'item_id' => $methanol->id, 'quantity' => 20, 'unit' => 'L', 'from_location_id' => $tank->id, 'to_location_id' => $lab->id, 'occurred_at' => now()->subDays(10)->toDateTimeString(), 'remark' => '分裝 20L 至實驗室']);
        $transactions->create([...$base, 'type' => 'in', 'item_id' => $naoh->id, 'quantity' => 500, 'unit' => 'kg', 'to_location_id' => $raw->id, 'occurred_at' => now()->subDays(20)->toDateTimeString()]);
        $transactions->create([...$base, 'type' => 'out', 'item_id' => $naoh->id, 'quantity' => 50, 'unit' => 'kg', 'from_location_id' => $raw->id, 'occurred_at' => now()->subDays(3)->toDateTimeString(), 'remark' => '廢棄物清運']);
    }
}
