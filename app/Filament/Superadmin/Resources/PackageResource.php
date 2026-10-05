<?php

namespace App\Filament\Superadmin\Resources;

use App\Filament\Superadmin\Resources\PackageResource\Pages;
use App\Models\Package;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PackageResource extends Resource
{
    protected static ?string $model = Package::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'SaaS management';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('price')->numeric()->prefix('GH₵')->required()->minValue(0),
            Select::make('billing_cycle')->options([
                'monthly' => 'Monthly',
                'yearly' => 'Yearly',
            ])->required()->default('monthly'),
            TextInput::make('branch_addon_price')->numeric()->prefix('GH₵')->required()->minValue(0),
            TextInput::make('worker_limit')->numeric()->required()->minValue(0),
            TextInput::make('sms_credits')->numeric()->required()->minValue(0),
            Toggle::make('is_active')->default(true),
            Repeater::make('packageFeatures')
                ->relationship()
                ->schema([
                    Select::make('feature_id')
                        ->relationship('feature', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->distinct(),
                    Toggle::make('enabled')->default(false)->required(),
                    TextInput::make('limit_value')->numeric()->minValue(0)->nullable(),
                ])
                ->columns(3)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('price')->money('GHS')->sortable(),
                TextColumn::make('billing_cycle')->badge(),
                TextColumn::make('worker_limit')->numeric()->sortable(),
                TextColumn::make('sms_credits')->numeric(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPackages::route('/'),
            'create' => Pages\CreatePackage::route('/create'),
            'edit' => Pages\EditPackage::route('/{record}/edit'),
        ];
    }
}
