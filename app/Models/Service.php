<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'name', 'description', 'default_price', 'company_pct',
        'worker_pct', 'is_global', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'company_pct' => 'decimal:2',
            'worker_pct' => 'decimal:2',
            'is_global' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->where('is_global', true);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ServicePrice::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(WashSaleItem::class);
    }
}
