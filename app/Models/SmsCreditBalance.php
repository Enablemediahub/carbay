<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsCreditBalance extends Model
{
    protected $fillable = ['tenant_id', 'credits_remaining', 'period_starts_at', 'period_ends_at'];

    protected function casts(): array
    {
        return [
            'credits_remaining' => 'integer',
            'period_starts_at' => 'date',
            'period_ends_at' => 'date',
        ];
    }
}
