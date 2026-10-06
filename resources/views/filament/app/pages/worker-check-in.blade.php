<div class="mx-auto max-w-2xl space-y-5">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Casual worker check-in</h1>
        <p class="mt-1 text-sm text-gray-500">Record attendance for today's casual team.</p>
    </div>
    <form wire:submit="checkIn" class="flex gap-2 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
        <select wire:model="workerId" class="min-w-0 flex-1 rounded-xl border-gray-300">
            <option value="">Choose casual worker</option>
            @foreach ($workers as $worker) <option value="{{ $worker->id }}">{{ $worker->name }}</option> @endforeach
        </select>
        <button class="rounded-xl bg-primary-600 px-4 font-semibold text-white">Check in</button>
    </form>
    @error('workerId') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
    <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5">
        <h2 class="border-b p-4 font-semibold">Today's attendance</h2>
        @forelse ($checkIns as $checkIn)
            <div class="flex items-center justify-between gap-3 border-b p-4 last:border-0">
                <div><p class="font-medium">{{ $checkIn->worker->name }}</p><p class="text-sm text-gray-500">{{ $checkIn->checked_in_at->format('g:i a') }} · {{ $checkIn->checked_out_at ? 'Checked out '.$checkIn->checked_out_at->format('g:i a') : 'On shift' }}</p></div>
                @if (! $checkIn->checked_out_at)<button wire:click="checkOut({{ $checkIn->worker_id }})" class="rounded-lg border px-3 py-2 text-sm font-semibold">Check out</button>@endif
            </div>
        @empty <p class="p-5 text-sm text-gray-500">No casual check-ins recorded today.</p> @endforelse
    </section>
</div>
