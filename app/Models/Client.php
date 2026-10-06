<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model implements BranchScoped
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'branch_id', 'name', 'phone', 'email', 'birthday',
        'preferences_json', 'loyalty_points',
    ];

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'preferences_json' => 'array',
            'loyalty_points' => 'integer',
        ];
    }

    public function getBranchScopeColumn(): string
    {
        return 'branch_id';
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(ClientBooking::class);
    }

    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }
}
