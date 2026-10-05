<?php

namespace App\Filament\Superadmin\Resources;

use App\Filament\Superadmin\Resources\VehicleModelResource\Pages;
use App\Models\VehicleModel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VehicleModelResource extends Resource
{
    protected static ?string $model = VehicleModel::class;

    protected static ?string $navigationGroup = 'Global reference data';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_global', true);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('vehicle_make_id')->relationship('make', 'name')->searchable()->preload()->required(),
            TextInput::make('name')->required()->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('make.name')->label('Make')->sortable()->searchable(),
            TextColumn::make('name')->sortable()->searchable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('vehicle_make_id')
                ->relationship('make', 'name'),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVehicleModels::route('/'),
            'create' => Pages\CreateVehicleModel::route('/create'),
            'edit' => Pages\EditVehicleModel::route('/{record}/edit'),
        ];
    }
}
