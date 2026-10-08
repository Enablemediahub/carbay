@push('scripts')
    @vite('resources/js/plate-camera.js')
@endpush

<div class="carbay-new-wash-job mx-auto max-w-3xl space-y-5 pb-24" x-on:restore-offline-job.window="$wire.set('plate', $event.detail.plate); $wire.set('notes', $event.detail.notes)" x-on:carbay-plate-scan.window="@this.call('acceptLocalPlateScan', $event.detail.plate, $event.detail.confidence)">
    <div>
        <p class="text-sm font-medium text-primary-600">{{ $tenant?->name }} · {{ auth()->user()->branch?->name }}</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-950">{{ $jobType === 'standalone' ? 'New cleaning job' : __('New wash job') }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ $jobType === 'standalone' ? 'Choose the worker and household cleaning services. Add client and location details as needed.' : 'Choose the worker, enter the number plate, select the vehicle type and services.' }}</p>
        <button type="button" x-data x-init="$el.hidden = ! localStorage.getItem('carbay.offlineJobDraft')" @click="$dispatch('restore-offline-job', JSON.parse(localStorage.getItem('carbay.offlineJobDraft') || '{}')); localStorage.removeItem('carbay.offlineJobDraft')" class="mt-2 min-h-11 rounded-lg px-3 text-sm font-semibold text-primary-700">
            Restore saved offline vehicle details
        </button>
    </div>

    <form wire:submit="submit" class="space-y-4">
        @include('filament.app.pages.partials.job-workers')
        <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-6">
            <label for="job-type" class="block text-sm font-semibold">Job type</label>
            <select id="job-type" wire:model.live="jobType" class="mt-2 min-h-11 w-full rounded-xl border-gray-300">
                <option value="vehicle">Vehicle washing / cleaning</option>
                <option value="standalone">Standalone cleaning — carpets, vacuuming and more</option>
            </select>
        </section>
        @if ($jobType === 'vehicle')
        <section class="min-w-0 space-y-5 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-6">
            <div>
                <h2 class="text-base font-semibold">Vehicle</h2>
                <p class="mt-1 text-sm text-gray-500">Add the registration and vehicle details.</p>
            </div>
            <div class="grid min-w-0 items-end gap-3 lg:grid-cols-[minmax(0,1fr)_auto]">
                <div class="min-w-0">
                    <label for="job-plate" class="block text-sm font-medium">{{ __('Registration plate') }} <span class="text-danger-600">*</span></label>
                    <input id="job-plate" wire:model="plate" class="mt-1 min-h-11 w-full rounded-xl border-gray-300 uppercase" placeholder="GR 1234-24" autocomplete="off">
                </div>
                <button type="button" data-carbay-start-camera class="min-h-11 w-full rounded-xl bg-primary-600 px-4 text-sm font-semibold text-white lg:w-auto">
                    Scan plate live
                </button>
            </div>
            <p class="-mt-3 text-xs text-gray-500">No photo upload needed. Live recognition runs on this device.</p>
            <div data-camera-error role="alert" class="mt-2 rounded-xl border border-red-300 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200" hidden></div>

            @error('plate') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror

            @if ($localPlateScanned || $plateConfidence > 0)
                <p class="mt-2 text-sm {{ $plateConfidence < 0.7 ? 'text-amber-700 dark:text-amber-300' : 'text-gray-600' }}">
                    On-device recognition confidence: {{ number_format($plateConfidence * 100, 1) }}%.
                    {{ $plateConfidence < 0.7 ? 'Confirm or correct the registration before saving.' : 'Check the registration before saving.' }}
                </p>
                @if ($plateConfidence < 0.7)
                    <label class="mt-2 flex min-h-11 items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="plateConfirmed" class="rounded border-gray-300 text-primary-600">
                        {{ __('I confirm this registration is correct') }}
                    </label>
                @endif
            @endif

            <div class="min-w-0">
                <label for="vehicle-category" class="block text-sm font-medium">Vehicle type <span class="text-danger-600">*</span></label>
                <select id="vehicle-category" wire:model.live="vehicleCategoryId" class="mt-1 min-h-11 w-full rounded-xl border-gray-300">
                    <option value="">Choose vehicle type</option>
                    @foreach ($categories as $category) <option value="{{ $category->id }}">{{ $category->name }}</option> @endforeach
                </select>
                @error('vehicleCategoryId') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror

            </div>

            <details>
                <summary class="min-h-11 cursor-pointer text-sm font-semibold">Additional vehicle details (optional)</summary>
                <div class="mt-3 space-y-4">
            <label class="inline-flex min-h-10 cursor-pointer items-center rounded-lg px-2 text-sm font-medium text-primary-700 dark:text-primary-300">
                Attach a vehicle photo (optional)
                <input type="file" wire:model="photo" accept="image/*" class="sr-only">
            </label>
            @if ($photo)
                <div class="mt-3 flex items-center gap-3">
                    <img src="{{ $photo->temporaryUrl() }}" alt="Captured vehicle image" class="h-16 w-24 rounded-lg object-cover">
                    <p class="text-sm text-gray-500">This image will be attached to the job only; use live scanning to read the plate.</p>
                </div>
            @endif
            @error('photo') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                <div class="mt-2 grid min-w-0 grid-cols-[minmax(0,1fr)_auto] gap-2">
                    <input wire:model="customCategoryName" aria-label="New vehicle category name" class="min-h-11 w-full min-w-0 rounded-xl border-gray-300 text-sm" placeholder="Add a company category">
                    <button type="button" wire:click="addCustomCategory" class="min-h-11 whitespace-nowrap rounded-xl border px-3 text-sm font-semibold">Add</button>
                </div>
            <div class="grid min-w-0 gap-4 lg:grid-cols-2">
                <div class="min-w-0">
                    <label for="vehicle-make" class="block text-sm font-medium">Make</label>
                    <select id="vehicle-make" wire:model.live="makeId" class="mt-1 min-h-11 w-full rounded-xl border-gray-300" @disabled(! $vehicleCategoryId)>
                        <option value="">Optional</option>
                        @foreach ($makes as $make) <option value="{{ $make->id }}">{{ $make->name }}</option> @endforeach
                    </select>
                    <div class="mt-2 grid min-w-0 grid-cols-[minmax(0,1fr)_auto] gap-2">
                        <input wire:model="customMakeName" aria-label="New vehicle make name" class="min-h-11 w-full min-w-0 rounded-xl border-gray-300 text-sm" placeholder="Add a make" @disabled(! $vehicleCategoryId)>
                        <button type="button" wire:click="addCustomMake" class="min-h-11 whitespace-nowrap rounded-xl border px-3 text-sm" @disabled(! $vehicleCategoryId)>Add</button>
                    </div>
                </div>
                <div class="min-w-0">
                    <label for="vehicle-model" class="block text-sm font-medium">Model</label>
                    <select id="vehicle-model" wire:model="modelId" class="mt-1 min-h-11 w-full rounded-xl border-gray-300" @disabled(! $makeId)>
                        <option value="">Optional</option>
                        @foreach ($models->where('vehicle_make_id', $makeId) as $model) <option value="{{ $model->id }}">{{ $model->name }}</option> @endforeach
                    </select>
                    <div class="mt-2 grid min-w-0 grid-cols-[minmax(0,1fr)_auto] gap-2">
                        <input wire:model="customModelName" aria-label="New vehicle model name" class="min-h-11 w-full min-w-0 rounded-xl border-gray-300 text-sm" placeholder="Add a model" @disabled(! $makeId)>
                        <button type="button" wire:click="addCustomModel" class="min-h-11 whitespace-nowrap rounded-xl border px-3 text-sm" @disabled(! $makeId)>Add</button>
                    </div>
                    @error('modelId') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                </div>
            </div>
                </div>
            </details>
        </section>



        @endif
        <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-6">
            <h2 class="text-base font-semibold">Services <span class="text-danger-600">*</span></h2>
            @if ($jobType === 'standalone')
                <p class="mt-1 text-sm text-gray-500">Household and standalone services. No vehicle type or registration plate is required. Add the location or carpet details in Job notes.</p>
            @elseif ($tenant?->service_pricing_mode === 'ghanaian')
                <p class="mt-1 text-sm text-gray-500">Ghanaian wash menu: select Body, Under, Engine or additional cleaning. The selected prices add up automatically.</p>
            @endif
            @if ($jobType === 'vehicle' && ! $vehicleCategoryId)
                <p class="mt-3 text-sm text-gray-500">Choose a vehicle category to see available services.</p>
            @else
                <div class="mt-3 space-y-2">
                    @forelse ($services as $service)
                        <label class="flex min-h-11 items-center justify-between gap-3 rounded-xl border p-3">
                            <span class="flex items-center gap-3">
                                <input type="checkbox" wire:model.live="selectedServices" value="{{ $service['id'] }}" class="rounded border-gray-300 text-primary-600">
                                <span class="text-sm font-medium">{{ $service['name'] }}<span class="block text-xs text-gray-500">Company {{ number_format($service['company_pct'], 0) }}% · Worker pool {{ number_format($service['worker_pct'], 0) }}%</span></span>
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



        <details @if ($createClient || $clientId || $clientSearch) open @endif>
            <summary class="carbay-client-summary min-h-11 cursor-pointer rounded-xl bg-white p-4 text-sm font-semibold">Client details (optional)</summary>
        <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-6">
            <h2 class="text-base font-semibold">Client</h2>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <button type="button" wire:click="selectClientMode(false)" class="min-h-11 rounded-lg px-2 text-sm font-semibold {{ ! $createClient ? 'text-primary-700' : 'text-gray-500' }}">Existing / walk-in</button>
                <button type="button" wire:click="selectClientMode(true)" class="min-h-11 rounded-lg px-2 text-sm font-semibold {{ $createClient ? 'text-primary-700' : 'text-gray-500' }}">Add client</button>
            </div>
            @if ($createClient)
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="client-name" class="block text-sm font-medium">Client name</label>
                        <input id="client-name" wire:model="clientName" class="mt-1 min-h-11 w-full rounded-xl border-gray-300" placeholder="Full name">
                        @error('clientName') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="client-phone" class="block text-sm font-medium">Phone number</label>
                        <input id="client-phone" wire:model="clientPhone" class="mt-1 min-h-11 w-full rounded-xl border-gray-300" placeholder="024 000 0000" inputmode="tel">
                        @error('clientPhone') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="client-email" class="block text-sm font-medium">Email <span class="font-normal text-gray-500">(for Paystack)</span></label>
                        <input id="client-email" wire:model="clientEmail" type="email" class="mt-1 min-h-11 w-full rounded-xl border-gray-300" placeholder="name@example.com">
                        @error('clientEmail') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            @else
                <label for="client-search" class="mt-3 block text-sm font-medium">Find client <span class="font-normal text-gray-500">(optional)</span></label>
                <input id="client-search" wire:model.live.debounce.300ms="clientSearch" class="mt-1 min-h-11 w-full rounded-xl border-gray-300" placeholder="Search by name or phone; leave blank for walk-in">
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
        </details>

        <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:p-6">
            <h2 class="text-base font-semibold">Payment</h2>
            <div class="mt-3 grid grid-cols-3 gap-2">
                @foreach (['cash' => 'Cash', 'momo' => 'MoMo', 'paystack' => 'Paystack'] as $method => $label)
                    <label class="carbay-payment-option flex min-h-11 cursor-pointer items-center justify-center rounded-xl border p-3 text-center text-sm font-semibold {{ $paymentMethod === $method ? 'border-primary-600 bg-primary-50 text-primary-700' : '' }}">
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

        <section class="carbay-job-breakdown rounded-2xl bg-gray-950 p-4 text-white shadow-sm sm:p-6">
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

        <div class="carbay-mobile-sticky-action sticky bottom-0 z-10 rounded-2xl border bg-white/95 p-3 shadow-lg backdrop-blur">
            <button type="submit" wire:loading.attr="disabled" class="min-h-11 w-full rounded-xl bg-primary-600 px-4 py-3 font-semibold text-white disabled:opacity-60">
                <span wire:loading.remove>{{ $jobType === 'standalone' ? 'Save cleaning job' : __('Save wash job') }} · {{ \App\Support\Currency::format($breakdown['total']) }}</span>
                <span wire:loading>Saving…</span>
            </button>
        </div>
    </form>

    <div wire:ignore data-camera-modal class="carbay-plate-camera-modal" role="dialog" aria-modal="true" aria-labelledby="carbay-plate-camera-title" hidden>
        <div class="carbay-plate-camera-panel">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="carbay-plate-camera-title" class="text-lg font-semibold">Live plate scanner</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-300">Keep the plate centered and steady. The camera stays on this device.</p>
                </div>
                <button type="button" data-carbay-stop-camera class="min-h-11 min-w-11 rounded-xl border px-3 text-sm font-semibold" aria-label="Close camera">Close</button>
            </div>
            <div class="carbay-plate-camera-view">
                <video data-camera-video autoplay muted playsinline aria-label="Live camera view"></video>
                <div class="carbay-plate-camera-guide" aria-hidden="true"></div>
            </div>
            <p data-camera-status class="mt-3 min-h-6 text-sm text-gray-600 dark:text-gray-300" aria-live="polite">Starting scanner…</p>
            <div class="carbay-camera-zoom" role="group" aria-label="Camera zoom">
                <button type="button" data-camera-zoom-out aria-label="Zoom out">−</button>
                <label for="camera-zoom">Zoom <output data-camera-zoom-value for="camera-zoom">1.0×</output></label>
                <input id="camera-zoom" data-camera-zoom type="range" min="1" max="4" step="0.1" value="1" aria-label="Camera zoom">
                <button type="button" data-camera-zoom-in aria-label="Zoom in">+</button>
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Use the slider or + and − to zoom. Keep the plate inside the guide and hold steady.</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Your video frames are processed locally and are not uploaded. The OCR engine downloads from a public CDN.</p>
            <button type="button" data-carbay-stop-camera class="mt-4 min-h-11 w-full rounded-xl border px-4 text-sm font-semibold">Stop scanning</button>
        </div>
    </div>
</div>
