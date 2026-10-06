<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientOtpChallenge extends Model
{
    protected $fillable = [
        'tenant_id', 'phone', 'purpose', 'code_hash', 'attempts',
        'registration_data', 'expires_at', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'registration_data' => 'array',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }
}
