<?php

namespace App\Filament\Superadmin\Resources;

use App\Filament\Superadmin\Resources\SubscriptionInvoiceResource\Pages\ListSubscriptionInvoices;
use App\Models\SubscriptionInvoice;
use App\Services\SubscriptionBillingService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
            TextColumn::make('payments.method')->label('Payment method')->badge(),
            TextColumn::make('payments.receipt_reference')->label('Receipt / MoMo reference'),
            TextColumn::make('payments.reference')->label('Payment reference')->toggleable(isToggledHiddenByDefault: true),
        ])->actions([
            Action::make('markPaid')
                ->label('Confirm cash / MoMo receipt')
                ->color('success')
                ->form([
                    Select::make('method')->options(['cash' => 'Cash', 'momo' => 'MoMo to Abidale Group'])->required(),
                    TextInput::make('receipt_reference')->label('Cash receipt / MoMo transaction reference')->required()->maxLength(255),
                ])
                ->modalDescription('Confirm only after Abidale Group has received the full invoice amount. This records a receipt and updates subscription access.')
                ->visible(fn (SubscriptionInvoice $record): bool => $record->status !== 'paid')
                ->action(fn (SubscriptionInvoice $record, array $data) => app(SubscriptionBillingService::class)->recordManual($record, auth()->user(), $data['method'], $data['receipt_reference'])),
        ])->filters([
            SelectFilter::make('tenant_id')->label('Company')->relationship('tenant', 'name')->searchable()->preload(),
            SelectFilter::make('status')->options(['pending' => 'Pending', 'paid' => 'Paid']),
        ])->defaultSort('due_at');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function getPages(): array
    {
        return ['index' => ListSubscriptionInvoices::route('/')];
    }
}
