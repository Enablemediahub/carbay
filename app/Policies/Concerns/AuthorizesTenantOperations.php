<?php

namespace App\Policies\Concerns;

use App\Models\Branch;
use App\Models\User;

trait AuthorizesTenantOperations
{
    protected function canUseTenant(User $user): bool
    {
        return in_array($user->role, ['ceo', 'manager'], true)
            && $user->status === 'active'
            && $user->tenant_id !== null;
    }

    protected function canUseBranch(User $user, ?int $branchId): bool
    {
        if (! $this->canUseTenant($user) || $branchId === null) {
            return false;
        }

        if ($user->role === 'manager' && $user->branch_id !== $branchId) {
            return false;
        }

        return Branch::withoutGlobalScopes()
            ->whereKey($branchId)
            ->where('tenant_id', $user->tenant_id)
            ->exists();
    }
}
