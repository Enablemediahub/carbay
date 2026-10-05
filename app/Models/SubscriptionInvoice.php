<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionInvoice extends Model
{
    use BelongsToTenant;

    protected $table = 'subscriptions_invoices';

    protected $fillable = [
        'tenant_id', 'package_id', 'invoice_number', 'amount',
        'currency', 'status', 'period_starts_at', 'period_ends_at',
        'due_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'period_starts_at' => 'date',
            'period_ends_at' => 'date',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
