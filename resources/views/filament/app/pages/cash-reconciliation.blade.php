<div class="mx-auto max-w-xl space-y-5">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Cash reconciliation</h1>
        <p class="mt-1 text-sm text-gray-500">Compare paid cash jobs recorded by you with the cash counted.</p>
    </div>
    <form wire:submit="save" class="space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5">
        <label class="block text-sm font-medium">Business date
            <input type="date" wire:model.live="businessDate" max="{{ today()->toDateString() }}" class="mt-1 w-full rounded-xl border-gray-300">
        </label>
        <div class="rounded-xl bg-gray-50 p-4">
            <p class="text-sm text-gray-500">Expected cash for this manager</p>
            <p class="mt-1 text-2xl font-bold">GH₵ {{ number_format($expected, 2) }}</p>
        </div>
        <label class="block text-sm font-medium">Cash counted
            <input type="number" min="0" step="0.01" wire:model="enteredAmount" class="mt-1 w-full rounded-xl border-gray-300" placeholder="0.00">
        </label>
        @error('enteredAmount') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
        <label class="block text-sm font-medium">Notes
            <textarea wire:model="notes" rows="3" class="mt-1 w-full rounded-xl border-gray-300" placeholder="Explain any difference"></textarea>
        </label>
        <p class="text-sm font-semibold">Difference: GH₵ {{ number_format((float) $enteredAmount - $expected, 2) }}</p>
        <button class="w-full rounded-xl bg-primary-600 px-4 py-3 font-semibold text-white">Save reconciliation</button>
    </form>
</div>
