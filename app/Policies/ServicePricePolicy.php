<?php

namespace App\Policies;

use App\Models\ServicePrice;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantOperations;

class ServicePricePolicy
{
    use AuthorizesTenantOperations;

    public function viewAny(User $user): bool
    {
        return $this->canUseTenant($user) && $user->role === 'ceo';
    }

    public function view(User $user, ServicePrice $servicePrice): bool
    {
        return $this->viewAny($user)
            && $servicePrice->tenant_id === $user->tenant_id;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, ServicePrice $servicePrice): bool
    {
        return $this->view($user, $servicePrice);
    }

    public function delete(User $user, ServicePrice $servicePrice): bool
    {
        return $this->view($user, $servicePrice);
    }
}
