<?php

namespace App\Filament\App\Pages;

use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Tenant;
use App\Models\VehicleCategory;
use App\Support\WashPriceBoard;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GhanaianPricing extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationLabel = 'Ghanaian service pricing';

    protected static ?string $navigationGroup = 'Company';

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    protected static string $view = 'filament.app.pages.ghanaian-pricing';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'ceo' && auth()->user()?->tenant_id !== null;
    }

    public function mount(): void
    {
        $prices = ServicePrice::query()->whereIn('pricing_system', ['ghanaian', 'standalone'])->where('is_active', true)->with(['service', 'vehicleCategory'])->get();
        $rows = [];
        $extras = [];
        $standalone = [];
        foreach ($prices as $price) {
            $name = $price->service->name;
            if ($price->pricing_system === 'standalone') {
                $standalone[] = ['service_name' => $name, 'price' => $price->price];

                continue;
            }
            $type = $price->vehicleCategory->name;
            if (in_array($name, ['Body', 'Under', 'Engine'], true)) {
                $rows[$type] ??= ['vehicle_type' => $type];
                $rows[$type][strtolower($name)] = $price->price;
            } else {
                $extras[] = ['vehicle_type' => $type, 'service_name' => $name, 'price' => $price->price];
            }
        }
        $this->form->fill([
            'mode' => $this->tenant()->service_pricing_mode,
            'company_pct' => $prices->first()?->company_pct ?? 70,
            'worker_pct' => $prices->first()?->worker_pct ?? 30,
            'vehicles' => $prices->contains('pricing_system', 'ghanaian') ? array_values($rows) : WashPriceBoard::vehicles(),
            'extras' => $prices->contains('pricing_system', 'ghanaian') ? $extras : WashPriceBoard::extras(),
            'standalone' => $standalone ?: WashPriceBoard::standalone(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Choose your company service system')->schema([
                Radio::make('mode')->label('Active service system')->options([
                    'standard' => 'Standard services — use Services & prices',
                    'ghanaian' => 'Ghanaian wash menu — use the prices below',
                ])->required()->in(['standard', 'ghanaian'])
                    ->helperText('Standard uses services added from the shared catalogue. Ghanaian uses the vehicle wash table and additional services below. Both menus keep their prices when you switch.'),
            ]),
            Section::make('Vehicle wash prices (GH₵)')
                ->description('Enter Body, Under and Engine prices for each vehicle type. Leave unavailable options blank. Selected cleaning options are added together automatically.')
                ->schema([
                    Repeater::make('vehicles')->label('Vehicle types')->view('filament.forms.vehicle-price-table')->schema([
                        TextInput::make('vehicle_type')->required()->maxLength(100)->hiddenLabel(),
                        TextInput::make('body')->numeric()->minValue(0)->maxValue(99999999)->label('Body')->hiddenLabel()->live(onBlur: true),
                        TextInput::make('under')->numeric()->minValue(0)->maxValue(99999999)->label('Under')->hiddenLabel()->live(onBlur: true),
                        TextInput::make('engine')->numeric()->minValue(0)->maxValue(99999999)->label('Engine')->hiddenLabel()->live(onBlur: true),
                        Placeholder::make('total')->label('Combined total')->hiddenLabel()->content(fn (Get $get): string => 'GH₵ '.number_format((float) $get('body') + (float) $get('under') + (float) $get('engine'), 2)),
                    ])->addActionLabel('Add vehicle type')->reorderable(false),
                ]),
            Section::make('Additional cleaning services')->description('Choose carpet cleaning, vacuuming, detailing, seat cleaning, polishing, waxing and more. Set a price for each vehicle type, or add your own service.')->schema([
                Repeater::make('extras')->label('Additional services')->schema([
                    TextInput::make('vehicle_type')->required()->maxLength(100),
                    Select::make('service_name')->label('Cleaning service')->required()->searchable()
                        ->options(fn (): array => $this->cleaningServiceOptions())
                        ->getOptionLabelUsing(fn ($value): ?string => $value)
                        ->createOptionForm([TextInput::make('name')->label('Custom service name')->required()->maxLength(100)])
                        ->createOptionUsing(fn (array $data): string => trim($data['name']))
                        ->rules(['string', 'max:100']),
                    TextInput::make('price')->numeric()->minValue(0)->maxValue(99999999)->prefix('GH₵')->required(),
                ])->columns(3)->defaultItems(0)->addActionLabel('Add cleaning service')->reorderable(false),
            ]),
            Section::make('Standalone cleaning — no vehicle required')
                ->description('For sitting-room carpets, household vacuuming and other cleaning jobs. These services are available with either menu. Enter your own household prices; the image only gives vehicle vacuum prices. Blank prices keep a service unavailable.')
                ->schema([
                    Repeater::make('standalone')->label('Standalone services')->schema([
                        TextInput::make('service_name')->label('Service name')->required()->maxLength(100),
                        TextInput::make('price')->label('Price per job')->numeric()->minValue(0)->maxValue(99999999)->prefix('GH₵'),
                    ])->columns(2)->addActionLabel('Add standalone service')->reorderable(false),
                ]),
            Section::make('Company share settings')->description('These percentages apply to the Ghanaian menu and standalone cleaning services.')->schema([
                TextInput::make('company_pct')->label('Company share')->numeric()->minValue(0)->maxValue(100)->suffix('%')->required(),
                TextInput::make('worker_pct')->label('Worker pool share')->numeric()->minValue(0.01)->maxValue(100)->suffix('%')->required(),
            ])->columns(2),
        ])->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();
        if (abs((float) $data['company_pct'] + (float) $data['worker_pct'] - 100) > 0.001) {
            throw ValidationException::withMessages(['data.worker_pct' => 'Company and worker shares must add up to 100%.']);
        }
        $entries = [];
        foreach ($data['vehicles'] as $row) {
            foreach (['Body', 'Under', 'Engine'] as $service) {
                $amount = $row[strtolower($service)] ?? null;
                if ($amount !== null && $amount !== '') {
                    $entries[] = [trim($row['vehicle_type']), $service, $amount];
                }
            }
        }
        foreach ($data['extras'] as $row) {
            $entries[] = [trim($row['vehicle_type']), trim($row['service_name']), $row['price']];
        }
        $vehicleEntryCount = count($entries);
        foreach ($data['standalone'] ?? [] as $row) {
            if (($row['price'] ?? null) !== null && $row['price'] !== '') {
                $entries[] = [null, trim($row['service_name']), $row['price']];
            }
        }
        $keys = array_map(fn ($row) => strtolower($row[0].'|'.$row[1]), $entries);
        if (count($keys) !== count(array_unique($keys))) {
            throw ValidationException::withMessages(['data.vehicles' => 'Enter each vehicle and cleaning service once.']);
        }
        if ($data['mode'] === 'ghanaian' && ! $vehicleEntryCount) {
            throw ValidationException::withMessages(['data.vehicles' => 'Enter at least one price before enabling the Ghanaian menu.']);
        }
        DB::transaction(function () use ($data, $entries): void {
            $tenant = $this->tenant();
            ServicePrice::query()->whereIn('pricing_system', ['ghanaian', 'standalone'])->update(['is_active' => false]);
            foreach ($entries as [$type, $name, $amount]) {
                $category = $type === null ? null : VehicleCategory::firstOrCreate(['tenant_id' => $tenant->id, 'name' => $type], ['is_global' => false]);
                $service = Service::firstOrCreate(['tenant_id' => $tenant->id, 'name' => $name, 'is_global' => false], ['is_active' => true]);
                if (! $service->is_active) {
                    $service->update(['is_active' => true]);
                }
                ServicePrice::updateOrCreate([
                    'tenant_id' => $tenant->id, 'service_id' => $service->id, 'vehicle_category_id' => $category?->id,
                ], [
                    'pricing_system' => $type === null ? 'standalone' : 'ghanaian', 'price' => $amount, 'company_pct' => $data['company_pct'],
                    'worker_pct' => $data['worker_pct'], 'is_active' => true,
                ]);
            }
            $tenant->update(['service_pricing_mode' => $data['mode']]);
        });
        Notification::make()->success()->title('Service menu saved')->send();
    }

    private function tenant(): Tenant
    {
        return Tenant::withoutGlobalScopes()->findOrFail(auth()->user()->tenant_id);
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('priceBoard')->label('Fill missing prices from image')->action('fillFromPriceBoard')];
    }

    public function fillFromPriceBoard(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getRawState();
        $rows = collect($data['vehicles'] ?? [])->keyBy(fn ($row) => strtolower(trim($row['vehicle_type'] ?? '')))->all();
        foreach (WashPriceBoard::vehicles() as $sample) {
            $key = strtolower($sample['vehicle_type']);
            $rows[$key] ??= $sample;
            foreach (['body', 'under', 'engine'] as $field) {
                if (($rows[$key][$field] ?? null) === null || $rows[$key][$field] === '') {
                    $rows[$key][$field] = $sample[$field];
                }
            }
        }
        $extras = collect($data['extras'] ?? [])->keyBy(fn ($row) => strtolower(trim($row['vehicle_type']).'|'.trim($row['service_name'])))->all();
        foreach (WashPriceBoard::extras() as $sample) {
            $key = strtolower($sample['vehicle_type'].'|'.$sample['service_name']);
            $extras[$key] ??= $sample;
            if (($extras[$key]['price'] ?? null) === null || $extras[$key]['price'] === '') {
                $extras[$key]['price'] = $sample['price'];
            }
        }
        $standalone = collect($data['standalone'] ?? [])->keyBy(fn ($row) => strtolower(trim($row['service_name'])))->all();
        foreach (WashPriceBoard::standalone() as $sample) {
            $key = strtolower($sample['service_name']);
            $standalone[$key] ??= $sample;
            if (($standalone[$key]['price'] ?? null) === null || $standalone[$key]['price'] === '') {
                $standalone[$key]['price'] = $sample['price'];
            }
        }
        $this->form->fill([...$data, 'vehicles' => array_values($rows), 'extras' => array_values($extras), 'standalone' => array_values($standalone)]);
        Notification::make()->success()->title('Image prices filled in')->body('Review the prices, then save your service menu.')->send();
    }

    private function cleaningServiceOptions(): array
    {
        $names = [
            'Carpet cleaning', 'Vacuuming', 'Interior detailing', 'Exterior detailing', 'Full detailing',
            'Seat / upholstery cleaning', 'Roof lining cleaning', 'Dashboard cleaning', 'Steam cleaning',
            'Odour removal', 'Polishing', 'Waxing', 'Blowing', 'Headlight restoration',
            ...collect($this->data['extras'] ?? [])->pluck('service_name')->filter()->all(),
        ];

        return array_combine($names, $names);
    }
}
