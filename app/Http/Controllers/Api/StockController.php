<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Services\LocationPaths;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    /** 即時庫存；location_id 會包含所有子節點 */
    public function index(Request $request, LocationPaths $paths)
    {
        $stocks = Stock::with('item:id,code,name_zh,control_category')
            ->where('quantity', '>', 0)
            ->when($request->filled('item_id'), fn ($q) => $q->where('item_id', $request->item_id))
            ->when($request->filled('location_id'), fn ($q) => $q->whereIn('location_id', $paths->descendantIds((int) $request->location_id)))
            ->get()
            ->map(fn (Stock $s) => [...$s->toArray(), 'location_path' => $paths->path($s->location_id)])
            ->sortBy([['item.code', 'asc'], ['location_path', 'asc']])
            ->values();

        return $stocks;
    }

    /** 由作業紀錄重算全部庫存 */
    public function rebuild(StockService $service)
    {
        $count = DB::transaction(fn () => $service->rebuild());

        return ['message' => "已依 {$count} 筆作業紀錄重算庫存"];
    }
}
