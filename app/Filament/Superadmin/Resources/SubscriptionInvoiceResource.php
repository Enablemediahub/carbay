<?php

namespace App\Filament\Superadmin\Resources;

use App\Filament\Superadmin\Resources\SubscriptionInvoiceResource\Pages\ListSubscriptionInvoices;
use App\Models\SubscriptionInvoice;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubscriptionInvoiceResource extends Resource
{
    protected static ?string $model = SubscriptionInvoice::class;

    protected static ?string $navigationLabel = 'Subscription invoices';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('invoice_number')->searchable()->sortable(),
            TextColumn::make('tenant.name')->label('Tenant')->searchable(),
            TextColumn::make('package.name')->label('Plan'),
            TextColumn::make('period_starts_at')->date(),
            TextColumn::make('period_ends_at')->date(),
            TextColumn::make('amount')->money('GHS')->sortable(),
            TextColumn::make('due_at')->dateTime()->sortable(),
            TextColumn::make('status')->badge()->sortable(),
        ])->actions([
            Action::make('markPaid')
                ->label('Mark paid')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (SubscriptionInvoice $record): bool => $record->status !== 'paid')
                ->action(fn (SubscriptionInvoice $record) => $record->markPaid()),
        ])->defaultSort('due_at');
    }

    public static function getPages(): array
    {
        return ['index' => ListSubscriptionInvoices::route('/')];
    }
}
