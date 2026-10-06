<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Package;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);

        $features = [
            'plate_ocr' => ['Plate OCR', 'Read vehicle plates using optical character recognition.'],
            'sms' => ['SMS', 'Send customer notifications by SMS.'],
            'client_portal' => ['Client portal', 'Give customers access to a self-service portal.'],
            'loyalty' => ['Loyalty', 'Reward repeat customers with loyalty benefits.'],
            'home_service' => ['Home service', 'Manage vehicle washes performed at customer locations.'],
            'multi_branch' => ['Multi-branch', 'Manage multiple car wash bay locations.'],
            'reports_export' => ['Reports export', 'Export operational and financial reports.'],
            'paystack' => ['Paystack', 'Accept online payments through Paystack.'],
            'momo_manual' => ['Manual Mobile Money', 'Record Mobile Money payments manually.'],
            'cash_payment' => ['Cash payments', 'Accept cash payments at the bay.'],
            'worker_pin_login' => ['Worker PIN login', 'Allow workers to sign in using a PIN.'],
            'audit_trail' => ['Audit trail', 'Keep a record of important account activity.'],
            'fraud_flags' => ['Fraud flags', 'Flag potentially suspicious transactions.'],
            'expense_tracking' => ['Expense tracking', 'Record and report business expenses.'],
        ];

        $featureModels = [];

        foreach ($features as $key => [$name, $description]) {
            $featureModels[$key] = Feature::query()->updateOrCreate(
                ['key' => $key],
                ['name' => $name, 'description' => $description, 'is_active' => true],
            );
        }

        $packages = [
            'Starter' => [
                'price' => 99,
                'branch_addon_price' => 50,
                'worker_limit' => 5,
                'sms_credits' => 0,
                'enabled' => ['cash_payment', 'momo_manual', 'worker_pin_login'],
            ],
            'Standard' => [
                'price' => 249,
                'branch_addon_price' => 75,
                'worker_limit' => 20,
                'sms_credits' => 100,
                'enabled' => [
                    'cash_payment', 'momo_manual', 'worker_pin_login', 'sms',
                    'loyalty', 'multi_branch', 'reports_export', 'audit_trail',
                    'expense_tracking',
                ],
            ],
            'Premium' => [
                'price' => 499,
                'branch_addon_price' => 100,
                'worker_limit' => 100,
                'sms_credits' => 500,
                'enabled' => array_keys($features),
            ],
        ];

        foreach ($packages as $name => $details) {
            $package = Package::query()->updateOrCreate(
                ['name' => $name],
                [
                    'price' => $details['price'],
                    'billing_cycle' => 'monthly',
                    'branch_addon_price' => $details['branch_addon_price'],
                    'worker_limit' => $details['worker_limit'],
                    'sms_credits' => $details['sms_credits'],
                    'is_active' => true,
                ],
            );

            $featureAssignments = [];

            foreach ($featureModels as $key => $feature) {
                $featureAssignments[$feature->id] = [
                    'enabled' => in_array($key, $details['enabled'], true),
                    'limit_value' => $key === 'multi_branch'
                        ? match ($name) {
                            'Starter' => 1,
                            'Standard' => 3,
                            default => 10,
                        }
                        : null,
                ];
            }

            $package->features()->sync($featureAssignments);
        }

        User::withoutGlobalScopes()->firstOrCreate(
            ['email' => 'superadmin@carbayplus.test'],
            [
                'tenant_id' => null,
                'branch_id' => null,
                'role' => 'super_admin',
                'name' => 'Carbay+ Super Admin',
                'phone' => '0241786330',
                'password' => '1234',
                'pin' => null,
                'status' => 'active',
            ],
        );
    }
}
