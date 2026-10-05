<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WashSale;
use App\Policies\Concerns\AuthorizesTenantOperations;

class WashSalePolicy
{
    use AuthorizesTenantOperations;

    public function viewAny(User $user): bool
    {
        return $this->canUseTenant($user);
    }

    public function view(User $user, WashSale $sale): bool
    {
        return $this->canUseBranch($user, $sale->branch_id);
    }

    public function create(User $user): bool
    {
        return $this->canUseTenant($user);
    }

    public function update(User $user, WashSale $sale): bool
    {
        return $this->canUseBranch($user, $sale->branch_id);
    }

    public function delete(User $user, WashSale $sale): bool
    {
        return $this->canUseBranch($user, $sale->branch_id);
    }
}
