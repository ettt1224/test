<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    public function __construct(private StockService $stocks)
    {
    }

    public function create(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            $type = TransactionType::from($data['type']);

            // 依作業類型清掉不適用的儲位欄位
            if (! $type->requiresFrom()) {
                $data['from_location_id'] = null;
            }
            if (! $type->requiresTo()) {
                $data['to_location_id'] = null;
            }

            $data['tx_no'] = $this->nextNumber($type, Carbon::parse($data['occurred_at']));
            $tx = Transaction::create($data);
            $this->stocks->apply($tx);

            return $tx;
        });
    }

    public function delete(Transaction $tx): void
    {
        DB::transaction(function () use ($tx) {
            $this->stocks->reverse($tx);
            $tx->delete();
        });
    }

    /** 單號格式：前綴 + 日期 + 4 碼流水號，例如 IN20261020-0001 */
    private function nextNumber(TransactionType $type, Carbon $date): string
    {
        $prefix = $type->prefix().$date->format('Ymd').'-';
        $last = Transaction::where('tx_no', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('tx_no')
            ->value('tx_no');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
