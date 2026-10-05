<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchAddonInvoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BranchProvisioner
{
    public function create(User $user, array $attributes): Branch
    {
        if (! $user->can('create', Branch::class)) {
            abort(403);
        }

        return DB::transaction(function () use ($user, $attributes): Branch {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($user->tenant_id);
            $branchCount = $tenant->branches()->lockForUpdate()->count();
            $includedLimit = $tenant->featureLimit('multi_branch') ?? 1;
            $hasIncludedSlot = $tenant->hasFeature('multi_branch')
                && $branchCount < $includedLimit;

            $branch = $tenant->branches()->create([
                'name' => $attributes['name'],
                'address' => $attributes['address'] ?? null,
                'phone' => $attributes['phone'] ?? null,
                'is_main' => false,
                'status' => 'active',
                'is_addon_paid' => $hasIncludedSlot,
            ]);

            if (! $hasIncludedSlot) {
                $addonPrice = $tenant->package?->branch_addon_price;

                if ($addonPrice === null) {
                    throw ValidationException::withMessages([
                        'branch' => 'Assign an active package before adding a billable branch.',
                    ]);
                }

                BranchAddonInvoice::query()->create([
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'invoice_number' => 'BAI-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                    'amount' => $addonPrice,
                    'currency' => 'GHS',
                    'status' => 'pending',
                ]);
            }

            return $branch;
        });
    }
}
