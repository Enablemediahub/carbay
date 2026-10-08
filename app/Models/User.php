<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\BranchScoped;
use App\Models\Concerns\HasUniqueStaffPhone;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements BranchScoped, FilamentUser, HasAvatar
{
    use BelongsToTenant, HasFactory, HasUniqueStaffPhone, Notifiable;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'role',
        'name',
        'phone',
        'email',
        'password',
        'pin',
        'status',
        'photo_path',
    ];

    protected $hidden = [
        'password',
        'pin',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin' => 'hashed',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? route('staff.photo', ['type' => 'manager', 'id' => $this->id], false) : null;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->photo_url;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        return match ($panel->getId()) {
            'superadmin' => $this->isSuperAdmin(),
            'app' => in_array($this->role, ['ceo', 'manager'], true),
            default => false,
        };
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function getBranchScopeColumn(): string
    {
        return 'branch_id';
    }

    public function managedBranches(): HasMany
    {
        return $this->hasMany(Branch::class, 'manager_id');
    }
}
