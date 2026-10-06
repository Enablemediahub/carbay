<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerCheckIn extends Model implements BranchScoped
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'branch_id', 'worker_id', 'recorded_by',
        'work_date', 'checked_in_at', 'checked_out_at',
    ];

    protected function casts(): array
    {
        return ['work_date' => 'date', 'checked_in_at' => 'datetime', 'checked_out_at' => 'datetime'];
    }

    public function getBranchScopeColumn(): string
    {
        return 'branch_id';
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }
}
