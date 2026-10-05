<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleMake extends Model
{
    protected $fillable = ['name', 'is_global'];

    protected function casts(): array
    {
        return ['is_global' => 'boolean'];
    }

    public function models(): HasMany
    {
        return $this->hasMany(VehicleModel::class);
    }
}
