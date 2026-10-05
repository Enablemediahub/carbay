<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WashSale extends Model implements BranchScoped
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'branch_id', 'worker_id', 'reference',
        'total_amount', 'payment_method', 'payment_reference', 'status', 'sold_at',
    ];

    protected function casts(): array
    {
        return ['total_amount' => 'decimal:2', 'sold_at' => 'datetime'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WashSaleItem::class);
    }

    public function fraudFlags(): HasMany
    {
        return $this->hasMany(FraudFlag::class);
    }

    public function getBranchScopeColumn(): string
    {
        return 'branch_id';
    }
}
