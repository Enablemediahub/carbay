<?php

namespace App\Filament\Superadmin\Pages;

use App\Console\Commands\ProcessSubscriptionBilling;
use App\Filament\Superadmin\Resources\TenantResource;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Support\SubscriptionAccess;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class Subscriptions extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationLabel = 'Tenant subscriptions';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static string $view = 'filament.superadmin.pages.subscriptions';

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table->query(Tenant::query()->with('package')->addSelect([
            'paid_until' => SubscriptionInvoice::withoutGlobalScopes()->selectRaw('MAX(period_ends_at)')->whereColumn('tenant_id', 'tenants.id')->where('status', 'paid'),
            'outstanding' => SubscriptionInvoice::withoutGlobalScopes()->selectRaw('COALESCE(SUM(amount), 0)')->whereColumn('tenant_id', 'tenants.id')->where('status', 'pending'),
        ]))->columns([
            TextColumn::make('name')->label('Company')->searchable()->sortable(),
            TextColumn::make('package.name')->label('Plan'),
            TextColumn::make('package.billing_cycle')->label('Cycle'),
            TextColumn::make('package.price')->label('Plan price')->money('GHS'),
            TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('access')->label('Subscription access')->state(function (Tenant $record): string {
                $state = app(SubscriptionAccess::class)->state($record);

                return $state['allowed'] ? ($state['attention'] ? 'Grace period' : 'Available') : 'Payment required';
            })->badge()->color(fn (string $state) => match ($state) {
                'Available' => 'success', 'Grace period' => 'warning', default => 'danger'
            }),
            TextColumn::make('trial_ends_at')->label('Trial ends')->date(),
            TextColumn::make('paid_until')->label('Paid through')->date()->placeholder('No paid invoice'),
            TextColumn::make('outstanding')->money('GHS')->sortable(),
        ])->filters([
            SelectFilter::make('status')->options(['active' => 'Active', 'trial' => 'Trial', 'suspended' => 'Suspended', 'inactive' => 'Inactive']),
        ])->actions([
            Action::make('invoices')->label('Invoices & receipts')->url(fn (Tenant $record) => '/superadmin/subscription-invoices?'.http_build_query(['tableFilters' => ['tenant_id' => ['value' => $record->id]]])),
            Action::make('generate')->label('Generate due invoice')->requiresConfirmation()->modalDescription('Create the next due invoice using this company\'s assigned plan and update overdue status. Existing invoices are retained.')
                ->action(fn (Tenant $record) => app(ProcessSubscriptionBilling::class)->processTenant($record->id)),
            Action::make('plan')->label('Manage plan / status')->url(fn (Tenant $record) => TenantResource::getUrl('edit', ['record' => $record])),
        ])->defaultSort('name');
    }
}
