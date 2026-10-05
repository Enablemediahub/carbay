<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantOperations;

class ExpensePolicy
{
    use AuthorizesTenantOperations;

    public function viewAny(User $user): bool
    {
        return $this->canUseTenant($user) && $user->tenant?->hasFeature('expense_tracking');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $this->viewAny($user) && $this->canUseBranch($user, $expense->branch_id);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $this->viewAny($user) && $this->canUseBranch($user, $expense->branch_id);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $this->update($user, $expense);
    }
}
