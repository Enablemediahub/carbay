<?php

namespace Tests\Feature;

use App\Filament\App\Pages\CashReconciliationPage;
use App\Filament\App\Pages\DataPrivacy;
use App\Filament\App\Pages\NewWashJob;
use App\Filament\App\Pages\PayoutApprovalPage;
use App\Filament\App\Pages\ServiceAgreement;
use App\Filament\App\Pages\TodayJobs;
use App\Filament\App\Pages\WorkerCheckInPage;
use App\Filament\App\Resources\WashSaleResource;
use App\Filament\App\Resources\WashSaleResource\Pages\ListWashSales;
use App\Models\Feature;
use App\Models\Job;
use App\Models\JobWorker;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PlateScan;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VehicleCategory;
use App\Models\Wallet;
use App\Models\Worker;
use App\Services\WalletService;
use App\Support\DashboardSales;
use App\Support\WorkerSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ManagerJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_record_a_wash_job_and_credit_the_assigned_worker(): void
    {
        $this->travelTo(Carbon::parse('2026-10-08 12:00:00'));
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
        $this->get('/app')
            ->assertOk()
            ->assertSee('New job')
            ->assertSee('Start a wash job')
            ->assertSee('Sales collected today')
            ->assertSee('Main branch')
            ->assertSee('This week')
            ->assertSee('Jobs completed today')
            ->assertSee('Open jobs')
            ->assertSee('Active workers')
            ->assertSee('Active branches')
            ->assertSee('Sales by branch this month')
            ->assertSee('Top workers this month')
            ->assertSee('Top services this month')
            ->assertSee(NewWashJob::getUrl(panel: 'app'), false);
        $jobPage = $this->get(NewWashJob::getUrl(panel: 'app'))
            ->assertOk()
            ->assertSee('Save wash job')
            ->assertSee('Scan plate live')
            ->assertSee('processed locally');
        $dom = new \DOMDocument;
        @$dom->loadHTML($jobPage->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//div[contains(@class, "carbay-new-wash-job")][@*[name()="wire:id"]]')->length);
        $this->assertSame(0, $xpath->query('//script[@*[name()="wire:id"]]')->length);
        foreach ([
            TodayJobs::class,
            CashReconciliationPage::class,
            WorkerCheckInPage::class,
            PayoutApprovalPage::class,
            ServiceAgreement::class,
            DataPrivacy::class,
        ] as $managerPage) {
            $this->get($managerPage::getUrl(panel: 'app'))->assertOk();
        }

        Livewire::test(NewWashJob::class)
            ->assertSee('carbay-new-wash-job')
            ->assertSee('acceptLocalPlateScan')
            ->call('selectClientMode', true)
            ->assertSet('createClient', true)
            ->assertSet('clientId', null)
            ->assertSee('id="client-name"', false)
            ->assertSee('id="client-phone"', false)
            ->call('selectClientMode', false)
            ->assertSet('createClient', false)
            ->assertSee('id="client-search"', false)
            ->call('acceptLocalPlateScan', 'gr1234-24', 0.91)
            ->assertSet('plate', 'GR1234-24')
            ->assertSet('plateConfidence', 0.91)
            ->assertSet('plateConfirmed', true);

        Livewire::test(NewWashJob::class)
            ->call('acceptLocalPlateScan', 'not-a-plate', 0.91)
            ->assertHasErrors(['plate']);

        Livewire::test(NewWashJob::class)
            ->call('acceptLocalPlateScan', 'GR1234-24', 0.55)
            ->assertSet('plate', 'GR1234-24')
            ->assertSet('plateConfirmed', false);

        Livewire::test(NewWashJob::class)
            ->call('acceptLocalPlateScan', 'GR1234-24', 0)
            ->assertSet('plate', 'GR1234-24')
            ->assertSet('localPlateScanned', true)
            ->assertSet('plateConfirmed', false)
            ->assertSee('I confirm this registration is correct');

        Livewire::test(NewWashJob::class)
            ->call('acceptLocalPlateScan', 'GR1234-24', 1.1)
            ->assertHasErrors(['confidence']);

        Livewire::test(NewWashJob::class)
            ->assertSee('carbay-mobile-sticky-action')
            ->set('plate', 'GR 1234-24')
            ->set('plateScanId', $scan->id)
            ->set('plateConfidence', 0.64)
            ->set('vehicleCategoryId', (string) $category->id)
            ->set('selectedServices', [(string) $service->id])
            ->set('paymentMethod', 'cash')
            ->call('submit')
            ->assertHasErrors(['selectedWorkers'])
            ->set('workerToAdd', (string) $workerId)
            ->assertSet('selectedWorkers', [$workerId])
            ->call('removeWorker', $workerId)
            ->assertSet('selectedWorkers', [])
            ->set('workerToAdd', (string) $workerId)
            ->call('submit')
            ->assertHasErrors(['plate'])
            ->set('plateConfirmed', true)
            ->set('workerShares.'.$workerId, 0)
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
        $jobId = DB::table('jobs')->where('plate', 'GR 1234-24')->value('id');
        $this->assertDatabaseHas('job_workers', [
            'job_id' => $jobId,
            'worker_id' => $workerId,
            'share_amount' => 18,
            'payout_mode' => 'instant',
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'worker_id' => $workerId,
            'job_id' => $jobId,
            'amount' => 18,
            'type' => 'credit',
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('payments', [
            'manager_id' => $managerId,
            'method' => 'cash',
            'status' => 'paid',
            'amount' => 60,
        ]);
        $this->assertSame('18.00', Wallet::query()->where('worker_id', $workerId)->firstOrFail()->available_balance);
        $sales = app(DashboardSales::class);
        $this->assertSame(60.0, $sales->tenantToday($tenant->id, $branchId));
        $this->assertSame(60.0, $sales->tenantToday($tenant->id));
        $this->assertSame(60.0, $sales->tenantBetween($tenant->id, $branchId, today()->startOfDay(), today()->endOfDay()));
        $this->assertSame(60.0, $sales->platformToday());
        $this->get('/app')
            ->assertOk()
            ->assertSee('GH₵ 60.00');
        $job = Job::query()->findOrFail($jobId);
        Livewire::test(ListWashSales::class)->assertCanNotSeeTableRecords([$job]);
        Livewire::test(TodayJobs::class)->call('complete', $jobId)->assertHasNoErrors();
        $saleJob = WashSaleResource::getEloquentQuery()->findOrFail($jobId);
        Livewire::test(ListWashSales::class)
            ->assertCanSeeTableRecords([$job->fresh()])
            ->assertSee('Ama')
            ->assertTableColumnStateSet('worker_share_total', 18, $saleJob)
            ->assertTableColumnStateSet('company_share_total', 42, $saleJob);
        Livewire::test(TodayJobs::class)->call('complete', $jobId)->assertHasNoErrors();
        Livewire::test(ListWashSales::class)->assertCountTableRecords(1);
        $job->update(['payment_status' => 'pending']);
        Livewire::test(ListWashSales::class)->assertCanSeeTableRecords([$job]);
        $job->update(['status' => 'cancelled']);
        Livewire::test(ListWashSales::class)->assertCanNotSeeTableRecords([$job]);
        $job->update(['status' => 'completed', 'payment_status' => 'paid']);
        $this->get('/app/wash-sales')->assertOk()->assertDontSee('Record sale');
        $this->get('/app/wash-sales/create')->assertNotFound();
        $this->assertDatabaseCount('wash_sales', 0);
        $otherBranch = DB::table('branches')->insertGetId([
            'tenant_id' => $tenant->id, 'name' => 'Other branch', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $hiddenJob = $job->replicate();
        $hiddenJob->branch_id = $otherBranch;
        $hiddenJob->save();
        Livewire::test(ListWashSales::class)->assertCanNotSeeTableRecords([$hiddenJob]);
        $hiddenJob->delete();
        $worker = Worker::query()->findOrFail($workerId);
        $this->assertEquals(18, app(WorkerSettlement::class)->today($worker)['owed']);
        Livewire::test(TodayJobs::class)->call('payWorker', $workerId, 18)->assertHasNoErrors()->assertSee('Fully paid for today');
        $this->assertEquals(0, app(WorkerSettlement::class)->today($worker)['owed']);
        $this->assertEquals(18, app(WorkerSettlement::class)->today($worker)['paid']);
        $this->assertSame('0.00', Wallet::query()->where('worker_id', $workerId)->firstOrFail()->available_balance);
        Livewire::test(TodayJobs::class)->call('payWorker', $workerId, 18)->assertHasErrors('payout');
        $this->assertDatabaseCount('payouts', 1);
        $this->assertSame(60.0, $sales->workerToday(Worker::withoutGlobalScopes()->findOrFail($workerId)));
        $this->assertDatabaseHas('plate_scans', [
            'id' => $scan->id,
            'job_id' => DB::table('jobs')->where('plate', 'GR 1234-24')->value('id'),
            'corrected_value' => 'GR 1234-24',
        ]);

        $secondWorker = Worker::query()->create([
            'tenant_id' => $tenant->id, 'branch_id' => $branchId, 'name' => 'Second Worker',
            'phone' => '0244000097', 'pin' => '1234', 'status' => 'active', 'payout_mode' => 'daily',
        ]);
        DB::table('service_prices')->where('tenant_id', $tenant->id)->update(['price' => 60.04]);
        Livewire::test(NewWashJob::class)
            ->set('plate', 'GR4321-24')->set('vehicleCategoryId', (string) $category->id)
            ->set('selectedServices', [(string) $service->id])
            ->set('selectedWorkers', [(string) $secondWorker->id, (string) $workerId])
            ->set('workerShares', [$workerId => 17, $secondWorker->id => 1.01])
            ->call('submit')->assertHasNoErrors();
        $splitJob = Job::query()->where('plate', 'GR4321-24')->firstOrFail();
        $this->assertDatabaseHas('job_workers', ['job_id' => $splitJob->id, 'worker_id' => $workerId, 'share_amount' => 9]);
        $this->assertDatabaseHas('job_workers', ['job_id' => $splitJob->id, 'worker_id' => $secondWorker->id, 'share_amount' => 9.01]);
        $this->assertDatabaseHas('wallet_transactions', ['job_id' => $splitJob->id, 'worker_id' => $secondWorker->id, 'amount' => 9.01]);
        Livewire::test(TodayJobs::class)->call('complete', $splitJob->id)->assertHasNoErrors();
        Livewire::test(TodayJobs::class)->set('workerId', (string) $secondWorker->id)
            ->assertSee('GR4321-24')->assertDontSee('GR 1234-24');
        Livewire::test(TodayJobs::class)->set('paymentMethods.'.$secondWorker->id, 'momo')
            ->call('payWorker', $secondWorker->id, 9.01)->assertHasErrors('reference');
        Livewire::test(TodayJobs::class)->set('paymentMethods.'.$secondWorker->id, 'momo')
            ->set('paymentReferences.'.$secondWorker->id, 'TRANSFER-TEST')
            ->call('payWorker', $secondWorker->id, 9.01)->assertHasNoErrors();
        $this->assertSame('0.00', Wallet::query()->where('worker_id', $secondWorker->id)->firstOrFail()->pending_balance);
        $this->assertSame('0.00', Wallet::query()->where('worker_id', $secondWorker->id)->firstOrFail()->available_balance);
        $this->assertEquals(0, app(WorkerSettlement::class)->today($secondWorker)['owed']);
        $request = app(WalletService::class)->payout($worker, 3, 'cash');
        Livewire::test(TodayJobs::class)->call('payWorker', $workerId, 9)->assertHasErrors('payout');
        app(WalletService::class)->settlePayout($request, 'cash', '', $managerId);
        $this->assertEquals(6, app(WorkerSettlement::class)->today($worker)['owed']);
        Livewire::test(TodayJobs::class)->call('payWorker', $workerId, 9)->assertHasErrors('payout');
        Livewire::test(TodayJobs::class)->call('payWorker', $workerId, 6)->assertHasNoErrors();
        $this->assertEquals(27, app(WorkerSettlement::class)->today($worker)['earned']);
        $this->assertEquals(27, app(WorkerSettlement::class)->today($worker)['paid']);
        $this->assertEquals(0, app(WorkerSettlement::class)->today($worker)['owed']);
        $pinFeature = Feature::query()->create(['key' => 'worker_pin_login', 'name' => 'Worker PIN', 'is_active' => true]);
        $package->features()->attach($pinFeature->id, ['enabled' => true]);
        $this->actingAs($worker, 'worker')->get(route('worker.dashboard'))->assertOk()
            ->assertSee('Paid from today\'s earnings', false)->assertSee('Still owed for today')->assertSee('Fully paid for today')
            ->assertViewHas('todaySettlement', fn (array $summary): bool => $summary['earned'] == 27 && $summary['paid'] == 27 && $summary['owed'] == 0);
        $job->forceFill(['created_at' => now()->startOfMonth()->subDay()])->save();
        foreach ([['date' => now()->startOfWeek()->subDay(), 'share' => 13], ['date' => now()->startOfYear()->subDay(), 'share' => 99]] as $entry) {
            $historical = $job->replicate();
            $historical->created_at = $entry['date'];
            $historical->save();
            $assignment = JobWorker::query()->create([
                'job_id' => $historical->id, 'worker_id' => $workerId, 'share_amount' => $entry['share'], 'payout_mode' => 'instant',
            ]);
            app(WalletService::class)->credit($worker, $historical, $entry['share'], 'instant', $assignment);
        }
        $this->get(route('worker.dashboard'))->assertOk()
            ->assertViewHas('earningPeriods', fn (array $periods): bool => $periods['today']['earned'] == 9
                && $periods['week']['earned'] == 9 && $periods['month']['earned'] == 22 && $periods['year']['earned'] == 40
                && $periods['today']['cars'] == 1 && $periods['month']['cars'] == 2 && $periods['year']['cars'] == 3)
            ->assertSee('id="hero-earnings" aria-live="polite">GH₵ 9.00', false)
            ->assertDontSee('Sales from your washes today')->assertDontSee('Earned this month')
            ->assertSee('value="today" selected', false)->assertSee('value="year"', false);
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
