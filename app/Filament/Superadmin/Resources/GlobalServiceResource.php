<?php

namespace App\Filament\Superadmin\Resources;

use App\Filament\Superadmin\Resources\GlobalServiceResource\Pages;
use App\Models\Service as CarWashService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GlobalServiceResource extends Resource
{
    protected static ?string $model = CarWashService::class;

    protected static ?string $navigationLabel = 'Global services';

    protected static ?string $navigationGroup = 'Global reference data';

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_global', true);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('default_price')->numeric()->prefix('GH₵')->required()->minValue(0),
            TextInput::make('company_pct')->numeric()->suffix('%')->required()->minValue(0)->maxValue(100),
            TextInput::make('worker_pct')->numeric()->suffix('%')->required()->minValue(0)->maxValue(100),
            Toggle::make('is_active')->default(true),
            Textarea::make('description')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('default_price')->money('GHS')->sortable(),
            TextColumn::make('company_pct')->suffix('%'),
            TextColumn::make('worker_pct')->suffix('%'),
            IconColumn::make('is_active')->boolean(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGlobalServices::route('/'),
            'create' => Pages\CreateGlobalService::route('/create'),
            'edit' => Pages\EditGlobalService::route('/{record}/edit'),
        ];
    }
}
