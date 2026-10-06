<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudFlag extends Model implements BranchScoped
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'wash_sale_id',
        'job_id',
        'cash_reconciliation_id',
        'reviewed_by',
        'flag_type',
        'description',
        'status',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(WashSale::class, 'wash_sale_id');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getBranchScopeColumn(): string
    {
        return 'branch_id';
    }
}
