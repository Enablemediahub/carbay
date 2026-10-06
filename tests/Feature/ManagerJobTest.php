<?php

namespace Tests\Feature;

use App\Filament\App\Pages\NewWashJob;
use App\Models\Feature;
use App\Models\Job;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PlateScan;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VehicleCategory;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ManagerJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_record_a_wash_job_and_credit_the_assigned_worker(): void
    {
        $package = Package::query()->create([
            'name' => 'Job package',
            'price' => 0,
            'billing_cycle' => 'monthly',
            'branch_addon_price' => 0,
            'worker_limit' => 10,
            'sms_credits' => 0,
            'is_active' => true,
        ]);
        $cashFeature = Feature::query()->create([
            'key' => 'cash_payment',
            'name' => 'Cash payments',
            'is_active' => true,
        ]);
        $package->features()->attach($cashFeature->id, ['enabled' => true]);
        $tenant = Tenant::withoutGlobalScopes()->create([
            'name' => 'Manager Job Company',
            'email' => 'jobs@example.test',
            'package_id' => $package->id,
            'status' => 'active',
            'cash_enabled' => true,
        ]);
        $branchId = DB::table('branches')->insertGetId([
            'tenant_id' => $tenant->id,
            'name' => 'Main branch',
            'is_main' => true,
            'status' => 'active',
            'is_addon_paid' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $managerId = DB::table('users')->insertGetId([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'role' => 'manager',
            'name' => 'Manager',
            'email' => 'manager-jobs@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $workerId = DB::table('workers')->insertGetId([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'name' => 'Ama',
            'phone' => '0244000001',
            'pin' => bcrypt('1234'),
            'type' => 'permanent',
            'default_share_pct' => 10,
            'payout_mode' => 'instant',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $category = VehicleCategory::query()->create(['name' => 'Company saloon']);
        $service = Service::query()->create([
            'name' => 'Full wash',
            'default_price' => 50,
            'company_pct' => 80,
            'worker_pct' => 20,
            'is_global' => true,
            'is_active' => true,
        ]);
        DB::table('service_prices')->insert([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'vehicle_category_id' => $category->id,
            'price' => 60,
            'company_pct' => 70,
            'worker_pct' => 30,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $scan = PlateScan::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'manager_id' => $managerId,
            'image_path' => 'plate-scans/test.jpg',
            'ocr_raw' => 'GR1234-24',
            'ocr_confidence' => 0.64,
        ]);

        $this->actingAs(User::withoutGlobalScopes()->findOrFail($managerId));
        Livewire::test(NewWashJob::class)
            ->set('plate', 'GR 1234-24')
            ->set('plateScanId', $scan->id)
            ->set('plateConfidence', 0.64)
            ->set('vehicleCategoryId', (string) $category->id)
            ->set('selectedServices', [(string) $service->id])
            ->set('selectedWorkers', [(string) $workerId])
            ->set('paymentMethod', 'cash')
            ->call('submit')
            ->assertHasErrors(['plate'])
            ->set('plateConfirmed', true)
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('jobs', [
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'manager_id' => $managerId,
            'plate' => 'GR 1234-24',
            'total_amount' => 60,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);
        $this->assertDatabaseHas('job_services', [
            'service_id' => $service->id,
            'company_share' => 42,
            'worker_share' => 18,
        ]);
        $this->assertDatabaseHas('payments', [
            'manager_id' => $managerId,
            'method' => 'cash',
            'status' => 'paid',
            'amount' => 60,
        ]);
        $this->assertSame('18.00', Wallet::query()->where('worker_id', $workerId)->firstOrFail()->available_balance);
        $this->assertDatabaseHas('plate_scans', [
            'id' => $scan->id,
            'job_id' => DB::table('jobs')->where('plate', 'GR 1234-24')->value('id'),
            'corrected_value' => 'GR 1234-24',
        ]);
    }

    public function test_paystack_job_defers_worker_wallet_credit_until_signed_success(): void
    {
        $package = Package::query()->create([
            'name' => 'Paystack job package',
            'price' => 0,
            'billing_cycle' => 'monthly',
            'branch_addon_price' => 0,
            'worker_limit' => 10,
            'sms_credits' => 0,
            'is_active' => true,
        ]);
        $feature = Feature::query()->create(['key' => 'paystack', 'name' => 'Paystack', 'is_active' => true]);
        $package->features()->attach($feature->id, ['enabled' => true]);
        $tenant = Tenant::withoutGlobalScopes()->create([
            'name' => 'Paystack job company',
            'email' => 'paystack-job@example.test',
            'package_id' => $package->id,
            'status' => 'active',
            'paystack_enabled' => true,
            'paystack_secret_key' => 'sk_test_job',
        ]);
        $branchId = DB::table('branches')->insertGetId([
            'tenant_id' => $tenant->id,
            'name' => 'Main branch',
            'is_main' => true,
            'status' => 'active',
            'is_addon_paid' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $managerId = DB::table('users')->insertGetId([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'role' => 'manager',
            'name' => 'Paystack Manager',
            'email' => 'paystack-manager-job@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $workerId = DB::table('workers')->insertGetId([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'name' => 'Paystack Worker',
            'phone' => '0244000004',
            'pin' => bcrypt('1234'),
            'type' => 'permanent',
            'default_share_pct' => 0,
            'payout_mode' => 'instant',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $category = VehicleCategory::query()->create(['name' => 'Paystack saloon']);
        $service = Service::query()->create([
            'name' => 'Paystack wash',
            'default_price' => 60,
            'company_pct' => 70,
            'worker_pct' => 30,
            'is_global' => true,
            'is_active' => true,
        ]);
        DB::table('service_prices')->insert([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'vehicle_category_id' => $category->id,
            'price' => 60,
            'company_pct' => 70,
            'worker_pct' => 30,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.example.test/pay'],
            ]),
        ]);
        $this->actingAs(User::withoutGlobalScopes()->findOrFail($managerId));
        Livewire::test(NewWashJob::class)
            ->set('plate', 'GR1234-25')
            ->set('vehicleCategoryId', (string) $category->id)
            ->set('selectedServices', [(string) $service->id])
            ->set('selectedWorkers', [(string) $workerId])
            ->set('clientEmail', 'customer@example.test')
            ->set('paymentMethod', 'paystack')
            ->call('submit')
            ->assertRedirect('https://checkout.example.test/pay');

        $job = Job::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $payment = Payment::withoutGlobalScopes()->where('job_id', $job->id)->firstOrFail();
        $this->assertSame('pending', $payment->status);
        $this->assertSame('pending', $job->payment_status);
        $this->assertFalse(Wallet::withoutGlobalScopes()->where('worker_id', $workerId)->exists());
        Http::assertSent(fn ($request) => $request['currency'] === 'GHS' && $request['amount'] === 6000);

        $payload = json_encode([
            'event' => 'charge.success',
            'data' => ['reference' => $payment->reference, 'amount' => 6000, 'currency' => 'GHS'],
        ], JSON_THROW_ON_ERROR);
        $response = $this->call(
            'POST',
            route('payments.paystack.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $payload, 'sk_test_job'),
            ],
            $payload,
        );

        $response->assertOk();
        $this->assertSame('paid', $job->fresh()->payment_status);
        $this->assertSame('18.00', Wallet::withoutGlobalScopes()->where('worker_id', $workerId)->firstOrFail()->available_balance);
    }
}
