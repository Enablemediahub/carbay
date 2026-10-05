<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Worker extends Model implements BranchScoped
{
    use BelongsToTenant, HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Worker $worker): void {
            if ($worker->status === 'inactive') {
                return;
            }

            static::ensureTenantHasWorkerSeat($worker);
        });

        static::updating(function (Worker $worker): void {
            if (! $worker->isDirty('status') || $worker->status !== 'active') {
                return;
            }

            static::ensureTenantHasWorkerSeat($worker);
        });
    }

    protected $fillable = [
        'tenant_id', 'branch_id', 'name', 'phone', 'pin',
        'type', 'default_share_pct', 'status',
    ];

    protected $hidden = ['pin'];

    protected function casts(): array
    {
        return [
            'pin' => 'hashed',
            'default_share_pct' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function getBranchScopeColumn(): string
    {
        return 'branch_id';
    }

    public function sales(): HasMany
    {
        return $this->hasMany(WashSale::class);
    }

    private static function ensureTenantHasWorkerSeat(Worker $worker): void
    {
        $tenant = Tenant::withoutGlobalScopes()->find($worker->tenant_id);

        if (! $tenant || ! $tenant->hasAvailableWorkerSeat()) {
            throw ValidationException::withMessages([
                'name' => 'Your package worker limit has been reached. Deactivate an existing worker or upgrade your package to add another active worker.',
            ]);
        }
    }
}
