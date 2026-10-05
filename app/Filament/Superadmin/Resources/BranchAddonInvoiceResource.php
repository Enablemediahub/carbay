<?php

namespace App\Filament\Superadmin\Resources;

use App\Filament\Superadmin\Resources\BranchAddonInvoiceResource\Pages\ListBranchAddonInvoices;
use App\Models\BranchAddonInvoice;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BranchAddonInvoiceResource extends Resource
{
    protected static ?string $model = BranchAddonInvoice::class;

    protected static ?string $navigationLabel = 'Branch add-on invoices';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('invoice_number')->searchable()->sortable(),
            TextColumn::make('tenant.name')->label('Tenant')->searchable(),
            TextColumn::make('branch.name')->label('Branch'),
            TextColumn::make('amount')->money('GHS')->sortable(),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->actions([
            Action::make('markPaid')
                ->label('Mark paid')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (BranchAddonInvoice $record): bool => $record->status !== 'paid')
                ->action(fn (BranchAddonInvoice $record) => $record->markPaid()),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListBranchAddonInvoices::route('/')];
    }
}
