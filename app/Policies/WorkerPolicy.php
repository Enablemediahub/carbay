<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Worker;
use App\Policies\Concerns\AuthorizesTenantOperations;

class WorkerPolicy
{
    use AuthorizesTenantOperations;

    public function viewAny(User $user): bool
    {
        return $this->canUseTenant($user);
    }

    public function view(User $user, Worker $worker): bool
    {
        return $this->canUseBranch($user, $worker->branch_id);
    }

    public function create(User $user): bool
    {
        return $this->canUseTenant($user);
    }

    public function update(User $user, Worker $worker): bool
    {
        return $this->canUseBranch($user, $worker->branch_id);
    }

    public function delete(User $user, Worker $worker): bool
    {
        return $this->canUseBranch($user, $worker->branch_id);
    }
}
