<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchAddonInvoice;
use App\Models\AuditLog;
use App\Filament\App\Pages\Reports;
use App\Models\Expense;
use App\Models\Feature;
use App\Models\FraudFlag;
use App\Models\Package;
use App\Models\Scopes\TenantScope;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\TenantFeature;
use App\Models\User;
use App\Models\VehicleCategory;
use App\Models\WashSale;
use App\Models\WashSaleItem;
use App\Models\Worker;
use App\Services\BranchProvisioner;
use App\Services\TenantOnboarder;
use App\Services\WashSaleRecorder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_queries_only_return_rows_for_the_authenticated_tenant(): void
    {
        $package = $this->createPackage();
        $tenantA = $this->createTenant($package, 'Tenant A');
        $tenantB = $this->createTenant($package, 'Tenant B');
        $branchA = $this->createBranch($tenantA, 'Branch A');
        $branchB = $this->createBranch($tenantB, 'Branch B');
        $userA = $this->createUser($tenantA, $branchA, 'a@example.test');
        $userB = $this->createUser($tenantB, $branchB, 'b@example.test');
        $workerA = $this->createWorker($tenantA, $branchA, 'Worker A');
        $workerB = $this->createWorker($tenantB, $branchB, 'Worker B');
        $feature = Feature::query()->create([
            'key' => 'tenant-test',
            'name' => 'Tenant test',
            'is_active' => true,
        ]);
        $tenantFeatureA = $this->createTenantFeature($tenantA, $feature);
        $this->createTenantFeature($tenantB, $feature);

        $this->actingAs(User::withoutGlobalScope(TenantScope::class)->findOrFail($userA));

        $this->assertSame([$branchA], Branch::query()->pluck('id')->all());
        $this->assertSame([$tenantA->id], Tenant::query()->pluck('id')->all());
        $this->assertSame([$userA], User::query()->pluck('id')->all());
        $this->assertSame([$workerA], Worker::query()->pluck('id')->all());
        $this->assertSame([$tenantFeatureA], TenantFeature::query()->pluck('id')->all());
        $this->assertNotSame($branchA, $branchB);
        $this->assertNotSame($userA, $userB);
        $this->assertNotSame($workerA, $workerB);
    }

    public function test_super_admin_can_bypass_the_tenant_scope(): void
    {
        $package = $this->createPackage();
        $tenantA = $this->createTenant($package, 'Tenant A');
        $tenantB = $this->createTenant($package, 'Tenant B');
        $this->createBranch($tenantA, 'Branch A');
        $this->createBranch($tenantB, 'Branch B');

        $this->actingAs(new User([
            'role' => 'super_admin',
            'status' => 'active',
        ]));

        $this->assertCount(2, Branch::query()->get());
    }

    public function test_tenant_feature_override_takes_precedence_over_package_feature(): void
    {
        $package = $this->createPackage();
        $tenant = $this->createTenant($package, 'Tenant A');
        $feature = Feature::query()->create([
            'key' => 'feature-override-test',
            'name' => 'Feature override test',
            'is_active' => true,
        ]);
        $package->features()->attach($feature->id, ['enabled' => true]);

        $this->assertTrue($tenant->hasFeature('feature-override-test'));

        $tenant->tenantFeatures()->create([
            'feature_id' => $feature->id,
            'enabled' => false,
        ]);

        $this->assertFalse($tenant->fresh()->hasFeature('feature-override-test'));
    }

    public function test_guest_queries_are_empty_but_tenant_users_can_authenticate(): void
    {
        $package = $this->createPackage();
        $tenant = $this->createTenant($package, 'Tenant A');
        $branchId = $this->createBranch($tenant, 'Branch A');
        $this->createUser($tenant, $branchId, 'login@example.test');

        $this->assertCount(0, Branch::query()->get());
        $this->assertTrue(Auth::attempt([
            'email' => 'login@example.test',
            'password' => 'password',
        ]));
        $this->assertSame($tenant->id, Auth::user()->tenant_id);
    }

    public function test_tenant_paystack_secret_is_encrypted_at_rest(): void
    {
        $tenant = $this->createTenant($this->createPackage(), 'Tenant A');
        $tenant->update(['paystack_secret_key' => 'sk_test_carbayplus']);
        $tenant->refresh();

        $this->assertSame('sk_test_carbayplus', $tenant->paystack_secret_key);
        $this->assertNotSame('sk_test_carbayplus', $tenant->getRawOriginal('paystack_secret_key'));
    }

    public function test_tenant_operations_models_are_scoped_to_the_current_tenant(): void
    {
        $package = $this->createPackage();
        $tenantA = $this->createTenant($package, 'Tenant A');
        $tenantB = $this->createTenant($package, 'Tenant B');
        $branchA = $this->createBranch($tenantA, 'Branch A');
        $branchB = $this->createBranch($tenantB, 'Branch B');
        $userA = $this->createUser($tenantA, $branchA, 'scope-a@example.test');
        $this->createUser($tenantB, $branchB, 'scope-b@example.test');
        $feature = Feature::query()->create(['key' => 'global-service-test', 'name' => 'Global service']);
        $category = VehicleCategory::query()->create(['name' => 'Scope category', 'is_global' => true]);
        $service = Service::query()->create([
            'name' => 'Scoped service',
            'default_price' => 20,
            'company_pct' => 70,
            'worker_pct' => 30,
            'is_global' => true,
            'is_active' => true,
        ]);

        $servicePriceA = $this->createServicePrice($tenantA, $service, $category);
        $servicePriceB = $this->createServicePrice($tenantB, $service, $category);
        $expenseA = $this->createExpense($tenantA, $branchA);
        $expenseB = $this->createExpense($tenantB, $branchB);
        $invoiceA = $this->createAddonInvoice($tenantA, $branchA);
        $invoiceB = $this->createAddonInvoice($tenantB, $branchB);
        $subscriptionA = $this->createSubscriptionInvoice($tenantA);
        $this->createSubscriptionInvoice($tenantB);
        $saleA = $this->createSale($tenantA, $branchA);
        $saleB = $this->createSale($tenantB, $branchB);
        $saleItemA = $this->createSaleItem($saleA, $service);
        $saleItemB = $this->createSaleItem($saleB, $service);
        $this->createAuditLog($tenantA, $userA);
        $auditB = $this->createAuditLog($tenantB, null);
        unset($feature);

        $this->actingAs(User::withoutGlobalScope(TenantScope::class)->findOrFail($userA));

        $this->assertSame([$servicePriceA], ServicePrice::query()->pluck('id')->all());
        $this->assertSame([$expenseA], Expense::query()->pluck('id')->all());
        $this->assertSame([$invoiceA], BranchAddonInvoice::query()->pluck('id')->all());
        $this->assertSame([$subscriptionA], SubscriptionInvoice::query()->pluck('id')->all());
        $this->assertSame([$saleA], WashSale::query()->pluck('id')->all());
        $this->assertSame([$saleItemA], WashSaleItem::query()->pluck('id')->all());
        $this->assertNotSame($servicePriceA, $servicePriceB);
        $this->assertNotSame($expenseA, $expenseB);
        $this->assertNotSame($invoiceA, $invoiceB);
        $this->assertNotSame($saleA, $saleB);
        $this->assertNotSame($saleItemA, $saleItemB);
        $this->assertSame($auditB, DB::table('audit_logs')->where('tenant_id', $tenantB->id)->value('id'));
    }

    public function test_manager_is_scoped_to_their_branch_and_cannot_create_another_branch(): void
    {
        $package = $this->createPackage();
        $tenantA = $this->createTenant($package, 'Tenant A');
        $tenantB = $this->createTenant($package, 'Tenant B');
        $branchA = $this->createBranch($tenantA, 'Branch A');
        $branchA2 = $this->createBranch($tenantA, 'Branch A2');
        $branchB = $this->createBranch($tenantB, 'Branch B');
        $managerId = $this->createUser($tenantA, $branchA, 'manager@example.test', 'manager');
        $this->createUser($tenantA, $branchA2, 'other-manager@example.test', 'manager');
        $this->createUser($tenantB, $branchB, 'other-tenant-manager@example.test', 'manager');
        $workerA = $this->createWorker($tenantA, $branchA, 'Worker A');
        $this->createWorker($tenantA, $branchA2, 'Worker A2');
        $this->createWorker($tenantB, $branchB, 'Worker B');
        $ownInvoice = $this->createAddonInvoice($tenantA, $branchA);
        $this->createAddonInvoice($tenantA, $branchA2);
        $this->createAddonInvoice($tenantB, $branchB);
        $manager = User::withoutGlobalScope(TenantScope::class)->findOrFail($managerId);

        $this->actingAs($manager);

        $this->assertSame([$branchA], Branch::query()->pluck('id')->all());
        $this->assertSame([$workerA], Worker::query()->pluck('id')->all());
        $this->assertSame([$ownInvoice], BranchAddonInvoice::query()->pluck('id')->all());
        $this->assertFalse($manager->can('create', Branch::class));
    }

    public function test_branch_provisioning_uses_included_multi_branch_limit_and_creates_addon_invoice(): void
    {
        $package = $this->createPackage();
        $tenant = $this->createTenant($package, 'Tenant A');
        $includedBranchId = $this->createBranch($tenant, 'Main Branch');
        $userId = $this->createUser($tenant, $includedBranchId, 'ceo@example.test');
        $multiBranch = Feature::query()->create([
            'key' => 'multi_branch',
            'name' => 'Multi branch',
            'is_active' => true,
        ]);
        $package->features()->attach($multiBranch->id, ['enabled' => true, 'limit_value' => 2]);
        $user = User::withoutGlobalScope(TenantScope::class)->findOrFail($userId);
        $this->actingAs($user);

        $included = app(BranchProvisioner::class)->create($user, ['name' => 'Included Branch']);
        $this->assertTrue($included->is_addon_paid);
        $this->assertNull($included->addonInvoice()->first());

        $addon = app(BranchProvisioner::class)->create($user, ['name' => 'Paid Add-on Branch']);
        $invoice = $addon->addonInvoice()->firstOrFail();
        $this->assertFalse($addon->is_addon_paid);
        $this->assertSame('pending', $invoice->status);
        $this->assertSame('10.00', $invoice->amount);

        $invoice->markPaid();
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertTrue($addon->fresh()->is_addon_paid);
    }

    public function test_reference_seeder_populates_ghanaian_categories_makes_models_and_services(): void
    {
        app(ReferenceDataSeeder::class)->run();

        $this->assertDatabaseCount('vehicle_categories', 8);
        $this->assertDatabaseCount('vehicle_makes', 16);
        $this->assertGreaterThanOrEqual(80, DB::table('vehicle_models')->count());
        $this->assertDatabaseCount('services', 7);
        $this->assertDatabaseHas('vehicle_categories', ['name' => 'Trotro', 'is_global' => true]);
        $this->assertDatabaseHas('vehicle_makes', ['name' => 'Toyota', 'is_global' => true]);
        $this->assertDatabaseHas('services', ['name' => 'Body Wash', 'is_global' => true]);
    }

    public function test_ceo_dashboard_and_tenant_resources_load_with_feature_gating(): void
    {
        $package = $this->createPackage();
        $tenant = $this->createTenant($package, 'Tenant A');
        $branchId = $this->createBranch($tenant, 'Branch A');
        $userId = $this->createUser($tenant, $branchId, 'dashboard@example.test');
        $expenseFeature = Feature::query()->create([
            'key' => 'expense_tracking',
            'name' => 'Expense tracking',
            'is_active' => true,
        ]);
        $package->features()->attach($expenseFeature->id, ['enabled' => true]);
        $category = VehicleCategory::query()->create(['name' => 'Dashboard category', 'is_global' => true]);
        $service = Service::query()->create([
            'name' => 'Dashboard service',
            'default_price' => 25,
            'company_pct' => 70,
            'worker_pct' => 30,
            'is_global' => true,
            'is_active' => true,
        ]);
        $this->createServicePrice($tenant, $service, $category);
        $saleId = $this->createSale($tenant, $branchId);
        DB::table('wash_sale_items')->insert([
            'wash_sale_id' => $saleId,
            'service_id' => $service->id,
            'service_name' => $service->name,
            'quantity' => 1,
            'unit_price' => 25,
            'total_amount' => 25,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::withoutGlobalScope(TenantScope::class)->findOrFail($userId));

        $this->get('/app')
            ->assertOk()
            ->assertSee('aria-label="Primary navigation"', false)
            ->assertSee('New wash job')
            ->assertSee('Tenant A')
            ->assertSee('More');
        $this->get('/app/branches')->assertOk();
        $this->get('/app/workers')->assertOk();
        $this->get('/app/service-prices')->assertOk();
        $this->get('/app/expenses')->assertOk();
        $this->get('/app/tenant-settings')->assertOk();
        $this->assertCount(1, WashSaleItem::query()->get());
    }

    public function test_expense_resource_is_denied_when_tenant_feature_is_disabled(): void
    {
        $tenant = $this->createTenant($this->createPackage(), 'Tenant A');
        $branchId = $this->createBranch($tenant, 'Branch A');
        $userId = $this->createUser($tenant, $branchId, 'no-expenses@example.test');
        Feature::query()->create([
            'key' => 'expense_tracking',
            'name' => 'Expense tracking',
            'is_active' => true,
        ]);

        $this->actingAs(User::withoutGlobalScope(TenantScope::class)->findOrFail($userId));

        $this->get('/app/expenses')->assertForbidden();
        $this->get('/app/expenses/create')->assertForbidden();
    }

    public function test_tenant_onboarding_creates_a_ceo_login_and_main_branch(): void
    {
        $package = $this->createPackage();
        $admin = $this->createSuperAdmin();
        $this->actingAs($admin);

        $tenant = app(TenantOnboarder::class)->create([
            'name' => 'New Car Wash',
            'email' => 'business@example.test',
            'package_id' => $package->id,
            'status' => 'active',
        ], [
            'name' => 'Business CEO',
            'email' => 'ceo-login@example.test',
            'password' => 'strong-password',
        ]);

        $ceo = User::withoutGlobalScope(TenantScope::class)
            ->where('email', 'ceo-login@example.test')
            ->firstOrFail();

        $this->assertSame($tenant->id, $ceo->tenant_id);
        $this->assertSame('ceo', $ceo->role);
        $this->assertSame($tenant->main_branch_id, $ceo->branch_id);
        $this->assertTrue($tenant->branches()->where('is_main', true)->exists());
        $this->assertTrue($ceo->canAccessPanel(\Filament\Facades\Filament::getPanel('app')));
        $this->assertTrue(Auth::attempt(['email' => 'ceo-login@example.test', 'password' => 'strong-password']));
        $this->get('/app')->assertOk();
    }

    public function test_super_admin_visiting_tenant_panel_is_redirected_to_admin_instead_of_forbidden(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAs($admin);

        $this->get('/app')->assertRedirect('/superadmin');
    }

    public function test_sale_recording_uses_configured_tenant_service_prices_for_totals(): void
    {
        $package = $this->createPackage();
        $cash = Feature::query()->create([
            'key' => 'cash_payment',
            'name' => 'Cash payments',
            'is_active' => true,
        ]);
        $package->features()->attach($cash->id, ['enabled' => true]);
        $tenant = $this->createTenant($package, 'Tenant A');
        $tenant->update(['cash_enabled' => true]);
        $branchId = $this->createBranch($tenant, 'Branch A');
        $userId = $this->createUser($tenant, $branchId, 'sales-ceo@example.test');
        $workerId = $this->createWorker($tenant, $branchId, 'Assigned worker');
        $category = VehicleCategory::query()->create(['name' => 'Sale category', 'is_global' => true]);
        $service = Service::query()->create([
            'name' => 'Sale service',
            'default_price' => 25,
            'company_pct' => 70,
            'worker_pct' => 30,
            'is_global' => true,
            'is_active' => true,
        ]);
        $priceId = $this->createServicePrice($tenant, $service, $category);
        $user = User::withoutGlobalScope(TenantScope::class)->findOrFail($userId);
        $this->actingAs($user);

        $sale = app(WashSaleRecorder::class)->create($user, [
            'branch_id' => $branchId,
            'worker_id' => $workerId,
            'payment_method' => 'cash',
            'items' => [['service_price_id' => $priceId, 'quantity' => 3]],
        ]);

        $this->assertSame('60.00', $sale->total_amount);
        $this->assertSame('cash', $sale->payment_method);
        $this->assertSame('60.00', $sale->items->sole()->total_amount);
        $this->assertSame('Sale service (Sale category)', $sale->items->sole()->service_name);
        $this->get('/app/wash-sales')->assertOk();
        $this->get('/app/wash-sales/create')->assertOk();
    }

    public function test_sale_recorder_rejects_payment_methods_not_enabled_for_tenant(): void
    {
        $package = $this->createPackage();
        $tenant = $this->createTenant($package, 'Tenant A');
        $branchId = $this->createBranch($tenant, 'Branch A');
        $userId = $this->createUser($tenant, $branchId, 'disabled-cash@example.test');
        $user = User::withoutGlobalScope(TenantScope::class)->findOrFail($userId);
        $this->actingAs($user);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(WashSaleRecorder::class)->create($user, [
            'branch_id' => $branchId,
            'payment_method' => 'cash',
            'items' => [['service_price_id' => 1, 'quantity' => 1]],
        ]);
    }

    public function test_duplicate_external_payment_reference_creates_feature_gated_review_flag(): void
    {
        $package = $this->createPackage();
        $momoFeature = Feature::query()->create([
            'key' => 'momo_manual',
            'name' => 'Manual Mobile Money',
            'is_active' => true,
        ]);
        $fraudFeature = Feature::query()->create([
            'key' => 'fraud_flags',
            'name' => 'Fraud flags',
            'is_active' => true,
        ]);
        $package->features()->attach($momoFeature->id, ['enabled' => true]);
        $package->features()->attach($fraudFeature->id, ['enabled' => true]);

        $tenant = $this->createTenant($package, 'Duplicate Payment Tenant');
        $tenant->update(['momo_enabled' => true]);
        $branchId = $this->createBranch($tenant, 'Mobile Money Branch');
        $userId = $this->createUser($tenant, $branchId, 'duplicate-payment@example.test');
        $category = VehicleCategory::query()->create([
            'name' => 'Duplicate payment category',
            'is_global' => true,
        ]);
        $service = Service::query()->create([
            'name' => 'Duplicate payment service',
            'default_price' => 30,
            'company_pct' => 70,
            'worker_pct' => 30,
            'is_global' => true,
            'is_active' => true,
        ]);
        $priceId = $this->createServicePrice($tenant, $service, $category);
        $user = User::withoutGlobalScope(TenantScope::class)->findOrFail($userId);
        $this->actingAs($user);

        $payload = [
            'branch_id' => $branchId,
            'payment_method' => 'momo',
            'payment_reference' => 'MOMO-REF-001',
            'items' => [['service_price_id' => $priceId, 'quantity' => 1]],
        ];
        $firstSale = app(WashSaleRecorder::class)->create($user, $payload);
        $duplicateSale = app(WashSaleRecorder::class)->create($user, $payload);

        $this->assertSame('completed', $firstSale->status);
        $this->assertSame('completed', $duplicateSale->status);
        $flag = FraudFlag::query()->where('wash_sale_id', $duplicateSale->id)->firstOrFail();
        $this->assertSame('duplicate_payment_reference', $flag->flag_type);
        $this->assertSame('open', $flag->status);
        $this->assertSame($branchId, $flag->branch_id);
        $this->assertSame(1, FraudFlag::query()->count());
        $this->get('/app/fraud-flags')->assertOk();
    }

    public function test_duplicate_payment_reference_does_not_create_flag_without_feature(): void
    {
        $package = $this->createPackage();
        $momoFeature = Feature::query()->create([
            'key' => 'momo_manual',
            'name' => 'Manual Mobile Money',
            'is_active' => true,
        ]);
        $package->features()->attach($momoFeature->id, ['enabled' => true]);
        $tenant = $this->createTenant($package, 'No Fraud Feature Tenant');
        $tenant->update(['momo_enabled' => true]);
        $branchId = $this->createBranch($tenant, 'No Fraud Branch');
        $userId = $this->createUser($tenant, $branchId, 'no-fraud-feature@example.test');
        $category = VehicleCategory::query()->create([
            'name' => 'No fraud category',
            'is_global' => true,
        ]);
        $service = Service::query()->create([
            'name' => 'No fraud service',
            'default_price' => 30,
            'company_pct' => 70,
            'worker_pct' => 30,
            'is_global' => true,
            'is_active' => true,
        ]);
        $priceId = $this->createServicePrice($tenant, $service, $category);
        $user = User::withoutGlobalScope(TenantScope::class)->findOrFail($userId);
        $this->actingAs($user);
        $payload = [
            'branch_id' => $branchId,
            'payment_method' => 'momo',
            'payment_reference' => 'MOMO-REF-NO-FEATURE',
            'items' => [['service_price_id' => $priceId, 'quantity' => 1]],
        ];

        app(WashSaleRecorder::class)->create($user, $payload);
        app(WashSaleRecorder::class)->create($user, $payload);

        $this->assertDatabaseCount('fraud_flags', 0);
    }

    public function test_worker_limit_counts_active_workers_and_blocks_creation_or_reactivation(): void
    {
        $package = $this->createPackage();
        $package->update(['worker_limit' => 1]);
        $tenant = $this->createTenant($package, 'Limited Worker Tenant');
        $branchId = $this->createBranch($tenant, 'Limited Worker Branch');
        $userId = $this->createUser($tenant, $branchId, 'limited-worker@example.test');
        $this->actingAs(User::withoutGlobalScope(TenantScope::class)->findOrFail($userId));

        $firstWorker = Worker::query()->create([
            'branch_id' => $branchId,
            'name' => 'First worker',
            'pin' => '1234',
            'type' => 'permanent',
            'default_share_pct' => 10,
            'status' => 'active',
        ]);

        $this->assertSame(1, $tenant->fresh()->activeWorkerCount());
        $this->assertFalse($tenant->fresh()->hasAvailableWorkerSeat());

        try {
            Worker::query()->create([
                'branch_id' => $branchId,
                'name' => 'Second worker',
                'pin' => '5678',
                'type' => 'casual',
                'default_share_pct' => 10,
                'status' => 'active',
            ]);
            $this->fail('Creating an active worker over the package limit should be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('name', $exception->errors());
        }

        $inactiveWorker = Worker::query()->create([
            'branch_id' => $branchId,
            'name' => 'Inactive worker',
            'pin' => '5678',
            'type' => 'casual',
            'default_share_pct' => 10,
            'status' => 'inactive',
        ]);

        try {
            $inactiveWorker->update(['status' => 'active']);
            $this->fail('Reactivating a worker over the package limit should be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('name', $exception->errors());
        }

        $firstWorker->update(['status' => 'inactive']);
        $inactiveWorker->update(['status' => 'active']);

        $this->assertSame(1, $tenant->fresh()->activeWorkerCount());
        $this->assertFalse($tenant->fresh()->hasAvailableWorkerSeat());
    }

    public function test_reports_export_is_tenant_scoped_date_filtered_and_formula_safe(): void
    {
        $package = $this->createPackage();
        $reportsFeature = Feature::query()->create([
            'key' => 'reports_export',
            'name' => 'Reports export',
            'is_active' => true,
        ]);
        $expensesFeature = Feature::query()->create([
            'key' => 'expense_tracking',
            'name' => 'Expense tracking',
            'is_active' => true,
        ]);
        $package->features()->attach($reportsFeature->id, ['enabled' => true]);
        $package->features()->attach($expensesFeature->id, ['enabled' => true]);

        $tenant = $this->createTenant($package, 'Report Tenant');
        $branchId = $this->createBranch($tenant, 'Report Branch');
        $userId = $this->createUser($tenant, $branchId, 'reports@example.test');
        $service = Service::query()->create([
            'name' => '=2+3',
            'default_price' => 20,
            'company_pct' => 70,
            'worker_pct' => 30,
            'is_global' => true,
            'is_active' => true,
        ]);

        $saleId = $this->createSale($tenant, $branchId);
        DB::table('wash_sales')->where('id', $saleId)->update([
            'reference' => 'REPORT-SALE',
            'sold_at' => now(),
        ]);
        $this->createSaleItem($saleId, $service);

        $outOfRangeSale = $this->createSale($tenant, $branchId);
        DB::table('wash_sales')->where('id', $outOfRangeSale)->update([
            'sold_at' => now()->subDay(),
        ]);
        $this->createSaleItem($outOfRangeSale, $service);

        $expenseId = $this->createExpense($tenant, $branchId);
        DB::table('expenses')->where('id', $expenseId)->update([
            'note' => 'fuel',
            'spent_at' => now(),
        ]);

        $otherTenant = $this->createTenant($package, 'Other Report Tenant');
        $otherBranchId = $this->createBranch($otherTenant, 'Other Branch');
        $otherSaleId = $this->createSale($otherTenant, $otherBranchId);
        $this->createSaleItem($otherSaleId, $service);
        $this->createExpense($otherTenant, $otherBranchId);

        $user = User::withoutGlobalScope(TenantScope::class)->findOrFail($userId);
        $this->actingAs($user);

        $rows = [
            [
                'Date', 'Type', 'Reference', 'Branch', 'Worker', 'Payment method',
                'Description', 'Quantity', 'Income (GHS)', 'Expense (GHS)',
            ],
            [
                now()->toDateTimeString(), 'Sale', 'REPORT-SALE', 'Report Branch', null,
                'cash', "'=2+3", 1, '20.00', '0.00',
            ],
            [
                now()->toDateTimeString(), 'Expense', 'EXP-'.$expenseId, 'Report Branch',
                null, null, 'supplies: fuel', null, '0.00', '15.00',
            ],
        ];

        $expectedCsv = "\xEF\xBB\xBF".$this->csvRows($rows);
        $today = today()->toDateString();
        $filename = "carbayplus-report-{$today}-to-{$today}.csv";

        Livewire::test(Reports::class)
            ->set('data.start_date', $today)
            ->set('data.end_date', $today)
            ->call('export')
            ->assertFileDownloaded($filename, $expectedCsv);
    }

    public function test_reports_page_is_hidden_without_reports_export_feature(): void
    {
        $tenant = $this->createTenant($this->createPackage(), 'No Reports Tenant');
        $branchId = $this->createBranch($tenant, 'No Reports Branch');
        $userId = $this->createUser($tenant, $branchId, 'no-reports@example.test');
        $this->actingAs(User::withoutGlobalScope(TenantScope::class)->findOrFail($userId));

        $this->get('/app/reports')->assertForbidden();
    }

    public function test_manager_report_export_contains_only_the_assigned_branch(): void
    {
        $package = $this->createPackage();
        $feature = Feature::query()->create([
            'key' => 'reports_export',
            'name' => 'Reports export',
            'is_active' => true,
        ]);
        $package->features()->attach($feature->id, ['enabled' => true]);

        $tenant = $this->createTenant($package, 'Manager Report Tenant');
        $managerBranchId = $this->createBranch($tenant, 'Manager Branch');
        $otherBranchId = $this->createBranch($tenant, 'Other Branch');
        $managerId = $this->createUser(
            $tenant,
            $managerBranchId,
            'branch-report-manager@example.test',
            'manager',
        );
        $service = Service::query()->create([
            'name' => 'Branch report service',
            'default_price' => 20,
            'company_pct' => 70,
            'worker_pct' => 30,
            'is_global' => true,
            'is_active' => true,
        ]);
        $managerSaleId = $this->createSale($tenant, $managerBranchId);
        $otherSaleId = $this->createSale($tenant, $otherBranchId);
        $this->createSaleItem($managerSaleId, $service);
        $this->createSaleItem($otherSaleId, $service);

        $this->actingAs(User::withoutGlobalScope(TenantScope::class)->findOrFail($managerId));

        $rows = [
            [
                'Date', 'Type', 'Reference', 'Branch', 'Worker', 'Payment method',
                'Description', 'Quantity', 'Income (GHS)', 'Expense (GHS)',
            ],
            [
                now()->toDateTimeString(), 'Sale', 'SALE-'.$managerSaleId, 'Manager Branch',
                null, 'cash', 'Branch report service', 1, '20.00', '0.00',
            ],
        ];
        $today = today()->toDateString();

        Livewire::test(Reports::class)
            ->set('data.start_date', $today)
            ->set('data.end_date', $today)
            ->call('export')
            ->assertFileDownloaded(
                "carbayplus-report-{$today}-to-{$today}.csv",
                "\xEF\xBB\xBF".$this->csvRows($rows),
            );
    }

    public function test_audit_trail_records_tenant_changes_without_sensitive_credentials(): void
    {
        $package = $this->createPackage();
        $feature = Feature::query()->create([
            'key' => 'audit_trail',
            'name' => 'Audit trail',
            'is_active' => true,
        ]);
        $package->features()->attach($feature->id, ['enabled' => true]);

        $tenant = $this->createTenant($package, 'Audit Tenant');
        $branchId = $this->createBranch($tenant, 'Audit Branch');
        $userId = $this->createUser($tenant, $branchId, 'audit-ceo@example.test');
        $this->actingAs(User::withoutGlobalScope(TenantScope::class)->findOrFail($userId));

        $worker = Worker::query()->create([
            'branch_id' => $branchId,
            'name' => 'Audited Worker',
            'pin' => '3456',
            'type' => 'permanent',
            'default_share_pct' => 15,
            'status' => 'active',
        ]);
        $worker->update(['status' => 'inactive']);

        $createdLog = AuditLog::query()->where('event', 'Worker.created')->firstOrFail();
        $updatedLog = AuditLog::query()->where('event', 'Worker.updated')->firstOrFail();

        $this->assertSame($tenant->id, $createdLog->tenant_id);
        $this->assertSame($branchId, $createdLog->branch_id);
        $this->assertSame($userId, $createdLog->user_id);
        $this->assertSame('Audited Worker', $createdLog->new_values['name']);
        $this->assertArrayNotHasKey('pin', $createdLog->new_values);
        $this->assertArrayNotHasKey('password', $createdLog->new_values);
        $this->assertSame('active', $updatedLog->old_values['status']);
        $this->assertSame('inactive', $updatedLog->new_values['status']);
        $this->assertSame([$createdLog->id, $updatedLog->id], AuditLog::query()
            ->where('auditable_type', Worker::class)
            ->orderBy('id')
            ->pluck('id')
            ->all());
    }

    public function test_audit_trail_is_feature_gated_and_branch_scoped_for_managers(): void
    {
        $package = $this->createPackage();
        $feature = Feature::query()->create([
            'key' => 'audit_trail',
            'name' => 'Audit trail',
            'is_active' => true,
        ]);
        $package->features()->attach($feature->id, ['enabled' => true]);
        $tenant = $this->createTenant($package, 'Audit Scope Tenant');
        $managerBranchId = $this->createBranch($tenant, 'Manager Audit Branch');
        $otherBranchId = $this->createBranch($tenant, 'Other Audit Branch');
        $managerId = $this->createUser(
            $tenant,
            $managerBranchId,
            'audit-manager@example.test',
            'manager',
        );
        $branchLogId = DB::table('audit_logs')->insertGetId([
            'tenant_id' => $tenant->id,
            'branch_id' => $managerBranchId,
            'user_id' => $managerId,
            'event' => 'Worker.updated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherLogId = DB::table('audit_logs')->insertGetId([
            'tenant_id' => $tenant->id,
            'branch_id' => $otherBranchId,
            'user_id' => $managerId,
            'event' => 'Worker.updated',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::withoutGlobalScope(TenantScope::class)->findOrFail($managerId));

        $this->assertSame([$branchLogId], AuditLog::query()->pluck('id')->all());
        $this->get('/app/audit-logs')->assertOk();
        $this->assertNotSame($branchLogId, $otherLogId);
    }

    public function test_audit_trail_does_not_record_changes_when_feature_is_disabled(): void
    {
        $tenant = $this->createTenant($this->createPackage(), 'No Audit Tenant');
        $branchId = $this->createBranch($tenant, 'No Audit Branch');
        $userId = $this->createUser($tenant, $branchId, 'no-audit@example.test');
        $this->actingAs(User::withoutGlobalScope(TenantScope::class)->findOrFail($userId));

        Worker::query()->create([
            'branch_id' => $branchId,
            'name' => 'Untracked Worker',
            'pin' => '3456',
            'type' => 'casual',
            'default_share_pct' => 10,
            'status' => 'active',
        ]);

        $this->assertDatabaseMissing('audit_logs', ['tenant_id' => $tenant->id]);
        $this->get('/app/audit-logs')->assertForbidden();
    }

    private function csvRows(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($stream, $row, ',', '"', '');
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return $contents;
    }

    private function createPackage(): Package
    {
        return Package::query()->create([
            'name' => 'Test package',
            'price' => 100,
            'billing_cycle' => 'monthly',
            'branch_addon_price' => 10,
            'worker_limit' => 10,
            'sms_credits' => 10,
            'is_active' => true,
        ]);
    }

    private function createSuperAdmin(): User
    {
        return User::withoutGlobalScopes()->create([
            'name' => 'Test Super Admin',
            'email' => 'admin-'.uniqid().'@example.test',
            'password' => 'password',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }

    private function createTenant(Package $package, string $name): Tenant
    {
        return Tenant::query()->create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '-', $name)).'@example.test',
            'package_id' => $package->id,
            'status' => 'active',
        ]);
    }

    private function createBranch(Tenant $tenant, string $name): int
    {
        return DB::table('branches')->insertGetId([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'is_main' => true,
            'status' => 'active',
            'is_addon_paid' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createUser(Tenant $tenant, int $branchId, string $email, string $role = 'ceo'): int
    {
        return DB::table('users')->insertGetId([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'role' => $role,
            'name' => $email,
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createWorker(Tenant $tenant, int $branchId, string $name): int
    {
        return DB::table('workers')->insertGetId([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'name' => $name,
            'pin' => Hash::make('1234'),
            'type' => 'permanent',
            'default_share_pct' => 10,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createTenantFeature(Tenant $tenant, Feature $feature): int
    {
        return DB::table('tenant_features')->insertGetId([
            'tenant_id' => $tenant->id,
            'feature_id' => $feature->id,
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createServicePrice(Tenant $tenant, Service $service, VehicleCategory $category): int
    {
        return DB::table('service_prices')->insertGetId([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'vehicle_category_id' => $category->id,
            'price' => 20,
            'company_pct' => 70,
            'worker_pct' => 30,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createExpense(Tenant $tenant, int $branchId): int
    {
        return DB::table('expenses')->insertGetId([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'category' => 'supplies',
            'amount' => 15,
            'recorded_by' => null,
            'spent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAddonInvoice(Tenant $tenant, int $branchId): int
    {
        return DB::table('branch_addon_invoices')->insertGetId([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'invoice_number' => 'BAI-TEST-'.uniqid(),
            'amount' => 10,
            'currency' => 'GHS',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSubscriptionInvoice(Tenant $tenant): int
    {
        return DB::table('subscriptions_invoices')->insertGetId([
            'tenant_id' => $tenant->id,
            'invoice_number' => 'SUB-TEST-'.uniqid(),
            'amount' => 100,
            'currency' => 'GHS',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSale(Tenant $tenant, int $branchId): int
    {
        return DB::table('wash_sales')->insertGetId([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'total_amount' => 20,
            'status' => 'completed',
            'sold_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSaleItem(int $saleId, Service $service): int
    {
        return DB::table('wash_sale_items')->insertGetId([
            'wash_sale_id' => $saleId,
            'service_id' => $service->id,
            'service_name' => $service->name,
            'quantity' => 1,
            'unit_price' => 20,
            'total_amount' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAuditLog(Tenant $tenant, ?int $userId): int
    {
        return DB::table('audit_logs')->insertGetId([
            'tenant_id' => $tenant->id,
            'branch_id' => null,
            'user_id' => $userId,
            'event' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
