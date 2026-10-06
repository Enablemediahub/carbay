<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Model;

class PlateScan extends Model implements BranchScoped
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'branch_id', 'manager_id', 'job_id', 'image_path',
        'ocr_raw', 'ocr_confidence', 'corrected_value',
    ];

    protected function casts(): array
    {
        return ['ocr_confidence' => 'float'];
    }

    public function getBranchScopeColumn(): string
    {
        return 'branch_id';
    }
}
