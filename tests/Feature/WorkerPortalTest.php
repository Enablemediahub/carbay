<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Package;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkerPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_worker_can_sign_in_with_pin_and_only_see_their_own_sales(): void
    {
        [$tenant, $package] = $this->createTenantWithWorkerPinFeature();
        $branchId = $this->createBranch($tenant->id, 'Worker Bay');
        $workerId = $this->createWorker($tenant->id, $branchId, 'Ama Worker', '0244000001');
        $this->createSale($tenant->id, $branchId, $workerId, 'OWN-SALE');

        $otherTenant = $this->createTenant($package, 'Other Company', 'other@example.test');
        $otherBranchId = $this->createBranch($otherTenant->id, 'Other Bay');
        $otherWorkerId = $this->createWorker($otherTenant->id, $otherBranchId, 'Other Worker', '0244000002');
        $this->createSale($otherTenant->id, $otherBranchId, $otherWorkerId, 'OTHER-SALE');

        $this->get('/worker/login')
            ->assertOk()
            ->assertSee('Team sign in')
            ->assertSee('Choose your company')
            ->assertSee('Worker Company')
            ->assertDontSee('workers@example.test');

        $this->post('/worker/login', [
            'company_id' => $tenant->id,
            'phone' => '0244000001',
            'pin' => '9999',
        ])->assertSessionHasErrors('credentials');

        $this->post('/worker/login', [
            'company_id' => $tenant->id,
            'phone' => '0244000001',
            'pin' => '1234',
        ])->assertRedirect('/worker');

        $this->assertGuest();
        $this->get('/worker')
            ->assertOk()
            ->assertSee('Ama Worker')
            ->assertSee('Sales from your washes today')
            ->assertSee('GH₵ 20.00')
            ->assertSee('OWN-SALE')
            ->assertDontSee('OTHER-SALE');

        $this->post('/worker/logout')->assertRedirect('/worker/login');
        $this->get('/worker')->assertRedirect('/worker/login');
    }

    public function test_worker_login_is_denied_when_package_feature_is_missing(): void
    {
        $package = $this->createPackage('No Worker PIN');
        $tenant = $this->createTenant($package, 'No PIN Company', 'no-pin@example.test');
        $branchId = $this->createBranch($tenant->id, 'No PIN Bay');
        $this->createWorker($tenant->id, $branchId, 'No PIN Worker', '0244000003');

        $this->post('/worker/login', [
            'company_id' => $tenant->id,
            'phone' => '0244000003',
            'pin' => '1234',
        ])->assertSessionHasErrors('credentials');

        $this->get('/worker')->assertRedirect('/worker/login');
    }

    public function test_worker_portal_session_ends_when_worker_is_deactivated(): void
    {
        [$tenant] = $this->createTenantWithWorkerPinFeature();
        $branchId = $this->createBranch($tenant->id, 'Inactive Bay');
        $workerId = $this->createWorker($tenant->id, $branchId, 'Inactive Worker', '0244000004');

        $this->post('/worker/login', [
            'company_id' => $tenant->id,
            'phone' => '0244000004',
            'pin' => '1234',
        ])->assertRedirect('/worker');

        DB::table('workers')->where('id', $workerId)->update(['status' => 'inactive']);

        $this->get('/worker')
            ->assertRedirect('/worker/login')
            ->assertSessionHasErrors('credentials');
    }

    public function test_worker_pin_sign_in_is_rate_limited(): void
    {
        [$tenant] = $this->createTenantWithWorkerPinFeature();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/worker/login', [
                'company_id' => $tenant->id,
                'phone' => '0244000099',
                'pin' => '9999',
            ])->assertSessionHasErrors('credentials');
        }

        $this->post('/worker/login', [
            'company_id' => $tenant->id,
            'phone' => '0244000099',
            'pin' => '9999',
        ])->assertTooManyRequests();
    }

    public function test_worker_can_choose_the_correct_company_when_phone_is_registered_with_multiple_companies(): void
    {
        [$tenant, $package] = $this->createTenantWithWorkerPinFeature();
        $branchId = $this->createBranch($tenant->id, 'First Bay');
        $this->createWorker($tenant->id, $branchId, 'Ama First Worker', '0244000008');

        $otherTenant = $this->createTenant($package, 'Another Worker Company', 'another@example.test');
        $otherBranchId = $this->createBranch($otherTenant->id, 'Second Bay');
        $this->createWorker($otherTenant->id, $otherBranchId, 'Ama Second Worker', '0244000008');

        $this->get('/worker/login')
            ->assertOk()
            ->assertSee('Worker Company')
            ->assertSee('Another Worker Company');

        $this->post('/worker/login', [
            'company_id' => $otherTenant->id,
            'phone' => '0244000008',
            'pin' => '1234',
        ])->assertRedirect('/worker');

        $this->get('/worker')
            ->assertOk()
            ->assertSee('Ama Second Worker')
            ->assertDontSee('Ama First Worker');
    }

    private function createTenantWithWorkerPinFeature(): array
    {
        $package = $this->createPackage('Worker PIN');
        $feature = Feature::query()->create([
            'key' => 'worker_pin_login',
            'name' => 'Worker PIN login',
            'is_active' => true,
        ]);
        $package->features()->attach($feature->id, ['enabled' => true]);

        return [$this->createTenant($package, 'Worker Company', 'workers@example.test'), $package];
    }

    private function createPackage(string $name): Package
    {
        return Package::query()->create([
            'name' => $name,
            'price' => 100,
            'billing_cycle' => 'monthly',
            'branch_addon_price' => 10,
            'worker_limit' => 10,
            'sms_credits' => 0,
            'is_active' => true,
        ]);
    }

    private function createTenant(Package $package, string $name, string $email): Tenant
    {
        return Tenant::withoutGlobalScopes()->create([
            'name' => $name,
            'email' => $email,
            'package_id' => $package->id,
            'status' => 'active',
        ]);
    }

    private function createBranch(int $tenantId, string $name): int
    {
        return DB::table('branches')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => $name,
            'is_main' => true,
            'status' => 'active',
            'is_addon_paid' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createWorker(int $tenantId, int $branchId, string $name, string $phone): int
    {
        return DB::table('workers')->insertGetId([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'name' => $name,
            'phone' => $phone,
            'pin' => Hash::make('1234'),
            'type' => 'permanent',
            'default_share_pct' => 10,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSale(int $tenantId, int $branchId, int $workerId, string $reference): void
    {
        $saleId = DB::table('wash_sales')->insertGetId([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'worker_id' => $workerId,
            'reference' => $reference,
            'total_amount' => 20,
            'payment_method' => 'cash',
            'status' => 'completed',
            'sold_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('wash_sale_items')->insert([
            'wash_sale_id' => $saleId,
            'service_name' => 'Body Wash',
            'quantity' => 1,
            'unit_price' => 20,
            'total_amount' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
