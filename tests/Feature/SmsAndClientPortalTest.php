<?php

namespace Tests\Feature;

use App\Filament\App\Pages\Reports;
use App\Models\Client;
use App\Models\Feature;
use App\Models\Job;
use App\Models\Package;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VehicleCategory;
use App\Services\ArkeselService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class SmsAndClientPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_delivery_logs_and_deducts_credits_from_tenant_allowance(): void
    {
        Queue::fake();
        $tenant = $this->createTenant(['sms']);
        config([
            'services.arkesel.api_key' => 'test-key',
            'services.arkesel.sender_id' => 'CARBAY',
            'services.arkesel.endpoint' => 'https://sms.example.test/send',
        ]);
        Http::fake(['sms.example.test/*' => Http::response(['status' => 'success'], 200)]);

        $log = app(ArkeselService::class)->send(
            $tenant,
            '+233244000001',
            str_repeat('A', 161),
            'test',
            null,
            null,
            'long-message-test',
        );

        $this->assertSame('queued', $log->status);
        $this->assertSame(2, $log->credits_charged);
        $this->assertDatabaseHas('sms_credit_balances', [
            'tenant_id' => $tenant->id,
            'credits_remaining' => 3,
        ]);
        app(ArkeselService::class)->deliver($log->id);
        $this->assertSame('sent', $log->fresh()->status);
        Http::assertSent(fn ($request) => $request->hasHeader('api-key', 'test-key')
            && $request['recipients'] === ['+233244000001']);
    }

    public function test_client_can_register_with_sms_otp_and_request_a_bay_booking(): void
    {
        Queue::fake();
        $tenant = $this->createTenant(['sms', 'client_portal', 'loyalty']);
        $branchId = $tenant->main_branch_id;
        $category = VehicleCategory::query()->create(['name' => 'Portal Saloon']);
        $service = Service::query()->create([
            'name' => 'Portal wash',
            'default_price' => 45,
            'company_pct' => 80,
            'worker_pct' => 20,
            'is_global' => true,
            'is_active' => true,
        ]);
        DB::table('service_prices')->insert([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'vehicle_category_id' => $category->id,
            'price' => 55,
            'company_pct' => 80,
            'worker_pct' => 20,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $customService = Service::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Portal custom detail',
            'default_price' => 30,
            'company_pct' => 80,
            'worker_pct' => 20,
            'is_global' => false,
            'is_active' => true,
        ]);
        $otherTenant = $this->createTenant(['client_portal']);
        $otherTenantService = Service::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Another company service',
            'default_price' => 90,
            'company_pct' => 80,
            'worker_pct' => 20,
            'is_global' => false,
            'is_active' => true,
        ]);

        $this->post(route('client.otp.request', ['tenant' => $tenant->id]), [
            'phone' => '+233 24 400 0001',
            'purpose' => 'register',
            'name' => 'Ama Customer',
            'birthday' => today()->subYears(25)->toDateString(),
            'sms_marketing' => 1,
        ])->assertRedirect(route('client.verify', ['tenant' => $tenant->id]));

        $log = DB::table('sms_logs')->where('tenant_id', $tenant->id)->first();
        preg_match('/\b([0-9]{6})\b/', $log->message, $matches);
        $this->assertNotEmpty($matches[1] ?? null);

        $this->post(route('client.otp.verify', ['tenant' => $tenant->id]), [
            'phone' => '+233 24 400 0001',
            'purpose' => 'register',
            'code' => $matches[1],
        ])->assertRedirect(route('client.dashboard', ['tenant' => $tenant->id]));

        $client = Client::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('+233244000001', $client->phone);
        $this->assertTrue($client->preferences_json['sms_marketing']);
        $this->get(route('client.dashboard', ['tenant' => $tenant->id]))
            ->assertOk()
            ->assertSee('Ama Customer')
            ->assertSee('Portal wash')
            ->assertSee('Portal custom detail')
            ->assertSee('GH₵ 45.00')
            ->assertSee('categoryPrices = JSON.parse', false)
            ->assertSee('\u00221\u0022:{\u00221\u0022:55}', false)
            ->assertDontSee('Another company service');

        $managerId = DB::table('users')->insertGetId([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'role' => 'ceo',
            'name' => 'Company CEO',
            'email' => 'portal-ceo@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $job = Job::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'manager_id' => $managerId,
            'client_id' => $client->id,
            'plate' => 'GR1234-24',
            'total_amount' => 55,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'open',
        ]);
        $job->update(['status' => 'completed']);
        $this->assertSame(5, $client->fresh()->loyalty_points);
        $client->update(['loyalty_points' => 50]);

        $this->post(route('client.bookings.create', ['tenant' => $tenant->id]), [
            'service_type' => 'bay',
            'vehicle_category_id' => $category->id,
            'service_ids' => [$service->id],
            'requested_for' => now()->addDay()->format('Y-m-d H:i:s'),
            'redeem_reward' => 1,
        ])->assertRedirect(route('client.dashboard', ['tenant' => $tenant->id]));

        $this->assertDatabaseHas('client_bookings', [
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'estimated_amount' => 55,
            'discount_amount' => 20,
            'service_type' => 'bay',
        ]);
        $this->assertSame(0, $client->fresh()->loyalty_points);

        $this->post(route('client.bookings.create', ['tenant' => $tenant->id]), [
            'service_type' => 'bay',
            'vehicle_category_id' => $category->id,
            'service_ids' => [$customService->id],
            'requested_for' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ])->assertRedirect(route('client.dashboard', ['tenant' => $tenant->id]));
        $this->assertDatabaseHas('client_bookings', [
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'estimated_amount' => 30,
        ]);

        $this->post(route('client.bookings.create', ['tenant' => $tenant->id]), [
            'service_type' => 'bay',
            'vehicle_category_id' => $category->id,
            'service_ids' => [$otherTenantService->id],
            'requested_for' => now()->addDays(3)->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('service_ids');
    }

    public function test_reports_page_aggregates_jobs_for_an_export_enabled_ceo(): void
    {
        $tenant = $this->createTenant(['reports_export']);
        $managerId = DB::table('users')->insertGetId([
            'tenant_id' => $tenant->id,
            'branch_id' => $tenant->main_branch_id,
            'role' => 'ceo',
            'name' => 'Report CEO',
            'email' => 'reports-'.uniqid().'@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Job::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $tenant->main_branch_id,
            'manager_id' => $managerId,
            'plate' => 'GR1000-26',
            'total_amount' => 100,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'completed',
        ]);

        $this->actingAs(User::withoutGlobalScopes()->findOrFail($managerId));
        Livewire::test(Reports::class)
            ->assertSee('Sales by day')
            ->assertSee('Sales by week')
            ->assertSee('Sales by month')
            ->assertSee('Sales by branch')
            ->assertSee('Worker payouts')
            ->assertSee('Expenses by category')
            ->assertSee('Portal Branch')
            ->assertSee('100.00');
    }

    private function createTenant(array $enabledFeatures): Tenant
    {
        $package = Package::query()->create([
            'name' => 'Client portal package '.uniqid(),
            'price' => 0,
            'billing_cycle' => 'monthly',
            'branch_addon_price' => 0,
            'worker_limit' => 10,
            'sms_credits' => 5,
            'is_active' => true,
        ]);
        $assignments = [];
        foreach ($enabledFeatures as $key) {
            $feature = Feature::query()->firstOrCreate(
                ['key' => $key],
                ['name' => ucfirst(str_replace('_', ' ', $key)), 'is_active' => true],
            );
            $assignments[$feature->id] = ['enabled' => true];
        }
        $package->features()->sync($assignments);
        $tenant = Tenant::withoutGlobalScopes()->create([
            'name' => 'Portal Company',
            'email' => 'portal-'.uniqid().'@example.test',
            'package_id' => $package->id,
            'status' => 'active',
        ]);
        $branchId = DB::table('branches')->insertGetId([
            'tenant_id' => $tenant->id,
            'name' => 'Portal Branch',
            'is_main' => true,
            'status' => 'active',
            'is_addon_paid' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $tenant->update(['main_branch_id' => $branchId]);

        return $tenant->fresh();
    }
}
