<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TenantOnboarder
{
    public function create(array $tenantAttributes, array $ceoAttributes): Tenant
    {
        return DB::transaction(function () use ($tenantAttributes, $ceoAttributes): Tenant {
            $tenant = Tenant::query()->create([
                ...$tenantAttributes,
                'status' => $tenantAttributes['status'] ?? 'trial',
            ]);

            $branch = $tenant->branches()->create([
                'name' => $tenant->name.' Main Branch',
                'is_main' => true,
                'status' => 'active',
                'is_addon_paid' => true,
            ]);

            $tenant->forceFill(['main_branch_id' => $branch->id])->save();

            $tenant->users()->create([
                'branch_id' => $branch->id,
                'role' => 'ceo',
                'name' => $ceoAttributes['name'],
                'phone' => $ceoAttributes['phone'] ?? null,
                'email' => $ceoAttributes['email'],
                'password' => $ceoAttributes['password'],
                'status' => 'active',
            ]);

            return $tenant->refresh();
        });
    }
}
