<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Branch extends Model implements BranchScoped
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'name', 'address', 'phone', 'manager_id',
        'is_main', 'status', 'is_addon_paid',
    ];

    protected function casts(): array
    {
        return ['is_main' => 'boolean', 'is_addon_paid' => 'boolean'];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function getBranchScopeColumn(): string
    {
        return 'id';
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function workers(): HasMany
    {
        return $this->hasMany(Worker::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(WashSale::class);
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

    public function addonInvoice(): HasOne
    {
        return $this->hasOne(BranchAddonInvoice::class);
    }

    public function addonInvoices(): HasMany
    {
        return $this->hasMany(BranchAddonInvoice::class);
    }
}
