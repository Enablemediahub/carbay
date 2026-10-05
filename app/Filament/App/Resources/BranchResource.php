<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\BranchResource\Pages;
use App\Models\Branch;
use App\Services\BranchProvisioner;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static ?string $navigationLabel = 'Branches';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Company';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('address')->maxLength(255),
            TextInput::make('phone')->tel()->maxLength(30),
            Select::make('manager_id')
                ->relationship('manager', 'name', modifyQueryUsing: fn ($query) => $query
                    ->where('role', 'manager')->where('status', 'active'))
                ->rules([
                    Rule::exists('users', 'id')
                        ->where('tenant_id', auth()->user()?->tenant_id)
                        ->where('role', 'manager')
                        ->where('status', 'active'),
                ])
                ->searchable()
                ->preload(),
            Select::make('status')->options([
                'active' => 'Active',
                'inactive' => 'Inactive',
            ])->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('address')->searchable(),
                TextColumn::make('phone'),
                TextColumn::make('manager.name')->label('Manager'),
                TextColumn::make('status')->badge(),
                TextColumn::make('addonInvoice.status')->label('Branch invoice')->badge()->default('Included'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                Action::make('addBranch')
                    ->label('Add branch')
                    ->icon('heroicon-o-plus')
                    ->visible(fn (): bool => auth()->user()?->role === 'ceo')
                    ->authorize('create', Branch::class)
                    ->form([
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('address')->maxLength(255),
                        TextInput::make('phone')->tel()->maxLength(30),
                    ])
                    ->action(function (array $data): void {
                        $branch = app(BranchProvisioner::class)->create(auth()->user(), $data);
                        $invoice = $branch->addonInvoice()->first();

                        Notification::make()
                            ->title($invoice ? 'Branch created; add-on invoice pending' : 'Included branch created')
                            ->body($invoice
                                ? 'Invoice '.$invoice->invoice_number.' for GH₵ '.number_format((float) $invoice->amount, 2).' is awaiting payment.'
                                : 'This branch is included in your package.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBranches::route('/'),
            'edit' => Pages\EditBranch::route('/{record}/edit'),
        ];
    }
}
