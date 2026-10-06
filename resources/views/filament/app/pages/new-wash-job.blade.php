<div class="mx-auto max-w-3xl space-y-5 pb-24" x-on:restore-offline-job.window="$wire.set('plate', $event.detail.plate); $wire.set('notes', $event.detail.notes)">
    <div>
        <p class="text-sm font-medium text-primary-600">{{ $tenant?->name }} · {{ auth()->user()->branch?->name }}</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-950">{{ __('New wash job') }}</h1>
        <p class="mt-1 text-sm text-gray-500">Record a vehicle, services, team and payment.</p>
        <button type="button" x-data x-init="$el.hidden = ! localStorage.getItem('carbay.offlineJobDraft')" @click="$dispatch('restore-offline-job', JSON.parse(localStorage.getItem('carbay.offlineJobDraft') || '{}')); localStorage.removeItem('carbay.offlineJobDraft')" class="mt-2 min-h-11 rounded-lg px-3 text-sm font-semibold text-primary-700">
            Restore saved offline vehicle details
        </button>
    </div>

    <form wire:submit="submit" class="space-y-4">
        <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-6">
            <h2 class="text-base font-semibold">Vehicle</h2>
            <label class="mt-4 block text-sm font-medium">{{ __('Registration plate') }}</label>
            <div class="mt-1 flex gap-2">
                <input wire:model="plate" class="min-h-11 min-w-0 flex-1 rounded-xl border-gray-300 uppercase" placeholder="GR 1234-24" autocomplete="off">
                <label class="grid min-h-11 cursor-pointer place-items-center rounded-xl border border-gray-300 px-3 text-sm font-medium" title="Capture plate photo">
                    <span>Photo</span>
                    <input type="file" wire:model="photo" accept="image/*" capture="environment" class="sr-only">
                </label>
            </div>
            @error('plate') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
            @if ($photo)
                <div class="mt-3 flex items-center gap-3">
                    <img src="{{ $photo->temporaryUrl() }}" alt="Plate capture" class="h-16 w-24 rounded-lg object-cover">
                    <button type="button" wire:click="scanPlate" wire:loading.attr="disabled" class="min-h-11 rounded-lg px-3 text-sm font-semibold text-primary-600 disabled:opacity-60">
                        <span wire:loading.remove wire:target="scanPlate">{{ __('Scan plate') }}</span>
                        <span wire:loading wire:target="scanPlate">Scanning…</span>
                    </button>
                </div>
                    @if ($plateScanId)
                        <p class="mt-2 text-sm {{ $plateConfidence < 0.7 ? 'text-amber-700' : 'text-gray-600' }}">
                            Recognition confidence: {{ number_format($plateConfidence * 100, 1) }}%.
                            {{ $plateConfidence < 0.7 ? 'Confirm or correct the registration before saving.' : 'Check the registration before saving.' }}
                        </p>
                        <label class="mt-2 flex min-h-11 items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="plateConfirmed" class="rounded border-gray-300 text-primary-600">
                            {{ __('I confirm this registration is correct') }}
                        </label>
                    @endif
            @endif
            @error('photo') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror

            <label class="mt-4 block text-sm font-medium">Vehicle category</label>
            <select wire:model.live="vehicleCategoryId" class="mt-1 min-h-11 w-full rounded-xl border-gray-300">
                <option value="">Choose category</option>
                @foreach ($categories as $category) <option value="{{ $category->id }}">{{ $category->name }}</option> @endforeach
            </select>
            @error('vehicleCategoryId') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
            <div class="mt-2 flex gap-2">
                <input wire:model="customCategoryName" class="min-h-11 min-w-0 flex-1 rounded-xl border-gray-300 text-sm" placeholder="Add a company category">
                <button type="button" wire:click="addCustomCategory" class="min-h-11 rounded-xl border px-3 text-sm font-semibold">Add</button>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium">Make</label>
                    <select wire:model.live="makeId" class="mt-1 min-h-11 w-full rounded-xl border-gray-300" @disabled(! $vehicleCategoryId)>
                        <option value="">Optional</option>
                        @foreach ($makes as $make) <option value="{{ $make->id }}">{{ $make->name }}</option> @endforeach
                    </select>
                    <div class="mt-2 flex gap-2">
                        <input wire:model="customMakeName" class="min-h-11 min-w-0 flex-1 rounded-xl border-gray-300 text-sm" placeholder="Add a make" @disabled(! $vehicleCategoryId)>
                        <button type="button" wire:click="addCustomMake" class="min-h-11 rounded-xl border px-3 text-sm" @disabled(! $vehicleCategoryId)>Add</button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium">Model</label>
                    <select wire:model="modelId" class="mt-1 min-h-11 w-full rounded-xl border-gray-300" @disabled(! $makeId)>
                        <option value="">Optional</option>
                        @foreach ($models->where('vehicle_make_id', $makeId) as $model) <option value="{{ $model->id }}">{{ $model->name }}</option> @endforeach
                    </select>
                    <div class="mt-2 flex gap-2">
                        <input wire:model="customModelName" class="min-h-11 min-w-0 flex-1 rounded-xl border-gray-300 text-sm" placeholder="Add a model" @disabled(! $makeId)>
                        <button type="button" wire:click="addCustomModel" class="min-h-11 rounded-xl border px-3 text-sm" @disabled(! $makeId)>Add</button>
                    </div>
                    @error('modelId') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-6">
            <h2 class="text-base font-semibold">Client</h2>
            <div class="mt-3 flex gap-3">
                <button type="button" wire:click="$set('createClient', false)" class="min-h-11 rounded-lg px-2 text-sm font-semibold {{ ! $createClient ? 'text-primary-700' : 'text-gray-500' }}">Existing / walk-in</button>
                <button type="button" wire:click="$set('createClient', true); $set('clientId', null)" class="min-h-11 rounded-lg px-2 text-sm font-semibold {{ $createClient ? 'text-primary-700' : 'text-gray-500' }}">Add client</button>
            </div>
            @if ($createClient)
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <input wire:model="clientName" class="min-h-11 rounded-xl border-gray-300" placeholder="Client name">
                    <input wire:model="clientPhone" class="min-h-11 rounded-xl border-gray-300" placeholder="Phone number">
                    <input wire:model="clientEmail" type="email" class="min-h-11 rounded-xl border-gray-300 sm:col-span-2" placeholder="Email (for Paystack)">
                </div>
            @else
                <input wire:model.live.debounce.300ms="clientSearch" class="mt-3 min-h-11 w-full rounded-xl border-gray-300" placeholder="Search by name or phone; leave blank for walk-in">
                @if ($clients->isNotEmpty())
                    <div class="mt-2 overflow-hidden rounded-xl border">
                        @foreach ($clients as $client)
                            <button type="button" wire:click="chooseClient({{ $client->id }})" class="block min-h-11 w-full border-b px-3 py-2 text-left text-sm last:border-0 hover:bg-gray-50">{{ $client->name }} · {{ $client->phone }}</button>
                        @endforeach
                    </div>
                @endif
                @error('clientId') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
            @endif
        </section>

        <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-6">
            <h2 class="text-base font-semibold">Services</h2>
            @if (! $vehicleCategoryId)
                <p class="mt-3 text-sm text-gray-500">Choose a vehicle category to see available services.</p>
            @else
                <div class="mt-3 space-y-2">
                    @forelse ($services as $service)
                        <label class="flex min-h-11 items-center justify-between gap-3 rounded-xl border p-3">
                            <span class="flex items-center gap-3">
                                <input type="checkbox" wire:model.live="selectedServices" value="{{ $service['id'] }}" class="rounded border-gray-300 text-primary-600">
                                <span class="text-sm font-medium">{{ $service['name'] }}</span>
                            </span>
                            <span class="text-sm font-semibold">GH₵ {{ number_format($service['price'], 2) }}</span>
                        </label>
                    @empty
                        <p class="text-sm text-gray-500">No services are configured for this category.</p>
                    @endforelse
                </div>
            @endif
            @error('selectedServices') <p class="mt-2 text-sm text-danger-600">{{ $message }}</p> @enderror
        </section>

        <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-6">
            <h2 class="text-base font-semibold">Workers</h2>
            <p class="mt-1 text-sm text-gray-500">Worker shares split evenly by default and can be adjusted.</p>
            <div class="mt-3 space-y-2">
                @forelse ($workers as $worker)
                    <label class="flex min-h-11 items-center gap-3 rounded-xl border p-3">
                        <input type="checkbox" wire:model.live="selectedWorkers" value="{{ $worker->id }}" class="rounded border-gray-300 text-primary-600">
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium">{{ $worker->name }}</span>
                            <span class="text-xs text-gray-500">{{ ucfirst($worker->payout_mode ?? 'daily') }} payout</span>
                        </span>
                        @if (in_array($worker->id, array_map('intval', $selectedWorkers)))
                            <span class="flex items-center gap-1 text-xs text-gray-500">GH₵
                                <input type="number" min="0" step="0.01" wire:model.live="workerShares.{{ $worker->id }}" class="min-h-11 w-24 rounded-lg border-gray-300 px-2 py-1 text-right text-sm text-gray-900">
                            </span>
                        @endif
                    </label>
                @empty
                    <p class="text-sm text-gray-500">No active workers are assigned to this branch.</p>
                @endforelse
            </div>
            @error('selectedWorkers') <p class="mt-2 text-sm text-danger-600">{{ $message }}</p> @enderror
            @error('workerShares') <p class="mt-2 text-sm text-danger-600">{{ $message }}</p> @enderror
        </section>

        <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-6">
            <h2 class="text-base font-semibold">Payment</h2>
            <div class="mt-3 grid grid-cols-3 gap-2">
                @foreach (['cash' => 'Cash', 'momo' => 'MoMo', 'paystack' => 'Paystack'] as $method => $label)
                    <label class="flex min-h-11 cursor-pointer items-center justify-center rounded-xl border p-3 text-center text-sm font-semibold {{ $paymentMethod === $method ? 'border-primary-600 bg-primary-50 text-primary-700' : '' }}">
                        <input type="radio" wire:model.live="paymentMethod" value="{{ $method }}" class="sr-only">{{ $label }}
                    </label>
                @endforeach
            </div>
            @if ($paymentMethod === 'momo')
                <div class="mt-3 rounded-xl bg-gray-50 p-3 text-sm">
                    <p>Send to <strong>{{ $tenant?->momo_number ?: 'MoMo number not configured' }}</strong> · {{ $tenant?->momo_name }}</p>
                    <input wire:model="paymentReference" class="mt-3 min-h-11 w-full rounded-xl border-gray-300" placeholder="Manager-entered MoMo reference">
                    @error('paymentReference') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>
            @elseif ($paymentMethod === 'paystack')
                <p class="mt-3 text-sm text-gray-500">Customer will be redirected to Paystack. Payment is marked paid only after a signed gateway event.</p>
            @endif
        </section>

        <section class="rounded-2xl bg-gray-950 p-4 text-white shadow-sm sm:p-6">
            <h2 class="text-base font-semibold">Live breakdown</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt>Total</dt><dd class="font-bold">{{ \App\Support\Currency::format($breakdown['total']) }}</dd></div>
                <div class="flex justify-between text-gray-300"><dt>Company share</dt><dd>{{ \App\Support\Currency::format($breakdown['company']) }}</dd></div>
                <div class="flex justify-between text-gray-300"><dt>Worker share(s)</dt><dd>{{ \App\Support\Currency::format($breakdown['worker']) }}</dd></div>
                @if ($selectedWorkers)
                    <div class="flex justify-between text-gray-300"><dt>Per worker (initial split)</dt><dd>{{ \App\Support\Currency::format($breakdown['per_worker']) }}</dd></div>
                @endif
            </dl>
            @if ($selectedServices && ! $breakdown['allocated'])
                <p class="mt-3 text-sm text-amber-200">The selected service share settings must add up to 100% before this job can be saved.</p>
            @endif
        </section>

        <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-6">
            <label class="block text-sm font-medium">Job notes</label>
            <textarea wire:model="notes" rows="2" class="mt-1 min-h-11 w-full rounded-xl border-gray-300" placeholder="Optional notes"></textarea>
        </section>

        <div class="sticky bottom-0 z-10 rounded-2xl border bg-white/95 p-3 shadow-lg backdrop-blur">
            <button type="submit" wire:loading.attr="disabled" class="min-h-11 w-full rounded-xl bg-primary-600 px-4 py-3 font-semibold text-white disabled:opacity-60">
                <span wire:loading.remove>{{ __('Save wash job') }} · {{ \App\Support\Currency::format($breakdown['total']) }}</span>
                <span wire:loading>Saving…</span>
            </button>
        </div>
    </form>
</div>
