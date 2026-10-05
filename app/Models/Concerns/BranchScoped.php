<?php

namespace App\Models\Concerns;

interface BranchScoped
{
    public function getBranchScopeColumn(): string;
}
