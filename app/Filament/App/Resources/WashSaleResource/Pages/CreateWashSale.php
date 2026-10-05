<?php

namespace App\Filament\App\Resources\WashSaleResource\Pages;

use App\Filament\App\Resources\WashSaleResource;
use App\Services\WashSaleRecorder;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWashSale extends CreateRecord
{
    protected static string $resource = WashSaleResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $items = $data['items'] ?? [];
        unset($data['items']);

        return app(WashSaleRecorder::class)->create(
            auth()->user(),
            [...$data, 'items' => $items],
        );
    }
}
