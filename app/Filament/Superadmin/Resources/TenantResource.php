<?php

namespace App\Filament\Superadmin\Resources;

use App\Filament\Superadmin\Resources\TenantResource\Pages;
use App\Models\Tenant;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'SaaS management';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required()->maxLength(255),
            FileUpload::make('logo')->image()->directory('tenant-logos'),
            TextInput::make('phone')->tel()->maxLength(30),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            Select::make('package_id')->relationship('package', 'name')->searchable()->preload(),
            TextInput::make('ceo_name')
                ->label('CEO name')
                ->required()
                ->maxLength(255)
                ->visibleOn('create'),
            TextInput::make('ceo_phone')
                ->label('CEO phone')
                ->tel()
                ->maxLength(30)
                ->visibleOn('create'),
            TextInput::make('ceo_email')
                ->label('CEO login email')
                ->email()
                ->required()
                ->unique(table: 'users', column: 'email')
                ->visibleOn('create'),
            TextInput::make('ceo_password')
                ->label('CEO login password')
                ->password()
                ->revealable()
                ->required()
                ->minLength(8)
                ->visibleOn('create'),
            Select::make('status')->options([
                'trial' => 'Trial',
                'active' => 'Active',
                'suspended' => 'Suspended',
                'cancelled' => 'Cancelled',
            ])->required()->default('trial'),
            DateTimePicker::make('trial_ends_at'),
            TextInput::make('momo_number')->tel()->maxLength(30),
            TextInput::make('momo_name')->maxLength(255),
            TextInput::make('paystack_public_key')->maxLength(255),
            TextInput::make('paystack_secret_key')
                ->password()
                ->revealable()
                ->dehydrated(fn ($state): bool => filled($state)),
            Toggle::make('cash_enabled')->default(true),
            Toggle::make('momo_enabled')->default(false),
            Toggle::make('paystack_enabled')->default(false),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('package.name')->label('Package')->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('trial_ends_at')->dateTime()->sortable(),
                TextColumn::make('created_at')->dateTime()->since()->sortable(),
            ])
            ->filters([Tables\Filters\SelectFilter::make('status')->options([
                'trial' => 'Trial',
                'active' => 'Active',
                'suspended' => 'Suspended',
                'cancelled' => 'Cancelled',
            ])])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
