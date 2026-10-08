<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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

    public function markPaid(): void
    {
        DB::transaction(function (): void {
            $invoice = self::withoutGlobalScopes()->whereKey($this->id)->lockForUpdate()->firstOrFail();
            if ($invoice->status !== 'paid') {
                $invoice->update(['status' => 'paid', 'paid_at' => now()]);
            }

            $hasUnpaidInvoices = self::withoutGlobalScopes()
                ->where('tenant_id', $invoice->tenant_id)
                ->where('status', 'pending')
                ->exists();
            if (! $hasUnpaidInvoices && $invoice->period_ends_at?->endOfDay()->gte(now())) {
                Tenant::withoutGlobalScopes()->whereKey($invoice->tenant_id)->update([
                    'status' => 'active',
                    'grace_period_ends_at' => null,
                ]);
            }
        });
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class)->whereIn('status', ['paid', 'review']);
    }
}
