<?php

namespace App\Http\Controllers\Api;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\LocationPaths;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function __construct(private TransactionService $service)
    {
    }

    public function index(Request $request, LocationPaths $paths)
    {
        $page = Transaction::with('item:id,code,name_zh')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('item_id'), fn ($q) => $q->where('item_id', $request->item_id))
            ->when($request->filled('location_id'), function ($q) use ($request, $paths) {
                $ids = $paths->descendantIds((int) $request->location_id);
                $q->where(fn ($w) => $w->whereIn('from_location_id', $ids)->orWhereIn('to_location_id', $ids));
            })
            ->when($request->filled('start'), fn ($q) => $q->where('occurred_at', '>=', Carbon::parse($request->start)->startOfDay()))
            ->when($request->filled('end'), fn ($q) => $q->where('occurred_at', '<=', Carbon::parse($request->end)->endOfDay()))
            ->when($request->filled('keyword'), fn ($q) => $q->where(fn ($w) => $w
                ->where('tx_no', 'like', "%{$request->keyword}%")
                ->orWhere('batch_no', 'like', "%{$request->keyword}%")
                ->orWhere('operator', 'like', "%{$request->keyword}%")))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        $page->getCollection()->transform(fn (Transaction $tx) => [
            ...$tx->toArray(),
            'type_label' => $tx->type->label(),
            'from_location_path' => $paths->path($tx->from_location_id),
            'to_location_path' => $paths->path($tx->to_location_id),
        ]);

        return $page;
    }

    public function show(Transaction $transaction)
    {
        return $transaction->load(['item', 'fromLocation', 'toLocation']);
    }

    public function store(Request $request)
    {
        $type = TransactionType::tryFrom((string) $request->input('type'));

        $data = $request->validate([
            'type' => ['required', Rule::enum(TransactionType::class)],
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['required', 'string', 'max:20'],
            'from_location_id' => [Rule::requiredIf($type?->requiresFrom() ?? false), 'nullable', 'integer', 'exists:locations,id'],
            'to_location_id' => [
                Rule::requiredIf($type?->requiresTo() ?? false), 'nullable', 'integer', 'exists:locations,id',
                Rule::when($type?->requiresFrom() && $type?->requiresTo(), ['different:from_location_id']),
            ],
            'operator' => ['required', 'string', 'max:50'],
            'occurred_at' => ['required', 'date'],
            'batch_no' => ['nullable', 'string', 'max:100'],
            'remark' => ['nullable', 'string'],
        ], [
            'to_location_id.different' => '來源與目的儲位不可相同',
        ], [
            'type' => '作業類型', 'item_id' => '物品', 'quantity' => '異動數量', 'unit' => '計量單位',
            'from_location_id' => '來源儲位', 'to_location_id' => '目的儲位', 'operator' => '操作人員', 'occurred_at' => '作業時間',
        ]);

        return response()->json($this->service->create($data), 201);
    }

    /** 刪除作業紀錄並回沖庫存（作業紀錄不提供修改，錯誤時刪除後重建） */
    public function destroy(Transaction $transaction)
    {
        $this->service->delete($transaction);

        return response()->noContent();
    }
}
