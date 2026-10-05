<?php

namespace App\Filament\App\Widgets;

use App\Models\Branch;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class BranchSales extends TableWidget
{
    protected static ?string $heading = 'Sales by branch this month';

    public function table(Table $table): Table
    {
        return $table
            ->query(Branch::query()->withSum([
                'sales' => fn (Builder $query) => $query
                    ->where('status', 'completed')
                    ->whereBetween('sold_at', [now()->startOfMonth(), now()->endOfMonth()]),
            ], 'total_amount'))
            ->columns([
                TextColumn::make('name')->label('Branch'),
                TextColumn::make('sales_sum_total_amount')
                    ->label('Sales')
                    ->money('GHS')
                    ->default(0)
                    ->sortable(),
            ])
            ->paginated(false);
    }
}
