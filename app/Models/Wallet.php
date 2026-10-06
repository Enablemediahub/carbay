<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model implements BranchScoped
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'branch_id', 'worker_id', 'available_balance', 'pending_balance'];

    public function getBranchScopeColumn(): string
    {
        return 'branch_id';
    }

    protected function casts(): array
    {
        return ['available_balance' => 'decimal:2', 'pending_balance' => 'decimal:2'];
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
