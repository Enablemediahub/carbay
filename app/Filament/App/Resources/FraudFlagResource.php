<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\Concerns\RequiresTenantFeature;
use App\Filament\App\Resources\FraudFlagResource\Pages\ListFraudFlags;
use App\Models\FraudFlag;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class FraudFlagResource extends Resource
{
    use RequiresTenantFeature;

    protected static ?string $model = FraudFlag::class;

    protected static ?string $navigationLabel = 'Payment review';

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationGroup = 'Reports & data';

    protected static function featureKey(): string
    {
        return 'fraud_flags';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $openCount = FraudFlag::query()->where('status', 'open')->count();

        return $openCount > 0 ? (string) $openCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Flagged')->dateTime()->sortable(),
                TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'open' => 'danger',
                    'reviewed' => 'success',
                    default => 'gray',
                }),
                TextColumn::make('flag_type')->label('Signal')->badge(),
                TextColumn::make('job.plate')->label('Plate')->default('—')->searchable(),
                TextColumn::make('job.total_amount')->label('Job amount')->money('GHS')->default('—'),
                TextColumn::make('cash_reconciliation_id')->label('Reconciliation')->formatStateUsing(
                    fn ($state) => $state ? 'Cash reconciliation #'.$state : '—',
                ),
                TextColumn::make('sale.reference')->label('Sale reference')->default('—')->searchable(),
                TextColumn::make('sale.payment_method')->label('Payment')->badge(),
                TextColumn::make('sale.payment_reference')->label('Payment reference')->copyable(),
                TextColumn::make('sale.total_amount')->label('Amount')->money('GHS'),
                TextColumn::make('branch.name')->label('Branch')->sortable(),
                TextColumn::make('description')->wrap()->limit(100),
                TextColumn::make('reviewedBy.name')->label('Reviewed by')->default('—'),
                TextColumn::make('reviewed_at')->label('Reviewed')->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'open' => 'Open',
                    'reviewed' => 'Reviewed',
                    'dismissed' => 'Dismissed',
                ])->default('open'),
                Tables\Filters\SelectFilter::make('branch_id')
                    ->relationship('branch', 'name')
                    ->visible(fn (): bool => Auth::user()?->role === 'ceo'),
            ])
            ->actions([
                Action::make('markReviewed')
                    ->label('Mark reviewed')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (FraudFlag $record): bool => $record->status === 'open')
                    ->action(fn (FraudFlag $record) => self::updateStatus($record, 'reviewed')),
                Action::make('dismiss')
                    ->label('Dismiss')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->visible(fn (FraudFlag $record): bool => $record->status === 'open')
                    ->requiresConfirmation()
                    ->action(fn (FraudFlag $record) => self::updateStatus($record, 'dismissed')),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => ListFraudFlags::route('/')];
    }

    private static function updateStatus(FraudFlag $record, string $status): void
    {
        abort_unless(in_array($status, ['reviewed', 'dismissed'], true), 422);
        abort_unless($record->status === 'open', 409);

        $record->update([
            'status' => $status,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        Notification::make()
            ->success()
            ->title($status === 'reviewed' ? 'Flag marked as reviewed' : 'Flag dismissed')
            ->send();
    }
}
