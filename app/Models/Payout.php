<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model implements BranchScoped
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'branch_id', 'worker_id', 'job_worker_id', 'requested_by',
        'approved_by', 'amount', 'payout_mode', 'method', 'reference',
        'status', 'available_at', 'paid_at', 'gateway_response_json',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'available_at' => 'datetime',
            'paid_at' => 'datetime',
            'gateway_response_json' => 'array',
        ];
    }

    public function getBranchScopeColumn(): string
    {
        return 'branch_id';
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function jobWorker(): BelongsTo
    {
        return $this->belongsTo(JobWorker::class);
    }
}
