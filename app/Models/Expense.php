<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model implements BranchScoped
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'branch_id', 'category', 'amount',
        'note', 'recorded_by', 'spent_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'spent_at' => 'datetime'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getBranchScopeColumn(): string
    {
        return 'branch_id';
    }
}
