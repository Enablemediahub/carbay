<?php

namespace App\Filament\App\Resources\WashSaleResource\Pages;

use App\Filament\App\Resources\WashSaleResource;
use Filament\Resources\Pages\ListRecords;

class ListWashSales extends ListRecords
{
    protected static string $resource = WashSaleResource::class;

    protected ?string $subheading = 'Jobs marked Completed in Today\'s Jobs appear here automatically. Shares are earnings, not confirmation of worker payouts; check payment status for money received.';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
