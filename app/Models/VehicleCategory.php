<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleCategory extends Model
{
    protected $fillable = ['name', 'is_global'];

    protected function casts(): array
    {
        return ['is_global' => 'boolean'];
    }

    public function servicePrices(): HasMany
    {
        return $this->hasMany(ServicePrice::class);
    }
}
