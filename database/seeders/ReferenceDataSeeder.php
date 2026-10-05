<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\VehicleCategory;
use App\Models\VehicleMake;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Saloon', 'SUV', 'Pickup', 'Trotro', 'Bus', 'Truck', 'Motorcycle', 'Van'] as $name) {
            VehicleCategory::query()->updateOrCreate(
                ['name' => $name],
                ['is_global' => true],
            );
        }

        $makes = [
            'Toyota' => ['Corolla', 'Camry', 'Yaris', 'RAV4', 'Land Cruiser', 'Hilux', 'Vitz'],
            'Kia' => ['Picanto', 'Rio', 'Cerato', 'Optima', 'Sportage', 'Sorento'],
            'Hyundai' => ['i10', 'i20', 'Elantra', 'Sonata', 'Tucson', 'Santa Fe', 'Accent'],
            'Nissan' => ['Micra', 'Sunny', 'Sentra', 'Altima', 'Qashqai', 'X-Trail', 'Navara'],
            'Honda' => ['Civic', 'Accord', 'Fit', 'CR-V', 'HR-V', 'Pilot'],
            'Mercedes-Benz' => ['C-Class', 'E-Class', 'S-Class', 'GLA', 'GLC', 'GLE'],
            'Volkswagen' => ['Golf', 'Passat', 'Polo', 'Jetta', 'Tiguan', 'Touareg'],
            'Ford' => ['Fiesta', 'Focus', 'Fusion', 'Escape', 'Ranger', 'Explorer'],
            'Mitsubishi' => ['Lancer', 'Outlander', 'Pajero', 'ASX', 'L200', 'Mirage'],
            'Suzuki' => ['Alto', 'Swift', 'Baleno', 'Vitara', 'Jimny', 'Ertiga'],
            'Peugeot' => ['206', '207', '301', '308', '3008', 'Partner'],
            'Renault' => ['Clio', 'Logan', 'Sandero', 'Duster', 'Kwid', 'Kangoo'],
            'BMW' => ['1 Series', '3 Series', '5 Series', 'X1', 'X3', 'X5'],
            'Audi' => ['A3', 'A4', 'A6', 'Q3', 'Q5', 'Q7'],
            'Land Rover' => ['Defender', 'Discovery', 'Discovery Sport', 'Range Rover Evoque', 'Range Rover Sport', 'Range Rover'],
            'Isuzu' => ['D-Max', 'MU-X', 'NPR', 'NQR', 'N-Series', 'F-Series'],
        ];

        foreach ($makes as $name => $models) {
            $make = VehicleMake::query()->updateOrCreate(
                ['name' => $name],
                ['is_global' => true],
            );

            foreach ($models as $modelName) {
                $make->models()->updateOrCreate(
                    ['name' => $modelName],
                    ['is_global' => true],
                );
            }
        }

        $services = [
            'Body Wash' => [30, 70, 30],
            'Body + Under Wash' => [60, 65, 35],
            'Full Detailing' => [350, 70, 30],
            'Interior Vacuum' => [35, 65, 35],
            'Carpet Wash' => [120, 65, 35],
            'Seat Wash' => [150, 65, 35],
            'Engine Wash' => [100, 70, 30],
        ];

        foreach ($services as $name => [$defaultPrice, $companyPct, $workerPct]) {
            Service::query()->updateOrCreate(
                ['name' => $name, 'is_global' => true],
                [
                    'description' => $name.' service',
                    'default_price' => $defaultPrice,
                    'company_pct' => $companyPct,
                    'worker_pct' => $workerPct,
                    'is_active' => true,
                ],
            );
        }
    }
}
