<?php

namespace App\Models;

use App\Enums\LocationLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $fillable = ['code', 'name', 'level', 'parent_id', 'type', 'capacity', 'capacity_unit', 'remark'];

    protected $casts = [
        'level' => LocationLevel::class,
        'capacity' => 'float',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Location::class, 'parent_id')->orderBy('code');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function isReferencedByTransactions(): bool
    {
        return Transaction::where('from_location_id', $this->id)
            ->orWhere('to_location_id', $this->id)
            ->exists();
    }
}
