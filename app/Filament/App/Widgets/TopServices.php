<?php

namespace App\Filament\App\Widgets;

use App\Models\WashSaleItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TopServices extends TableWidget
{
    protected static ?string $heading = 'Top services this month';

    public function table(Table $table): Table
    {
        return $table
            ->query(WashSaleItem::query()
                ->selectRaw('MIN(wash_sale_items.id) as id, service_name, SUM(quantity) as quantity_sold, SUM(wash_sale_items.total_amount) as sales_total')
                ->whereHas('sale', fn (Builder $query) => $query
                    ->where('status', 'completed')
                    ->whereBetween('sold_at', [now()->startOfMonth(), now()->endOfMonth()]))
                ->groupBy('service_name')
                ->orderByDesc('sales_total'))
            ->columns([
                TextColumn::make('service_name')->label('Service'),
                TextColumn::make('quantity_sold')->label('Units')->numeric(),
                TextColumn::make('sales_total')->label('Sales')->money('GHS'),
            ])
            ->paginated(false);
    }
}
