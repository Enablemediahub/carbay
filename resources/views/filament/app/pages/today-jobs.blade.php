<div class="mx-auto max-w-4xl space-y-5">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Today's jobs</h1>
        <p class="mt-1 text-sm text-gray-500">Find a vehicle and update its wash status.</p>
    </div>
    <input wire:model.live.debounce.300ms="search" class="w-full rounded-xl border-gray-300" placeholder="Search by plate">
    <div class="space-y-3">
        @forelse ($jobs as $job)
            <article class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold">{{ $job->plate }}</h2>
                        <p class="text-sm text-gray-500">{{ $job->created_at->format('g:i a') }} · {{ $job->services->pluck('service_name')->join(', ') }}</p>
                        <p class="mt-1 text-sm">{{ $job->client?->name ?? 'Walk-in' }} · {{ $job->workers->pluck('name')->join(', ') ?: 'No worker assigned' }}</p>
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
