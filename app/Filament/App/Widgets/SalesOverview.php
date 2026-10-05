<?php

namespace App\Filament\App\Widgets;

use App\Models\WashSale;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $sales = WashSale::query()->where('status', 'completed');

        return [
            Stat::make('Today', 'GH₵ '.number_format((float) (clone $sales)
                ->whereDate('sold_at', today())->sum('total_amount'), 2)),
            Stat::make('This week', 'GH₵ '.number_format((float) (clone $sales)
                ->whereBetween('sold_at', [now()->startOfWeek(), now()->endOfWeek()])->sum('total_amount'), 2)),
            Stat::make('This month', 'GH₵ '.number_format((float) (clone $sales)
                ->whereBetween('sold_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('total_amount'), 2)),
        ];
    }
}
