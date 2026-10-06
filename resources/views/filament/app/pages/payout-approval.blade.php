<div class="mx-auto max-w-3xl space-y-5">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Payout approvals</h1>
        <p class="mt-1 text-sm text-gray-500">Review worker requests and scheduled payouts. Record cash payments or MoMo references.</p>
    </div>
    <div class="space-y-3">
        @forelse ($payouts as $payout)
            <article class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">{{ $payout->worker->name }}</h2>
                        <p class="text-sm text-gray-500">{{ ucfirst($payout->payout_mode) }} · {{ $payout->status === 'queued' ? 'Scheduled' : 'Worker request' }}</p>
                        @if ($payout->available_at)<p class="text-xs text-gray-500">Available {{ $payout->available_at->format('D j M, g:i a') }}</p>@endif
                    </div>
                    <strong>GH₵ {{ number_format((float) $payout->amount, 2) }}</strong>
                </div>
                <div class="mt-4 grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                    <select wire:model.live="methods.{{ $payout->id }}" class="min-h-11 rounded-xl border-gray-300 text-sm">
                        <option value="cash">Cash paid</option>
                        <option value="momo">MoMo sent</option>
                        @if (auth()->user()->tenant->hasFeature('paystack') && auth()->user()->tenant->paystack_enabled && auth()->user()->tenant->paystack_transfers_enabled)
                            <option value="paystack">Paystack transfer</option>
                        @endif
                    </select>
                    <input wire:model="references.{{ $payout->id }}" class="min-h-11 rounded-xl border-gray-300 text-sm" placeholder="{{ ($methods[$payout->id] ?? '') === 'paystack' ? 'Transfer recipient code' : 'MoMo reference (if MoMo)' }}">
                    <button wire:click="pay({{ $payout->id }})" wire:confirm="Mark this payout as paid?" class="min-h-11 rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white">Mark paid</button>
                </div>
                @error('references.'.$payout->id) <p class="mt-2 text-sm text-danger-600">{{ $message }}</p> @enderror
            </article>
        @empty
            <div class="rounded-2xl bg-white p-8 text-center text-sm text-gray-500">No pending payouts.</div>
        @endforelse
    </div>
</div>
