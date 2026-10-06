<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model implements BranchScoped
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'branch_id', 'manager_id', 'job_id', 'amount', 'method',
        'reference', 'status', 'gateway_response_json',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'gateway_response_json' => 'array'];
    }

    public function getBranchScopeColumn(): string
    {
        return 'branch_id';
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
