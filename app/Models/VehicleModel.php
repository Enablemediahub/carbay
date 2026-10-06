<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleModel extends Model
{
    protected $fillable = ['tenant_id', 'vehicle_make_id', 'name', 'is_global'];

    protected function casts(): array
    {
        return ['is_global' => 'boolean'];
    }

    public function make(): BelongsTo
    {
        return $this->belongsTo(VehicleMake::class, 'vehicle_make_id');
    }
}
