<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class BranchAddonInvoice extends Model implements BranchScoped
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'branch_id', 'invoice_number', 'amount',
        'currency', 'status', 'due_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function getBranchScopeColumn(): string
    {
        return 'branch_id';
    }

    public function markPaid(): void
    {
        if ($this->status === 'paid') {
            return;
        }

        DB::transaction(function (): void {
            $this->forceFill(['status' => 'paid', 'paid_at' => now()])->save();
            $this->branch()->update(['is_addon_paid' => true]);
        });
    }
}
