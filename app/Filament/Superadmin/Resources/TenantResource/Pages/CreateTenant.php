<?php

namespace App\Filament\Superadmin\Resources\TenantResource\Pages;

use App\Filament\Superadmin\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\TenantOnboarder;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $ceo = [
            'name' => $data['ceo_name'],
            'phone' => $data['ceo_phone'] ?? null,
            'email' => $data['ceo_email'],
            'password' => $data['ceo_password'],
        ];

        unset($data['ceo_name'], $data['ceo_phone'], $data['ceo_email'], $data['ceo_password']);

        return app(TenantOnboarder::class)->create($data, $ceo);
    }
}
