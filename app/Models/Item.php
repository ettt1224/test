<?php

namespace App\Models;

use App\Enums\ControlCategory;
use App\Enums\ItemState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = [
        'code', 'name_zh', 'name_en', 'cas_no', 'state', 'control_category',
        'hazard_labels', 'default_unit', 'sds_path', 'sds_original_name',
    ];

    protected $casts = [
        'state' => ItemState::class,
        'control_category' => ControlCategory::class,
        'hazard_labels' => 'array',
    ];

    protected $hidden = ['sds_path'];

    protected $appends = ['has_sds'];

    public function getHasSdsAttribute(): bool
    {
        return $this->sds_path !== null;
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
