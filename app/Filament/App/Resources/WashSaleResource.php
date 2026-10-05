<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\WashSaleResource\Pages;
use App\Models\Branch;
use App\Models\ServicePrice;
use App\Models\WashSale;
use App\Models\Worker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class WashSaleResource extends Resource
{
    protected static ?string $model = WashSale::class;

    protected static ?string $navigationLabel = 'Sales';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Company';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('branch_id')
                ->options(fn (): array => Branch::query()->orderBy('name')->pluck('name', 'id')->all())
                ->rules([
                    Rule::exists('branches', 'id')
                        ->where('tenant_id', auth()->user()?->tenant_id)
                        ->when(
                            auth()->user()?->role === 'manager',
                            fn ($rule) => $rule->where('id', auth()->user()?->branch_id),
                        ),
                ])
                ->live()
                ->required(),
            Select::make('worker_id')
                ->options(fn ($get): array => Worker::query()
                    ->where('branch_id', $get('branch_id'))
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->rules([
                    Rule::exists('workers', 'id')->where('tenant_id', auth()->user()?->tenant_id),
                ])
                ->searchable(),
            Select::make('payment_method')
                ->options(fn (): array => self::availablePaymentMethods())
                ->required()
                ->rules(['required', Rule::in(array_keys(self::availablePaymentMethods()))]),
            TextInput::make('payment_reference')
                ->maxLength(255)
                ->visible(fn ($get): bool => in_array($get('payment_method'), ['momo', 'paystack'], true)),
            Repeater::make('items')
                ->schema([
                    Select::make('service_price_id')
                        ->label('Service · vehicle category')
                        ->options(fn (): array => ServicePrice::query()
                            ->with(['service', 'vehicleCategory'])
                            ->where('is_active', true)
                            ->get()
                            ->filter(fn (ServicePrice $price): bool => $price->service?->is_global
                                && $price->service->is_active)
                            ->mapWithKeys(fn (ServicePrice $price): array => [
                                $price->id => $price->service->name.' · '.$price->vehicleCategory->name
                                    .' · GH₵ '.number_format((float) $price->price, 2),
                            ])
                            ->all())
                        ->searchable()
                        ->required(),
                    TextInput::make('quantity')->numeric()->integer()->minValue(1)->maxValue(1000)->default(1)->required(),
                ])
                ->columns(2)
                ->minItems(1)
                ->required()
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->searchable()->sortable(),
                TextColumn::make('sold_at')->dateTime()->sortable(),
                TextColumn::make('branch.name')->label('Branch')->sortable(),
                TextColumn::make('worker.name')->label('Worker')->default('—'),
                TextColumn::make('items_count')->counts('items')->label('Services'),
                TextColumn::make('payment_method')->badge(),
                TextColumn::make('total_amount')->money('GHS')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('branch_id')->relationship('branch', 'name'),
                Tables\Filters\SelectFilter::make('payment_method')->options([
                    'cash' => 'Cash',
                    'momo' => 'Mobile Money',
                    'paystack' => 'Paystack',
                ]),
            ])
            ->defaultSort('sold_at', 'desc')
            ->actions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWashSales::route('/'),
            'create' => Pages\CreateWashSale::route('/create'),
        ];
    }

    private static function availablePaymentMethods(): array
    {
        $tenant = auth()->user()?->tenant;

        if (! $tenant) {
            return [];
        }

        $methods = [];

        foreach ([
            'cash' => ['cash_enabled', 'cash_payment', 'Cash'],
            'momo' => ['momo_enabled', 'momo_manual', 'Mobile Money'],
            'paystack' => ['paystack_enabled', 'paystack', 'Paystack'],
        ] as $method => [$enabledColumn, $feature, $label]) {
            if ($tenant->getAttribute($enabledColumn) && $tenant->hasFeature($feature)) {
                $methods[$method] = $label;
            }
        }

        return $methods;
    }
}
