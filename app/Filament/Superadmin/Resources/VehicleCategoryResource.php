<?php

namespace App\Filament\Superadmin\Resources;

use App\Filament\Superadmin\Resources\VehicleCategoryResource\Pages;
use App\Models\VehicleCategory;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VehicleCategoryResource extends Resource
{
    protected static ?string $model = VehicleCategory::class;

    protected static ?string $navigationGroup = 'Global reference data';

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_global', true);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([TextInput::make('name')->required()->unique(ignoreRecord: true)->maxLength(255)]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('created_at')->dateTime()->since()->sortable(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVehicleCategories::route('/'),
            'create' => Pages\CreateVehicleCategory::route('/create'),
            'edit' => Pages\EditVehicleCategory::route('/{record}/edit'),
        ];
    }
}
