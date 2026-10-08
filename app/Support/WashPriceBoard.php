<?php

namespace App\Support;

class WashPriceBoard
{
    public static function vehicles(): array
    {
        $prices = [
            'Motor' => [15, null, null], 'Saloon' => [20, 20, 20], '4x4' => [25, 25, 25],
            'Urvan / Mini Bus' => [30, 30, 30], 'Kia Small (Macho)' => [20, 20, 20],
            'Kia Medium' => [30, 30, 30], 'Kia Extra Large' => [35, 35, 35],
            'Benz 207/208 / Sprinter / Ford Bus' => [35, 35, 35],
            'Coaster / Civilian / Benz Bus up to 33 seats' => [40, 40, 40],
            '4x4 V8 / Prado' => [30, 30, 30],
        ];

        return collect($prices)->map(fn ($values, $name) => [
            'vehicle_type' => $name, 'body' => $values[0], 'under' => $values[1], 'engine' => $values[2],
        ])->values()->all();
    }

    public static function extras(): array
    {
        return [
            ['vehicle_type' => 'Saloon', 'service_name' => 'Vacuuming', 'price' => 20],
            ['vehicle_type' => '4x4', 'service_name' => 'Vacuuming', 'price' => 20],
            ['vehicle_type' => 'Saloon', 'service_name' => 'Polishing', 'price' => 20],
            ['vehicle_type' => '4x4', 'service_name' => 'Polishing', 'price' => 20],
            ['vehicle_type' => 'Saloon', 'service_name' => 'Waxing', 'price' => 35],
            ['vehicle_type' => '4x4', 'service_name' => 'Waxing', 'price' => 40],
        ];
    }

    public static function standalone(): array
    {
        return [
            ['service_name' => 'Household carpet cleaning', 'price' => null],
            ['service_name' => 'Standalone vacuuming', 'price' => null],
            ['service_name' => 'Blowing', 'price' => 20],
        ];
    }
}
