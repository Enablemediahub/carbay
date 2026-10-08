<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\ServicePriceResource\Pages;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\VehicleCategory;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class ServicePriceResource extends Resource
{
    protected static ?string $model = ServicePrice::class;

    protected static ?string $navigationLabel = 'Services & prices';

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Company';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('service_id')
                ->label('Shared catalogue service')
                ->helperText('Add this service to your company’s Standard menu, then set your company price and share percentages.')
                ->options(fn (): array => Service::query()->global()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->rules([
                    Rule::exists('services', 'id')
                        ->where('is_global', true)
                        ->where('is_active', true),
                ])
                ->live()
                ->afterStateUpdated(function ($state, callable $set): void {
                    $service = Service::query()->global()->find($state);

                    if ($service) {
                        $set('price', $service->default_price);
                        $set('company_pct', $service->company_pct);
                        $set('worker_pct', $service->worker_pct);
                    }
                })
                ->required(),
            Select::make('vehicle_category_id')
                ->options(fn (): array => VehicleCategory::query()->where('is_global', true)->orderBy('name')->pluck('name', 'id')->all())
                ->rules([Rule::exists('vehicle_categories', 'id')->where('is_global', true)])
                ->searchable()
                ->required(),
            TextInput::make('price')->numeric()->prefix('GH₵')->required()->minValue(0),
            TextInput::make('company_pct')->numeric()->suffix('%')->required()->minValue(0)->maxValue(100),
            TextInput::make('worker_pct')->numeric()->suffix('%')->required()->minValue(0)->maxValue(100),
            Toggle::make('is_active')->default(true)->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('service.name')->label('Service')->searchable()->sortable(),
            TextColumn::make('vehicleCategory.name')->label('Vehicle category')->sortable(),
            TextColumn::make('price')->money('GHS')->sortable(),
            TextColumn::make('company_pct')->label('Company')->suffix('%'),
            TextColumn::make('worker_pct')->label('Worker')->suffix('%'),
            IconColumn::make('is_active')->boolean(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServicePrices::route('/'),
            'create' => Pages\CreateServicePrice::route('/create'),
            'edit' => Pages\EditServicePrice::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('pricing_system', 'standard')->whereHas('service', fn (Builder $query) => $query->where('is_global', true));
    }
}
