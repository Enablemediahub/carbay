<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'branch_id', 'client_id', 'recipient', 'purpose',
        'campaign_key', 'message', 'status', 'credit_refunded',
        'credits_charged', 'credits_period_start', 'provider_response_json', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'credit_refunded' => 'boolean',
            'credits_charged' => 'integer',
            'credits_period_start' => 'date',
            'provider_response_json' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
