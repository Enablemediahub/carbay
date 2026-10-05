<?php

namespace App\Filament\App\Widgets;

use App\Models\Worker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TopWorkers extends TableWidget
{
    protected static ?string $heading = 'Top workers this month';

    public function table(Table $table): Table
    {
        return $table
            ->query(Worker::query()->withSum([
                'sales' => fn (Builder $query) => $query
                    ->where('status', 'completed')
                    ->whereBetween('sold_at', [now()->startOfMonth(), now()->endOfMonth()]),
            ], 'total_amount')->orderByDesc('sales_sum_total_amount'))
            ->columns([
                TextColumn::make('name')->label('Worker'),
                TextColumn::make('sales_sum_total_amount')->label('Sales')->money('GHS')->default(0),
            ])
            ->paginated(false);
    }
}
