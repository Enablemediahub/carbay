<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\Concerns\RequiresTenantFeature;
use App\Filament\App\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class ExpenseResource extends Resource
{
    use RequiresTenantFeature;

    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Company';

    protected static function featureKey(): string
    {
        return 'expense_tracking';
    }

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
            Select::make('category')->options([
                'supplies' => 'Supplies',
                'utilities' => 'Utilities',
                'rent' => 'Rent',
                'maintenance' => 'Maintenance',
                'salaries' => 'Salaries',
                'transport' => 'Transport',
                'other' => 'Other',
            ])->required(),
            TextInput::make('amount')->numeric()->prefix('GH₵')->minValue(0.01)->required(),
            DateTimePicker::make('spent_at')->required()->default(now()),
            Textarea::make('note')->rows(3)->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('spent_at')->dateTime()->sortable(),
            TextColumn::make('category')->badge()->sortable(),
            TextColumn::make('amount')->money('GHS')->sortable(),
            TextColumn::make('branch.name')->label('Branch'),
            TextColumn::make('note')->limit(60)->wrap(),
        ])->filters([
            Tables\Filters\SelectFilter::make('branch_id')->relationship('branch', 'name'),
            Tables\Filters\SelectFilter::make('category')->options([
                'supplies' => 'Supplies',
                'utilities' => 'Utilities',
                'rent' => 'Rent',
                'maintenance' => 'Maintenance',
                'salaries' => 'Salaries',
                'transport' => 'Transport',
                'other' => 'Other',
            ]),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
