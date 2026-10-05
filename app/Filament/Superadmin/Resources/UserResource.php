<?php

namespace App\Filament\Superadmin\Resources;

use App\Filament\Superadmin\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'SaaS management';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('tenant_id')->relationship('tenant', 'name')->searchable()->preload(),
            Select::make('branch_id')->relationship('branch', 'name')->searchable()->preload(),
            Select::make('role')->options([
                'super_admin' => 'Super admin',
                'ceo' => 'CEO',
                'manager' => 'Manager',
            ])->required(),
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('phone')->tel()->maxLength(30),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('password')
                ->password()
                ->dehydrated(fn ($state): bool => filled($state))
                ->required(fn (string $context): bool => $context === 'create'),
            TextInput::make('pin')
                ->password()
                ->dehydrated(fn ($state): bool => filled($state)),
            Select::make('status')->options([
                'active' => 'Active',
                'inactive' => 'Inactive',
                'suspended' => 'Suspended',
            ])->required()->default('active'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('tenant.name')->label('Tenant')->sortable(),
                TextColumn::make('branch.name')->label('Branch'),
                TextColumn::make('role')->badge()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')->options([
                    'super_admin' => 'Super admin',
                    'ceo' => 'CEO',
                    'manager' => 'Manager',
                ]),
                Tables\Filters\SelectFilter::make('status')->options([
                    'active' => 'Active',
                    'inactive' => 'Inactive',
                    'suspended' => 'Suspended',
                ]),
            ])
            ->actions([
                Action::make('impersonate')
                    ->label('Impersonate')
                    ->icon('heroicon-m-arrow-right-on-rectangle')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => $record->status === 'active'
                        && in_array($record->role, ['ceo', 'manager'], true))
                    ->action(function (User $record) {
                        session()->put('impersonator_id', Auth::id());
                        Auth::login($record);
                        request()->session()->regenerate();

                        return redirect('/app');
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
