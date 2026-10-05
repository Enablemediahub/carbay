<?php

namespace App\Models;

use App\Models\Scopes\SaleTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WashSaleItem extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new SaleTenantScope);
    }

    protected $fillable = [
        'wash_sale_id', 'service_id', 'service_name',
        'quantity', 'unit_price', 'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(WashSale::class, 'wash_sale_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
