<div class="mx-auto max-w-4xl space-y-5">
    <style>
        .worker-settlement { padding: 20px; border: 1px solid #c9dff3; border-radius: 20px; background: linear-gradient(135deg, #eff8ff, #fff); color: #153653; }
        .worker-settlement-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 14px; }
        .worker-settlement-totals { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 16px 0; font-size: 13px; }
        .worker-settlement-totals strong { display: block; font-size: 18px; margin-top: 5px; }
        .job-input { width: 100%; border-radius: 12px; border: 1px solid #cbd5e1; background: #fff; color: #153653; }
        .dark .worker-settlement { background: linear-gradient(135deg, #122d47, #172334); color: #e7f3ff; border-color: #34516c; }
        .dark .job-input { background: #172334; color: #e7f3ff; border-color: #34516c; }
    </style>
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Today's jobs</h1>
        <p class="mt-1 text-sm text-gray-500">Find a vehicle and update its wash status.</p>
    </div>
    <div class="grid gap-3 sm:grid-cols-2">
        <label>Worker<select wire:model.live="workerId" class="job-input"><option value="">All workers</option>@foreach ($workers as $worker)<option value="{{ $worker->id }}">{{ $worker->name }} · {{ $worker->phone }}</option>@endforeach</select></label>
        <label>Vehicle<input wire:model.live.debounce.300ms="search" class="job-input" placeholder="Search by plate"></label>
    </div>
    <div>
        <h2 class="text-lg font-bold">Today's worker payments</h2>
        <p class="mb-3 text-sm text-gray-500">Completed, paid jobs. Worker totals cover all their jobs today, independent of the plate search.</p>
        @error('payout')<p role="alert" class="mb-3 text-red-600">{{ $message }}</p>@enderror
        @error('reference')<p role="alert" class="mb-3 text-red-600">{{ $message }}</p>@enderror
        @error('method')<p role="alert" class="mb-3 text-red-600">{{ $message }}</p>@enderror
        <div class="worker-settlement-grid">
            @forelse ($workerTotals as $summary)
                @php($person = $summary['worker'])
                <section class="worker-settlement" wire:key="worker-payment-{{ $person->id }}">
                    <button type="button" wire:click="$set('workerId', '{{ $person->id }}')" class="text-lg font-bold hover:underline">{{ $person->name }}</button>
                    <div class="worker-settlement-totals">
                        <div>Earned<strong>GHS {{ number_format($summary['earned'], 2) }}</strong></div>
                        <div>Paid<strong>GHS {{ number_format($summary['paid'], 2) }}</strong></div>
                        <div>Still owed<strong>GHS {{ number_format($summary['owed'], 2) }}</strong></div>
                    </div>
                    @if ($summary['owed'] > 0)
                        <label class="text-sm">Payment method<select wire:model.live="paymentMethods.{{ $person->id }}" class="job-input mb-2"><option value="cash">Cash</option><option value="momo">Mobile Money</option></select></label>
                        @if (($paymentMethods[$person->id] ?? 'cash') === 'momo')
                            <label class="text-sm">Transfer reference<input wire:model="paymentReferences.{{ $person->id }}" maxlength="255" class="job-input mb-2"></label>
                        @endif
                        <button type="button" wire:click="payWorker({{ $person->id }}, {{ $summary['owed'] }})" wire:confirm="Confirm you have given {{ $person->name }} GHS {{ number_format($summary['owed'], 2) }}? This records a payment already made." wire:loading.attr="disabled" class="min-h-11 rounded-xl bg-primary-600 px-4 py-2 font-semibold text-white">Confirm payment · GHS {{ number_format($summary['owed'], 2) }}</button>
                    @else
                        <p class="font-semibold">Fully paid for today</p>
                    @endif
                </section>
            @empty
                <p class="text-sm text-gray-500">Worker earnings appear when paid jobs are completed.</p>
            @endforelse
        </div>
    </div>
    <div class="space-y-3">
        @forelse ($jobs as $job)
            <article class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold">{{ $job->plate ?: 'Standalone cleaning' }}</h2>
                        <p class="text-sm text-gray-500">{{ $job->created_at->format('g:i a') }} · {{ $job->services->pluck('service_name')->join(', ') }}</p>
                        <p class="mt-1 text-sm">{{ $job->client?->name ?? 'Walk-in' }} · {{ $job->workers->pluck('name')->join(', ') ?: 'No worker assigned' }}</p>
                        <p class="mt-2 text-sm">@foreach ($job->workers as $assigned){{ $assigned->name }}: GHS {{ number_format((float) $assigned->pivot->share_amount, 2) }}{{ $loop->last ? '' : ' · ' }}@endforeach</p>
                    </div>
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold uppercase">{{ $job->status }}</span>
                </div>
                <div class="mt-3 flex items-center justify-between border-t pt-3">
                    <div>
                        <p class="font-semibold">GH₵ {{ number_format((float) $job->total_amount, 2) }}</p>
                        <p class="text-xs text-gray-500">{{ ucfirst($job->payment_method) }} · payment {{ $job->payment_status }}</p>
                    </div>
                    @if ($job->status === 'open')
                        <div class="flex gap-2">
                            <button wire:click="complete({{ $job->id }})" class="min-h-11 rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white">Complete</button>
                            <button wire:click="cancel({{ $job->id }})" wire:confirm="Cancel this wash job?" class="min-h-11 rounded-lg border px-3 py-2 text-sm font-semibold">Cancel</button>
                        </div>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-2xl bg-white p-8 text-center text-sm text-gray-500">No jobs match your search today.</div>
        @endforelse
    </div>
</div>
