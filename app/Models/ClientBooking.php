<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientBooking extends Model implements BranchScoped
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'branch_id', 'client_id', 'service_type', 'address',
        'requested_for', 'service_ids', 'estimated_amount', 'discount_amount',
        'loyalty_points_redeemed', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_for' => 'datetime',
            'service_ids' => 'array',
            'estimated_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'loyalty_points_redeemed' => 'integer',
        ];
    }

    public function getBranchScopeColumn(): string
    {
        return 'branch_id';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
