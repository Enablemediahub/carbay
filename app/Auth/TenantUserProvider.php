<?php

namespace App\Auth;

use App\Models\Scopes\TenantScope;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Database\Eloquent\Builder;

class TenantUserProvider extends EloquentUserProvider
{
    protected function newModelQuery($model = null): Builder
    {
        $model ??= $this->createModel();

        $query = $model->newQueryWithoutScope(TenantScope::class);

        with($query, $this->queryCallback);

        return $query;
    }
}
