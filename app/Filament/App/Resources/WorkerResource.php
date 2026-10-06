<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\WorkerResource\Pages;
use App\Models\Worker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class WorkerResource extends Resource
{
    protected static ?string $model = Worker::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Company';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('branch_id')
                ->relationship('branch', 'name')
                ->rules([
                    Rule::exists('branches', 'id')
                        ->where('tenant_id', auth()->user()?->tenant_id)
                        ->when(auth()->user()?->role === 'manager', fn ($rule) => $rule->where('id', auth()->user()?->branch_id)),
                ])
                ->searchable()
                ->preload()
                ->required(),
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('phone')->tel()->maxLength(30),
            TextInput::make('pin')->password()->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->rules(['digits_between:4,12'])
                ->dehydrated(fn ($state): bool => filled($state))
                ->helperText('Enter a 4-12 digit PIN to set or reset it. The saved PIN is hashed.'),
            Select::make('type')->options([
                'permanent' => 'Permanent',
                'casual' => 'Casual',
            ])->required()->default('permanent'),
            TextInput::make('default_share_pct')->numeric()->suffix('%')->minValue(0)->maxValue(100)->required(),
            Select::make('payout_mode')->options([
                'instant' => 'Instant',
                'daily' => 'Daily',
                'weekly' => 'Weekly',
                'monthly' => 'Monthly',
            ])->required()->default('daily'),
            Select::make('status')->options([
                'active' => 'Active',
                'inactive' => 'Inactive',
            ])->required()->default('active'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('phone')->searchable(),
            TextColumn::make('branch.name')->label('Branch')->sortable(),
            TextColumn::make('type')->badge(),
            TextColumn::make('default_share_pct')->suffix('%'),
            TextColumn::make('status')->badge(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkers::route('/'),
            'create' => Pages\CreateWorker::route('/create'),
            'edit' => Pages\EditWorker::route('/{record}/edit'),
        ];
    }
}
