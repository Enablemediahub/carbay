<?php

namespace App\Models\Scopes;

use App\Models\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if ($user?->isSuperAdmin()) {
            return;
        }

        $tenantId = $user?->tenant_id
            ?? (app()->bound('current_tenant_id') ? app('current_tenant_id') : null);

        if ($tenantId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);

        if ($user?->role === 'manager') {
            if (! $model instanceof BranchScoped || $user->branch_id === null) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where($model->qualifyColumn($model->getBranchScopeColumn()), $user->branch_id);
        }
    }
}
