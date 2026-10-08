<section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-6" aria-label="Select workers">
    <label for="job-worker" class="block text-base font-semibold">Worker name and phone number <span class="text-danger-600">*</span></label>
    <select id="job-worker" wire:model.live="workerToAdd" class="mt-2 min-h-11 w-full rounded-xl border-gray-300" @disabled($workers->isEmpty())>
        <option value="">{{ $selectedWorkers ? 'Add another worker (optional)' : 'Choose a worker' }}</option>
        @foreach ($workers as $worker)
            @unless (in_array($worker->id, array_map('intval', $selectedWorkers)))
                <option value="{{ $worker->id }}">{{ $worker->name }} — {{ $worker->phone ?: 'No phone saved' }}</option>
            @endunless
        @endforeach
    </select>
    @if ($workers->isEmpty())
        <p class="mt-2 text-sm text-amber-700">No active workers are assigned to this branch. Add or activate a worker first.</p>
    @endif
    <div class="mt-3 space-y-2">
        @foreach ($workers->whereIn('id', array_map('intval', $selectedWorkers)) as $worker)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3">
                <div class="min-w-0">
                    <strong class="block text-sm">{{ $worker->name }}</strong>
                    <span class="block text-xs text-gray-500">{{ $worker->phone ?: 'No phone saved' }}</span>
                    @if ($selectedServices)
                        <span class="block text-xs text-gray-500">Company-calculated share: {{ \App\Support\Currency::format((float) ($workerShares[$worker->id] ?? 0)) }}</span>
                    @endif
                </div>
                <button type="button" wire:click="removeWorker({{ $worker->id }})" class="min-h-11 rounded-lg px-3 text-sm font-medium text-gray-500" aria-label="Remove {{ $worker->name }}">Remove</button>
            </div>
        @endforeach
    </div>
    @error('selectedWorkers') <p class="mt-2 text-sm text-danger-600">{{ $message }}</p> @enderror
    @error('workerToAdd') <p class="mt-2 text-sm text-danger-600">{{ $message }}</p> @enderror
</section>
