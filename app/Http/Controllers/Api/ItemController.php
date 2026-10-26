<?php

namespace App\Http\Controllers\Api;

use App\Enums\ControlCategory;
use App\Enums\HazardLabel;
use App\Enums\ItemState;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Services\ExcelService;
use App\Services\ItemImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $keyword = $request->string('keyword')->trim()->toString();

        return Item::query()
            ->when($keyword !== '', function ($q) use ($keyword) {
                $like = '%'.mb_strtolower($keyword).'%';
                $q->where(fn ($w) => $w
                    ->whereRaw('LOWER(code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(name_zh) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(name_en) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(cas_no) LIKE ?', [$like]));
            })
            ->when($request->filled('control_category'), fn ($q) => $q->where('control_category', $request->control_category))
            ->when($request->filled('state'), fn ($q) => $q->where('state', $request->state))
            ->orderBy('code')
            ->paginate($request->integer('per_page', 20));
    }

    /** 下拉選單用的精簡清單 */
    public function options()
    {
        return Item::orderBy('code')->get(['id', 'code', 'name_zh', 'default_unit', 'control_category']);
    }

    public function show(Item $item)
    {
        return $item;
    }

    public function store(Request $request)
    {
        $item = Item::create($this->validated($request));
        $this->saveSds($request, $item);

        return response()->json($item, 201);
    }

    /** 前端以 multipart 傳送時請用 POST + _method=PUT */
    public function update(Request $request, Item $item)
    {
        $item->update($this->validated($request, $item));
        $this->saveSds($request, $item);

        return $item;
    }

    public function destroy(Item $item)
    {
        if ($item->transactions()->exists()) {
            throw ValidationException::withMessages(['item' => '此物品已有作業紀錄，無法刪除']);
        }
        if ($item->sds_path) {
            Storage::delete($item->sds_path);
        }
        $item->delete();

        return response()->noContent();
    }

    public function downloadSds(Item $item)
    {
        abort_unless($item->sds_path && Storage::exists($item->sds_path), 404, '尚未上傳 SDS');

        return Storage::response($item->sds_path, $item->sds_original_name ?? "{$item->code}_SDS.pdf");
    }

    public function deleteSds(Item $item)
    {
        if ($item->sds_path) {
            Storage::delete($item->sds_path);
            $item->update(['sds_path' => null, 'sds_original_name' => null]);
        }

        return $item;
    }

    public function template(ItemImportService $import, ExcelService $excel)
    {
        return response()->download($excel->write($import->templateSheets()), '物品主檔匯入範本.xlsx')->deleteFileAfterSend();
    }

    public function import(Request $request, ItemImportService $import)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:10240']], [], ['file' => '匯入檔案']);
        $result = $import->import($request->file('file')->getRealPath());

        return response()->json($result, $result['errors'] ? 422 : 200);
    }

    private function validated(Request $request, ?Item $item = null): array
    {
        // multipart 傳送陣列時可能是 JSON 字串
        if (is_string($request->hazard_labels)) {
            $request->merge(['hazard_labels' => json_decode($request->hazard_labels, true) ?: []]);
        }

        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('items', 'code')->ignore($item)],
            'name_zh' => ['required', 'string', 'max:200'],
            'name_en' => ['nullable', 'string', 'max:200'],
            'cas_no' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', Rule::enum(ItemState::class)],
            'control_category' => ['required', Rule::enum(ControlCategory::class)],
            'hazard_labels' => ['nullable', 'array'],
            'hazard_labels.*' => [Rule::enum(HazardLabel::class)],
            'default_unit' => ['nullable', 'string', 'max:20'],
            'sds' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
        ], [], [
            'code' => '物品代號', 'name_zh' => '中文名稱', 'control_category' => '管制分類', 'sds' => 'SDS 檔案',
        ]);
    }

    private function saveSds(Request $request, Item $item): void
    {
        if (! $request->hasFile('sds')) {
            return;
        }
        if ($item->sds_path) {
            Storage::delete($item->sds_path);
        }
        $file = $request->file('sds');
        $item->update([
            'sds_path' => $file->store('sds'),
            'sds_original_name' => $file->getClientOriginalName(),
        ]);
    }
}
