<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Job extends Model implements BranchScoped
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'branch_id', 'manager_id', 'client_id', 'plate',
        'vehicle_category_id', 'make_id', 'model_id', 'total_amount', 'discount_amount',
        'payment_method', 'payment_ref', 'payment_status', 'status',
        'notes', 'photo_path', 'job_type',
    ];

    protected function casts(): array
    {
        return ['total_amount' => 'decimal:2', 'discount_amount' => 'decimal:2'];
    }

    public function getBranchScopeColumn(): string
    {
        return 'branch_id';
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(JobService::class);
    }

    public function workers(): BelongsToMany
    {
        return $this->belongsToMany(Worker::class, 'job_workers')
            ->withPivot(['share_amount', 'payout_mode'])
            ->withTimestamps();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
