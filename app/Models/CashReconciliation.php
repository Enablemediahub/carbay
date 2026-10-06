<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Model;

class CashReconciliation extends Model implements BranchScoped
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'branch_id', 'manager_id', 'business_date',
        'expected_amount', 'entered_amount', 'notes',
    ];

    protected function casts(): array
    {
        return ['business_date' => 'date', 'expected_amount' => 'decimal:2', 'entered_amount' => 'decimal:2'];
    }

    public function getBranchScopeColumn(): string
    {
        return 'branch_id';
    }
}
