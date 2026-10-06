<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model implements BranchScoped
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'branch_id', 'wallet_id', 'worker_id', 'job_id', 'job_worker_id',
        'payout_id', 'type', 'amount', 'status', 'payout_mode',
        'reference', 'available_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'available_at' => 'datetime'];
    }

    public function getBranchScopeColumn(): string
    {
        return 'branch_id';
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
