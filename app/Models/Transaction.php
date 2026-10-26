<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'tx_no', 'type', 'item_id', 'quantity', 'unit', 'from_location_id', 'to_location_id',
        'operator', 'occurred_at', 'batch_no', 'remark',
    ];

    protected $casts = [
        'type' => TransactionType::class,
        'quantity' => 'float',
        'occurred_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }
}
