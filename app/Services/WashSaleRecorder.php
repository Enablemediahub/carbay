<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\FraudFlag;
use App\Models\ServicePrice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WashSale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WashSaleRecorder
{
    public function create(User $user, array $attributes): WashSale
    {
        if (! $user->can('create', WashSale::class)) {
            abort(403);
        }

        $tenant = Tenant::query()->findOrFail($user->tenant_id);
        $branch = Branch::query()->findOrFail($attributes['branch_id']);
        abort_unless($branch->tenant_id === $tenant->id, 403);

        if ($user->role === 'manager') {
            abort_unless($user->branch_id === $branch->id, 403);
        }

        if ($branch->is_addon_paid === false && ! $branch->is_main) {
            throw ValidationException::withMessages([
                'branch_id' => 'This branch add-on is awaiting payment and cannot record sales yet.',
            ]);
        }

        $paymentMethod = $attributes['payment_method'];

        if (! $tenant->getAttribute($paymentMethod.'_enabled')) {
            throw ValidationException::withMessages([
                'payment_method' => 'This payment method is disabled for your company.',
            ]);
        }

        $feature = match ($paymentMethod) {
            'cash' => 'cash_payment',
            'momo' => 'momo_manual',
            'paystack' => 'paystack',
        };

        if (! $tenant->hasFeature($feature)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Your package does not include this payment method.',
            ]);
        }

        $items = $attributes['items'] ?? [];

        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one service to the sale.']);
        }

        return DB::transaction(function () use ($attributes, $items, $user, $tenant, $branch, $paymentMethod): WashSale {
            $total = 0;
            $pricedItems = [];

            foreach ($items as $item) {
                $price = ServicePrice::query()
                    ->with(['service', 'vehicleCategory'])
                    ->where('is_active', true)
                    ->find($item['service_price_id'] ?? null);

                if (! $price || ! $price->service?->is_global || ! $price->service->is_active) {
                    throw ValidationException::withMessages([
                        'items' => 'Choose an active service and vehicle category offered by your company.',
                    ]);
                }

                $quantity = filter_var(
                    $item['quantity'] ?? null,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1, 'max_range' => 1000]],
                );

                if ($quantity === false) {
                    throw ValidationException::withMessages([
                        'items' => 'Service quantities must be whole numbers from 1 to 1,000.',
                    ]);
                }

                $lineTotal = (float) $price->price * $quantity;
                $total += $lineTotal;
                $pricedItems[] = [
                    'service_id' => $price->service_id,
                    'service_name' => $price->service->name.' ('.$price->vehicleCategory->name.')',
                    'quantity' => $quantity,
                    'unit_price' => $price->price,
                    'total_amount' => $lineTotal,
                ];
            }

            $workerId = $attributes['worker_id'] ?? null;

            if ($workerId !== null && ! $branch->workers()->whereKey($workerId)->exists()) {
                throw ValidationException::withMessages([
                    'worker_id' => 'Choose a worker assigned to the selected branch.',
                ]);
            }

            $sale = $tenant->branches()
                ->whereKey($branch->id)
                ->firstOrFail()
                ->sales()
                ->create([
                    'tenant_id' => $tenant->id,
                    'worker_id' => $workerId,
                    'reference' => 'CB-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                    'total_amount' => $total,
                    'payment_method' => $paymentMethod,
                    'payment_reference' => $attributes['payment_reference'] ?? null,
                    'status' => 'completed',
                    'sold_at' => now(),
                ]);

            $sale->items()->createMany($pricedItems);

            $paymentReference = trim((string) ($attributes['payment_reference'] ?? ''));

            if (
                $tenant->hasFeature('fraud_flags')
                && in_array($paymentMethod, ['momo', 'paystack'], true)
                && $paymentReference !== ''
                && WashSale::query()
                    ->where('payment_method', $paymentMethod)
                    ->where('payment_reference', $paymentReference)
                    ->whereKeyNot($sale->id)
                    ->exists()
            ) {
                FraudFlag::query()->create([
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'wash_sale_id' => $sale->id,
                    'flag_type' => 'duplicate_payment_reference',
                    'description' => 'This '.$paymentMethod.' payment reference was used on an earlier sale. Verify the payment with the provider before taking further action.',
                    'status' => 'open',
                ]);
            }

            return $sale->load('items');
        });
    }
}
