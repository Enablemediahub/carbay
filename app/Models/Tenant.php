<?php

namespace App\Models;

use App\Models\Scopes\TenantRecordScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantRecordScope);
    }

    protected $fillable = [
        'name', 'logo', 'phone', 'email', 'package_id', 'main_branch_id',
        'status', 'trial_ends_at', 'momo_number', 'momo_name',
        'paystack_public_key', 'paystack_secret_key', 'billing_anchor_at',
        'grace_period_ends_at', 'paystack_transfers_enabled',
        'cash_enabled', 'momo_enabled', 'paystack_enabled',
        'loyalty_points_per_10', 'loyalty_reward_points', 'loyalty_reward_value',
        'fraud_discount_threshold_pct',
    ];

    protected $hidden = ['paystack_secret_key'];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'billing_anchor_at' => 'datetime',
            'grace_period_ends_at' => 'datetime',
            'paystack_secret_key' => 'encrypted',
            'cash_enabled' => 'boolean',
            'momo_enabled' => 'boolean',
            'paystack_enabled' => 'boolean',
            'paystack_transfers_enabled' => 'boolean',
            'loyalty_points_per_10' => 'integer',
            'loyalty_reward_points' => 'integer',
            'loyalty_reward_value' => 'decimal:2',
            'fraud_discount_threshold_pct' => 'decimal:2',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function mainBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'main_branch_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function workers(): HasMany
    {
        return $this->hasMany(Worker::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function smsLogs(): HasMany
    {
        return $this->hasMany(SmsLog::class);
    }

    public function activeWorkerCount(): int
    {
        return Worker::withoutGlobalScopes()
            ->where('tenant_id', $this->id)
            ->where('status', 'active')
            ->count();
    }

    public function workerLimit(): int
    {
        return (int) ($this->package?->worker_limit ?? 0);
    }

    public function hasAvailableWorkerSeat(): bool
    {
        return $this->activeWorkerCount() < $this->workerLimit();
    }

    public function subscriptionInvoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    public function branchAddonInvoices(): HasMany
    {
        return $this->hasMany(BranchAddonInvoice::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function tenantFeatures(): HasMany
    {
        return $this->hasMany(TenantFeature::class);
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'tenant_features')
            ->withPivot(['enabled', 'limit_value'])
            ->withTimestamps();
    }

    public function hasFeature(string $key): bool
    {
        $feature = Feature::query()->where('key', $key)->where('is_active', true)->first();

        if (! $feature) {
            return false;
        }

        $override = $this->tenantFeatures()
            ->withoutGlobalScopes()
            ->where('feature_id', $feature->id)
            ->first();

        if ($override) {
            return $override->enabled;
        }

        return (bool) $this->package?->packageFeatures()
            ->where('feature_id', $feature->id)
            ->where('enabled', true)
            ->exists();
    }

    public function featureLimit(string $key): ?int
    {
        $feature = Feature::query()->where('key', $key)->where('is_active', true)->first();

        if (! $feature) {
            return null;
        }

        $override = $this->tenantFeatures()
            ->withoutGlobalScopes()
            ->where('feature_id', $feature->id)
            ->first();

        if ($override) {
            return $override->limit_value;
        }

        return $this->package?->packageFeatures()
            ->where('feature_id', $feature->id)
            ->value('limit_value');
    }
}
