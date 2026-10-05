<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantOperations;

class BranchPolicy
{
    use AuthorizesTenantOperations;

    public function viewAny(User $user): bool
    {
        return $this->canUseTenant($user);
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->canUseBranch($user, $branch->id);
    }

    public function create(User $user): bool
    {
        return $this->canUseTenant($user) && $user->role === 'ceo';
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->role === 'ceo' && $this->canUseBranch($user, $branch->id);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $branch->is_main === false
            && $user->role === 'ceo'
            && $this->canUseBranch($user, $branch->id);
    }
}
