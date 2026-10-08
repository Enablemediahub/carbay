<?php

namespace App\Filament\App\Pages;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Job;
use App\Models\JobWorker;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Tenant;
use App\Models\VehicleCategory;
use App\Models\VehicleMake;
use App\Models\VehicleModel;
use App\Models\Worker;
use App\Services\JobFraudInspector;
use App\Services\PaystackService;
use App\Services\PlateOcrService;
use App\Services\WalletService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\WithFileUploads;

class NewWashJob extends Page
{
    use WithFileUploads;

    protected static string $view = 'filament.app.pages.new-wash-job';

    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?string $navigationGroup = 'Manager';

    protected static ?string $navigationLabel = 'New wash job';

    protected static ?string $title = 'New wash job';

    protected static ?int $navigationSort = 1;

    public string $plate = '';

    public string $jobType = 'vehicle';

    public function updatedJobType(): void
    {
        $this->reset('plate', 'vehicleCategoryId', 'makeId', 'modelId', 'selectedServices', 'plateConfidence', 'plateConfirmed', 'localPlateScanned');
        $this->synchronizeWorkerShares();
        $this->resetValidation();
    }

    public ?int $plateScanId = null;

    public float $plateConfidence = 0;

    public bool $plateConfirmed = false;

    public bool $localPlateScanned = false;

    public ?string $vehicleCategoryId = null;

    public ?string $makeId = null;

    public ?string $modelId = null;

    public string $clientSearch = '';

    public ?int $clientId = null;

    public bool $createClient = false;

    public string $clientName = '';

    public string $clientPhone = '';

    public string $clientEmail = '';

    public array $selectedServices = [];

    public array $selectedWorkers = [];

    public ?string $workerToAdd = null;

    public array $workerShares = [];

    public string $paymentMethod = 'cash';

    public string $paymentReference = '';

    public string $notes = '';

    public $photo;

    public string $customCategoryName = '';

    public string $customMakeName = '';

    public string $customModelName = '';

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['manager', 'ceo'], true), 403);
        abort_unless(auth()->user()?->tenant_id && auth()->user()?->branch_id, 403);
    }

    public function updatedVehicleCategoryId(): void
    {
        $this->selectedServices = [];
        $this->makeId = null;
        $this->modelId = null;
        $this->synchronizeWorkerShares();
    }

    public function updatedMakeId(): void
    {
        $this->modelId = null;
    }

    public function updatedClientSearch(): void
    {
        $this->clientId = null;
    }

    public function updatedPlate(): void
    {
        $this->plateConfidence = 0;
        $this->plateConfirmed = false;
        $this->localPlateScanned = false;
    }

    public function updatedSelectedWorkers(): void
    {
        $this->synchronizeWorkerShares();
    }

    public function updatedWorkerToAdd(): void
    {
        if (! filled($this->workerToAdd)) {
            return;
        }
        $worker = $this->availableWorkers()->findOrFail($this->workerToAdd);
        $this->selectedWorkers = array_values(array_unique([
            ...array_map('intval', $this->selectedWorkers), $worker->id,
        ]));
        $this->workerToAdd = null;
        $this->synchronizeWorkerShares();
    }

    public function removeWorker(int $workerId): void
    {
        $this->selectedWorkers = array_values(array_filter(
            $this->selectedWorkers, fn ($id): bool => (int) $id !== $workerId,
        ));
        $this->synchronizeWorkerShares();
    }

    public function updatedSelectedServices(): void
    {
        $this->synchronizeWorkerShares();
    }

    public function updatedPaymentMethod(): void
    {
        $this->paymentReference = '';
    }

    public function acceptLocalPlateScan(string $plate, float $confidence): void
    {
        $plate = strtoupper(trim($plate));
        validator(
            ['plate' => $plate, 'confidence' => $confidence],
            [
                'plate' => ['required', 'string', 'max:20', 'regex:/^(?:[A-Z]{2}\s?\d{3,4}\s?[A-Z]?|[A-Z]{2}\s?\d{4}-\d{2}|[A-Z]{2}\s?\d{4}\s?[A-Z])$/'],
                'confidence' => ['required', 'numeric', 'between:0,1'],
            ],
        )->validate();

        $this->plate = $plate;
        $this->plateScanId = null;
        $this->plateConfidence = $confidence;
        $this->plateConfirmed = $confidence >= 0.7;
        $this->localPlateScanned = true;
    }

    public function addCustomCategory(): void
    {
        $name = trim($this->customCategoryName);
        validator(['name' => $name], ['name' => ['required', 'string', 'max:100']])->validate();
        $category = $this->createOrFindCustom(VehicleCategory::class, $name);
        $this->vehicleCategoryId = (string) $category->id;
        $this->customCategoryName = '';
        $this->updatedVehicleCategoryId();
    }

    public function addCustomMake(): void
    {
        if (! $this->vehicleCategoryId) {
            throw ValidationException::withMessages(['vehicleCategoryId' => 'Choose a vehicle category before adding a make.']);
        }

        $this->availableCategories()->findOrFail($this->vehicleCategoryId);
        $name = trim($this->customMakeName);
        validator(['name' => $name], ['name' => ['required', 'string', 'max:100']])->validate();
        $make = $this->createOrFindCustom(VehicleMake::class, $name);
        $this->makeId = (string) $make->id;
        $this->customMakeName = '';
        $this->modelId = null;
    }

    public function addCustomModel(): void
    {
        if (! $this->makeId) {
            throw ValidationException::withMessages(['makeId' => 'Choose a make before adding a model.']);
        }

        $name = trim($this->customModelName);
        validator(['name' => $name], ['name' => ['required', 'string', 'max:100']])->validate();
        $make = $this->availableMakes()->findOrFail($this->makeId);
        $model = VehicleModel::query()->firstOrCreate(
            ['vehicle_make_id' => $make->id, 'name' => $name],
            ['tenant_id' => auth()->user()->tenant_id, 'is_global' => false],
        );
        if ($model->tenant_id !== null && $model->tenant_id !== auth()->user()->tenant_id) {
            throw ValidationException::withMessages(['customModelName' => 'That model is already registered by another company.']);
        }
        $this->modelId = (string) $model->id;
        $this->customModelName = '';
    }

    public function selectClientMode(bool $create): void
    {
        $this->createClient = $create;
        $this->clientId = null;
        $this->clientSearch = '';
        $this->resetValidation(['clientName', 'clientPhone', 'clientEmail', 'clientId']);
    }

    public function chooseClient(int $clientId): void
    {
        $client = Client::query()->findOrFail($clientId);
        $this->clientId = $client->id;
        $this->clientSearch = $client->name.' · '.($client->phone ?: 'no phone');
        $this->createClient = false;
    }

    public function submit(WalletService $walletService, PaystackService $paystack): mixed
    {
        $this->plate = strtoupper(trim($this->plate));
        $this->validate([
            'jobType' => ['required', 'in:vehicle,standalone'],
            'plate' => $this->jobType === 'standalone' ? ['nullable'] : ['required', 'string', 'max:20', 'regex:/^(?:[A-Z]{2}\s?\d{3,4}\s?[A-Z]?|[A-Z]{2}\s?\d{4}-\d{2}|[A-Z]{2}\s?\d{4}\s?[A-Z])$/'],
            'vehicleCategoryId' => [$this->jobType === 'standalone' ? 'nullable' : 'required', 'integer'],
            'makeId' => ['nullable', 'integer'],
            'modelId' => ['nullable', 'integer'],
            'clientId' => ['nullable', 'integer'],
            'clientName' => [$this->createClient ? 'required' : 'nullable', 'string', 'max:255'],
            'clientPhone' => [$this->createClient ? 'required' : 'nullable', 'string', 'max:30'],
            'clientEmail' => ['nullable', 'email', 'max:255'],
            'selectedServices' => ['required', 'array', 'min:1'],
            'selectedServices.*' => ['integer'],
            'selectedWorkers' => ['required', 'array', 'min:1'],
            'selectedWorkers.*' => ['integer'],
            'workerShares' => ['array'],
            'paymentMethod' => ['required', 'in:cash,momo,paystack'],
            'paymentReference' => [$this->paymentMethod === 'momo' ? 'required' : 'nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($this->jobType === 'vehicle' && ($this->localPlateScanned || $this->plateConfidence > 0) && $this->plateConfidence < 0.7 && ! $this->plateConfirmed) {
            throw ValidationException::withMessages(['plate' => 'Confirm or correct this low-confidence plate scan before saving.']);
        }

        $user = auth()->user();
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($user->tenant_id);
        $branch = Branch::query()->whereKey($user->branch_id)->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertPaymentEnabled($tenant);
        $category = $this->jobType === 'standalone' ? null : $this->availableCategories()->findOrFail($this->vehicleCategoryId);
        $make = $this->jobType === 'vehicle' && $this->makeId ? $this->availableMakes()->findOrFail($this->makeId) : null;
        $model = $this->jobType === 'vehicle' && $this->modelId ? $this->availableModels()->findOrFail($this->modelId) : null;
        if ($model && (! $make || (int) $model->vehicle_make_id !== (int) $make->id)) {
            throw ValidationException::withMessages(['modelId' => 'Choose a model that belongs to the selected make.']);
        }

        $items = $this->pricedItems();
        if ($items === []) {
            throw ValidationException::withMessages(['selectedServices' => 'Add an active service for this vehicle category before recording the job.']);
        }
        foreach ($items as $item) {
            if (abs($item['company_pct'] + $item['worker_pct'] - 100) > 0.01) {
                throw ValidationException::withMessages([
                    'selectedServices' => 'Each selected service must allocate 100% between company and workers. Update the service share settings and try again.',
                ]);
            }
        }
        $total = array_sum(array_column($items, 'total_amount'));
        $workerShare = array_sum(array_column($items, 'worker_share'));
        if ($workerShare <= 0) {
            throw ValidationException::withMessages([
                'selectedServices' => 'Selected services must allocate a positive share to workers before this job can be saved.',
            ]);
        }
        $workers = $this->availableWorkers()->whereIn('id', $this->selectedWorkers)->get();
        if ($workers->count() !== count(array_unique(array_map('intval', $this->selectedWorkers)))) {
            throw ValidationException::withMessages(['selectedWorkers' => 'Choose only active workers assigned to this branch.']);
        }
        $shares = $this->companyWorkerShares($workers->pluck('id')->all(), $workerShare);
        $clientId = $this->clientId;
        if (! $this->createClient && $clientId && ! Client::query()->whereKey($clientId)->exists()) {
            throw ValidationException::withMessages(['clientId' => 'Choose a client belonging to this company.']);
        }

        $photoPath = $this->photo?->store('jobs', 'public');
        $reference = $this->paymentMethod === 'paystack'
            ? 'CB-'.now()->format('Ymd').'-'.Str::upper(Str::random(12))
            : ($this->paymentMethod === 'momo' ? trim($this->paymentReference) : null);
        $isPaid = $this->paymentMethod !== 'paystack';

        $job = DB::transaction(function () use (
            $user, $tenant, $branch, $category, $make, $model, $items,
            $total, $workers, $shares, $photoPath, $reference, $isPaid, $clientId
        ): Job {
            if ($this->createClient) {
                $clientId = Client::query()->create([
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'name' => trim($this->clientName),
                    'phone' => trim($this->clientPhone),
                    'email' => $this->clientEmail ?: null,
                ])->id;
            }

            $job = Job::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'manager_id' => $user->id,
                'client_id' => $clientId,
                'job_type' => $this->jobType,
                'plate' => $this->jobType === 'standalone' ? null : $this->plate,
                'vehicle_category_id' => $category?->id,
                'make_id' => $make?->id,
                'model_id' => $model?->id,
                'total_amount' => $total,
                'payment_method' => $this->paymentMethod,
                'payment_ref' => $reference,
                'payment_status' => $isPaid ? 'paid' : 'pending',
                'status' => 'open',
                'notes' => $this->notes ?: null,
                'photo_path' => $photoPath,
            ]);

            $job->services()->createMany($items);
            foreach ($workers as $worker) {
                $jobWorker = JobWorker::query()->create([
                    'job_id' => $job->id,
                    'worker_id' => $worker->id,
                    'share_amount' => $shares[$worker->id],
                    'payout_mode' => $worker->payout_mode ?? 'daily',
                ]);
                if ($isPaid && (float) $jobWorker->share_amount > 0) {
                    app(WalletService::class)->credit($worker, $job, (float) $jobWorker->share_amount, $jobWorker->payout_mode, $jobWorker);
                }
            }
            app(JobFraudInspector::class)->inspect($job);

            Payment::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'job_id' => $job->id,
                'manager_id' => $user->id,
                'amount' => $total,
                'method' => $this->paymentMethod === 'momo' ? 'momo_manual' : $this->paymentMethod,
                'reference' => $reference,
                'status' => $isPaid ? 'paid' : 'pending',
            ]);

            if ($this->plateScanId) {
                app(PlateOcrService::class)->confirmCorrection($this->plateScanId, $job, $this->plate);
            }

            return $job;
        });

        if ($this->paymentMethod === 'paystack') {
            try {
                $checkoutUrl = $paystack->initialize(
                    $total,
                    $job->client?->email ?: $this->clientEmail ?: $tenant->email,
                    (string) $reference,
                );
                $job->payments()->latest()->firstOrFail()->update([
                    'gateway_response_json' => ['authorization_url' => $checkoutUrl],
                ]);
            } catch (ValidationException $exception) {
                $job->update(['payment_status' => 'failed']);
                $job->payments()->latest()->firstOrFail()->update(['status' => 'failed']);
                throw $exception;
            }

            return $this->redirect($checkoutUrl, navigate: false);
        }

        Notification::make()->title('Wash job recorded')->success()->send();
        $this->reset([
            'jobType', 'plate', 'plateScanId', 'plateConfidence', 'plateConfirmed', 'localPlateScanned',
            'vehicleCategoryId', 'makeId', 'modelId', 'clientSearch',
            'clientId', 'createClient', 'clientName', 'clientPhone', 'clientEmail',
            'selectedServices', 'selectedWorkers', 'workerToAdd', 'workerShares',
            'paymentReference', 'notes', 'photo',
        ]);
        $this->paymentMethod = 'cash';

        return null;
    }

    protected function getViewData(): array
    {
        return [
            'categories' => $this->availableCategories()->orderBy('name')->get(),
            'makes' => $this->availableMakes()->orderBy('name')->get(),
            'models' => $this->availableModels()->orderBy('name')->get(),
            'services' => $this->servicesForCategory(),
            'workers' => $this->availableWorkers()->orderBy('name')->get(),
            'clients' => $this->matchingClients(),
            'breakdown' => $this->breakdown(),
            'tenant' => Tenant::withoutGlobalScopes()->find(auth()->user()->tenant_id),
        ];
    }

    private function availableCategories()
    {
        return VehicleCategory::query()->where(function ($query): void {
            $query->whereNull('tenant_id')->orWhere('tenant_id', auth()->user()->tenant_id);
        })->when(Tenant::withoutGlobalScopes()->findOrFail(auth()->user()->tenant_id)->service_pricing_mode === 'ghanaian', fn ($query) => $query->whereHas('servicePrices', fn ($prices) => $prices
            ->withoutGlobalScopes()->where('tenant_id', auth()->user()->tenant_id)->where('pricing_system', 'ghanaian')->where('is_active', true)
            ->whereHas('service', fn ($service) => $service->where('is_active', true))));
    }

    private function availableMakes()
    {
        return VehicleMake::query()->where(function ($query): void {
            $query->whereNull('tenant_id')->orWhere('tenant_id', auth()->user()->tenant_id);
        });
    }

    private function availableModels()
    {
        return VehicleModel::query()->where(function ($query): void {
            $query->whereNull('tenant_id')->orWhere('tenant_id', auth()->user()->tenant_id);
        });
    }

    private function availableWorkers()
    {
        return Worker::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('branch_id', auth()->user()->branch_id)
            ->where('status', 'active');
    }

    private function servicesForCategory()
    {
        if ($this->jobType === 'vehicle' && ! $this->vehicleCategoryId) {
            return collect();
        }

        $prices = ServicePrice::withoutGlobalScopes()
            ->with('service')
            ->where('pricing_system', $this->jobType === 'standalone' ? 'standalone' : Tenant::withoutGlobalScopes()->findOrFail(auth()->user()->tenant_id)->service_pricing_mode)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('vehicle_category_id', $this->jobType === 'standalone' ? null : $this->vehicleCategoryId)
            ->where('is_active', true)
            ->get()
            ->keyBy('service_id');

        return Service::query()->where('is_active', true)->whereIn('id', $prices->keys())
            ->where(function ($query): void {
                $query->where('is_global', true)->orWhere('tenant_id', auth()->user()->tenant_id);
            })
            ->orderBy('name')->get()->map(function (Service $service) use ($prices): array {
                $price = $prices->get($service->id);

                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'price' => (float) $price->price,
                    'worker_pct' => (float) $price->worker_pct,
                    'company_pct' => (float) $price->company_pct,
                ];
            });
    }

    private function pricedItems(): array
    {
        $catalog = $this->servicesForCategory()->keyBy('id');
        $selected = array_unique(array_map('intval', $this->selectedServices));
        $items = [];
        foreach ($selected as $serviceId) {
            $service = $catalog->get($serviceId);
            if (! $service) {
                throw ValidationException::withMessages(['selectedServices' => 'Choose active services offered by this company.']);
            }
            $total = round($service['price'], 2);
            $workerShare = round($total * $service['worker_pct'] / 100, 2);
            $items[] = [
                'service_id' => $service['id'],
                'service_name' => $service['name'],
                'quantity' => 1,
                'unit_price' => $total,
                'total_amount' => $total,
                'worker_share' => $workerShare,
                'company_share' => round($total * $service['company_pct'] / 100, 2),
                'worker_pct' => $service['worker_pct'],
                'company_pct' => $service['company_pct'],
            ];
        }

        return $items;
    }

    private function breakdown(): array
    {
        $items = $this->jobType === 'standalone' || $this->vehicleCategoryId
            ? $this->pricedItems()
            : [];
        $total = array_sum(array_column($items, 'total_amount'));
        $companyShare = array_sum(array_column($items, 'company_share'));
        $workerShare = array_sum(array_column($items, 'worker_share'));

        return [
            'total' => round($total, 2),
            'company' => round($companyShare, 2),
            'worker' => round($workerShare, 2),
            'allocated' => abs($total - $companyShare - $workerShare) <= 0.01,
            'per_worker' => count($this->selectedWorkers) ? round($workerShare / count($this->selectedWorkers), 2) : 0,
        ];
    }

    private function matchingClients()
    {
        $term = trim($this->clientSearch);
        if ($term === '' || $this->clientId) {
            return collect();
        }

        return Client::query()
            ->where(fn ($query) => $query->where('name', 'like', '%'.$term.'%')->orWhere('phone', 'like', '%'.$term.'%'))
            ->orderBy('name')->limit(6)->get();
    }

    private function synchronizeWorkerShares(): void
    {
        $selected = array_unique(array_map('intval', $this->selectedWorkers));
        sort($selected);
        $share = (float) $this->breakdown()['worker'];
        $base = count($selected) ? floor(($share / count($selected)) * 100) / 100 : 0;
        $this->workerShares = [];
        foreach ($selected as $index => $workerId) {
            $this->workerShares[$workerId] = $index === count($selected) - 1
                ? round($share - $base * (count($selected) - 1), 2)
                : $base;
        }
    }

    private function companyWorkerShares(array $workerIds, float $totalShare): array
    {
        sort($workerIds);
        $base = count($workerIds) ? floor(($totalShare / count($workerIds)) * 100) / 100 : 0;
        $shares = [];
        foreach ($workerIds as $index => $id) {
            $share = $index === count($workerIds) - 1
                ? round($totalShare - $base * (count($workerIds) - 1), 2)
                : $base;
            if ($share <= 0) {
                throw ValidationException::withMessages(['selectedWorkers' => 'The company worker share is too small for this many workers. Assign fewer workers.']);
            }
            $shares[$id] = $share;
        }

        return $shares;
    }

    private function createOrFindCustom(string $modelClass, string $name): VehicleCategory|VehicleMake
    {
        $existing = $modelClass::query()->where('name', $name)->first();
        if ($existing) {
            if ($existing->tenant_id !== null && $existing->tenant_id !== auth()->user()->tenant_id) {
                throw ValidationException::withMessages(['customName' => 'That name is already used by another company.']);
            }

            return $existing;
        }

        return $modelClass::query()->create([
            'name' => $name,
            'tenant_id' => auth()->user()->tenant_id,
            'is_global' => false,
        ]);
    }

    private function assertPaymentEnabled(Tenant $tenant): void
    {
        $method = $this->paymentMethod;
        if (! $tenant->getAttribute($method.'_enabled')) {
            throw ValidationException::withMessages(['paymentMethod' => 'This payment method is disabled for your company.']);
        }

        $feature = match ($method) {
            'cash' => 'cash_payment',
            'momo' => 'momo_manual',
            'paystack' => 'paystack',
        };
        if (! $tenant->hasFeature($feature)) {
            throw ValidationException::withMessages(['paymentMethod' => 'Your package does not include this payment method.']);
        }
    }
}
