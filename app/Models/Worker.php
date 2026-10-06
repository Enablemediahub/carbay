<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Validation\ValidationException;

class Worker extends Authenticatable implements BranchScoped
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
        'type', 'default_share_pct', 'payout_mode', 'status',
    ];

    protected $hidden = ['pin'];

    protected function casts(): array
    {
        return [
            'pin' => 'hashed',
            'default_share_pct' => 'decimal:2',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'pin';
    }

    public function isSuperAdmin(): bool
    {
        return false;
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

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function jobs(): BelongsToMany
    {
        return $this->belongsToMany(Job::class, 'job_workers')->withPivot(['share_amount', 'payout_mode'])->withTimestamps();
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
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
