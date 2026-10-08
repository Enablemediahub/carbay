<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePrice extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'service_id', 'vehicle_category_id',
        'price', 'company_pct', 'worker_pct', 'is_active', 'pricing_system',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'company_pct' => 'decimal:2',
            'worker_pct' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function vehicleCategory(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class);
    }
}
