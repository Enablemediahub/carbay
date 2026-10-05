<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'price', 'billing_cycle', 'branch_addon_price',
        'worker_limit', 'sms_credits', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'branch_addon_price' => 'decimal:2',
            'worker_limit' => 'integer',
            'sms_credits' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function packageFeatures(): HasMany
    {
        return $this->hasMany(PackageFeature::class);
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'package_features')
            ->withPivot(['enabled', 'limit_value'])
            ->withTimestamps();
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }
}
