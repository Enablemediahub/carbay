<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobWorker extends Model
{
    protected $table = 'job_workers';

    protected $fillable = ['job_id', 'worker_id', 'share_amount', 'payout_mode'];

    protected function casts(): array
    {
        return ['share_amount' => 'decimal:2'];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }
}
