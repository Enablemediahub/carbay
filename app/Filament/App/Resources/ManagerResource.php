<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\ManagerResource\Pages;
use App\Models\Branch;
use App\Models\User;
use App\Rules\UniqueStaffPhone;
use App\Support\PhoneNumber;
use App\Support\StaffPhoto;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class ManagerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'managers';

    protected static ?string $modelLabel = 'manager';

    protected static ?string $navigationLabel = 'Managers';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Company';

    public static function canViewAny(): bool
    {
        return auth()->user()?->role === 'ceo'
            && auth()->user()?->status === 'active'
            && auth()->user()?->tenant_id !== null;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny() && $record->role === 'manager'
            && $record->tenant_id === auth()->user()->tenant_id;
    }

    public static function canEdit(Model $record): bool
    {
        return static::canView($record);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', 'manager');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required()->maxLength(255),
            ViewField::make('photo_path')->label('Manager photo')->view('filament.forms.staff-photo')
                ->rules(fn (?User $record): array => StaffPhoto::rules($record))->columnSpanFull(),
            TextInput::make('phone')->label('Phone number')->tel()->required()->maxLength(30)
                ->mutateStateForValidationUsing(fn (?string $state): ?string => PhoneNumber::normalize($state))
                ->rules(fn (?User $record): array => ['regex:/^\+?[0-9]{7,15}$/', new UniqueStaffPhone($record)])
                ->helperText('Each staff account must use a different phone number.'),
            Select::make('branch_id')->label('Branch')
                ->options(fn (): array => Branch::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id')->all())
                ->rules([Rule::exists('branches', 'id')->where('tenant_id', auth()->user()?->tenant_id)->where('status', 'active')])
                ->searchable()->required(),
            TextInput::make('pin')->label('Manager PIN')->password()->revealable()
                ->afterStateHydrated(fn (TextInput $component) => $component->state(null))
                ->required(fn (string $operation): bool => $operation === 'create')
                ->rules(['nullable', 'digits_between:4,12'])
                ->dehydrated(fn ($state): bool => filled($state))
                ->helperText('Use 4–12 digits. Leave blank when editing to keep the current PIN.'),
            Select::make('status')->options(['active' => 'Active', 'inactive' => 'Inactive'])->default('active')->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('phone')->label('Phone number')->searchable(),
            TextColumn::make('branch.name')->label('Branch'),
            TextColumn::make('status')->badge(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListManagers::route('/'),
            'create' => Pages\CreateManager::route('/create'),
            'edit' => Pages\EditManager::route('/{record}/edit'),
        ];
    }
}
