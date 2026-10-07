<?php

namespace App\Filament\App\Widgets;

use App\Models\FraudFlag;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class FraudFlagsWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static ?string $heading = 'Fraud and reconciliation alerts';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->tenant?->hasFeature('fraud_flags') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => FraudFlag::query()->where('status', 'open')->latest())
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Flagged')->since(),
                Tables\Columns\TextColumn::make('flag_type')->label('Signal')->badge(),
                Tables\Columns\TextColumn::make('job.plate')->label('Plate')->default('—'),
                Tables\Columns\TextColumn::make('branch.name')->label('Branch'),
                Tables\Columns\TextColumn::make('description')->wrap()->limit(100),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5);
    }
}
