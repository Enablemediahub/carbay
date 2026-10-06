<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobService extends Model
{
    protected $fillable = [
        'job_id', 'service_id', 'service_name', 'quantity', 'unit_price',
        'total_amount', 'company_share', 'worker_share', 'company_pct', 'worker_pct',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'company_share' => 'decimal:2',
            'worker_share' => 'decimal:2',
            'company_pct' => 'decimal:2',
            'worker_pct' => 'decimal:2',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
