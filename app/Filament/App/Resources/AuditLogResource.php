<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\AuditLogResource\Pages;
use App\Filament\App\Resources\Concerns\RequiresTenantFeature;
use App\Models\AuditLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AuditLogResource extends Resource
{
    use RequiresTenantFeature;

    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationLabel = 'Activity history';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Reports & data';

    protected static function featureKey(): string
    {
        return 'audit_trail';
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

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('event')->badge()->searchable()->sortable(),
                TextColumn::make('user.name')->label('By')->default('System')->searchable(),
                TextColumn::make('branch.name')->label('Branch')->default('Company-wide')->sortable(),
                TextColumn::make('auditable_type')
                    ->label('Record')
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? class_basename($state)
                        : '—'),
                TextColumn::make('auditable_id')->label('Record ID'),
                TextColumn::make('old_values')
                    ->label('Before')
                    ->formatStateUsing(fn (?array $state): string => self::formatValues($state))
                    ->wrap()
                    ->limit(100)
                    ->tooltip(fn (AuditLog $record): string => self::formatValues($record->old_values)),
                TextColumn::make('new_values')
                    ->label('After')
                    ->formatStateUsing(fn (?array $state): string => self::formatValues($state))
                    ->wrap()
                    ->limit(100)
                    ->tooltip(fn (AuditLog $record): string => self::formatValues($record->new_values)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->options(fn (): array => AuditLog::query()
                        ->distinct()
                        ->orderBy('event')
                        ->pluck('event', 'event')
                        ->all()),
                Tables\Filters\SelectFilter::make('branch_id')
                    ->relationship('branch', 'name')
                    ->visible(fn (): bool => Auth::user()?->role === 'ceo'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAuditLogs::route('/')];
    }

    private static function formatValues(?array $values): string
    {
        if (! $values) {
            return '—';
        }

        return json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '—';
    }
}
