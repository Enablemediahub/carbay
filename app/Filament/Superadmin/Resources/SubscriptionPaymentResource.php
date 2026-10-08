<?php

namespace App\Filament\Superadmin\Resources;

use App\Filament\Superadmin\Resources\SubscriptionPaymentResource\Pages\ListSubscriptionPayments;
use App\Models\SubscriptionPayment;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubscriptionPaymentResource extends Resource
{
    protected static ?string $model = SubscriptionPayment::class;

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('tenant.name')->label('Company')->searchable(),
            TextColumn::make('invoice.invoice_number')->label('Invoice'),
            TextColumn::make('method')->badge(),
            TextColumn::make('reference')->searchable(),
            TextColumn::make('receipt_reference')->label('Receipt / MoMo reference')->searchable(),
            TextColumn::make('amount')->money('GHS')->sortable(),
            TextColumn::make('status')->badge(),
            TextColumn::make('recordedBy.name')->label('Recorded / initiated by'),
            TextColumn::make('paid_at')->dateTime()->sortable(),
        ])->filters([
            SelectFilter::make('status')->options(['pending' => 'Pending verification', 'paid' => 'Paid', 'failed' => 'Failed', 'review' => 'Extra payment — reconcile with payer']),
            SelectFilter::make('method')->options(['cash' => 'Cash', 'momo' => 'MoMo', 'paystack' => 'Paystack']),
        ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListSubscriptionPayments::route('/')];
    }
}
