<?php

namespace Tests\Unit;

use App\Models\Job;
use App\Models\JobWorker;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Worker;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Worker $worker;

    private Job $job;

    protected function setUp(): void
    {
        parent::setUp();
        $package = Package::query()->create([
            'name' => 'Wallet package',
            'price' => 0,
            'billing_cycle' => 'monthly',
            'branch_addon_price' => 0,
            'worker_limit' => 10,
            'sms_credits' => 0,
            'is_active' => true,
        ]);
        $this->tenant = Tenant::withoutGlobalScopes()->create([
            'name' => 'Wallet company',
            'email' => 'wallet@example.test',
            'package_id' => $package->id,
            'status' => 'active',
        ]);
        $branchId = DB::table('branches')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main branch',
            'is_main' => true,
            'status' => 'active',
            'is_addon_paid' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $managerId = DB::table('users')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $branchId,
            'role' => 'manager',
            'name' => 'Manager',
            'email' => 'manager@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Auth::login(User::withoutGlobalScopes()->findOrFail($managerId));

        $workerId = DB::table('workers')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $branchId,
            'name' => 'Ama Worker',
            'phone' => '0244000001',
            'pin' => bcrypt('1234'),
            'type' => 'permanent',
            'default_share_pct' => 10,
            'payout_mode' => 'daily',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->worker = Worker::query()->findOrFail($workerId);
        $this->job = Job::query()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $branchId,
            'manager_id' => $managerId,
            'plate' => 'GR1234-24',
            'total_amount' => 100,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'status' => 'open',
        ]);
    }

    public function test_instant_credit_is_available_and_debit_recalculates_balance(): void
    {
        $jobWorker = $this->assignWorker(25);
        $service = app(WalletService::class);

        $service->credit($this->worker, $this->job, 25, 'instant', $jobWorker);
        $wallet = Wallet::query()->where('worker_id', $this->worker->id)->firstOrFail();
        $this->assertSame('25.00', $wallet->available_balance);
        $this->assertSame('0.00', $wallet->pending_balance);

        $service->debit($this->worker, 5, 'cash advance');
        $this->assertSame('20.00', $wallet->fresh()->available_balance);

        $this->expectException(ValidationException::class);
        $service->debit($this->worker, 21, 'overdraw');
    }

    public function test_scheduled_credit_is_pending_and_auto_queues_a_payout(): void
    {
        $jobWorker = $this->assignWorker(18);
        app(WalletService::class)->credit($this->worker, $this->job, 18, 'weekly', $jobWorker);

        $wallet = Wallet::query()->where('worker_id', $this->worker->id)->firstOrFail();
        $this->assertSame('0.00', $wallet->available_balance);
        $this->assertSame('18.00', $wallet->pending_balance);
        $this->assertDatabaseHas('payouts', [
            'worker_id' => $this->worker->id,
            'job_worker_id' => $jobWorker->id,
            'amount' => 18,
            'status' => 'queued',
            'payout_mode' => 'weekly',
        ]);
    }

    public function test_cancelling_job_reverses_instant_credits_and_rejects_scheduled_payouts(): void
    {
        $instantJobWorker = $this->assignWorker(10);
        $service = app(WalletService::class);
        $service->credit($this->worker, $this->job, 10, 'instant', $instantJobWorker);
        $service->cancelJob($this->job);

        $wallet = Wallet::query()->where('worker_id', $this->worker->id)->firstOrFail();
        $this->assertSame('0.00', $wallet->available_balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'job_id' => $this->job->id,
            'type' => 'debit',
            'status' => 'paid',
            'reference' => 'JOB-CANCEL-'.$this->job->id,
        ]);
        $this->assertSame('cancelled', $this->job->fresh()->status);
    }

    public function test_payout_reserves_balance_and_settlement_is_recorded_in_ledger(): void
    {
        $jobWorker = $this->assignWorker(40);
        $service = app(WalletService::class);
        $service->credit($this->worker, $this->job, 40, 'instant', $jobWorker);
        $payout = $service->payout($this->worker, 12, 'momo', 'REQ-1');

        $this->assertSame('28.00', Wallet::query()->where('worker_id', $this->worker->id)->firstOrFail()->available_balance);
        $service->settlePayout($payout, 'momo', 'MOMO-1', auth()->id());

        $this->assertDatabaseHas('payouts', [
            'id' => $payout->id,
            'status' => 'paid',
            'method' => 'momo',
            'reference' => 'MOMO-1',
        ]);
        $this->assertSame('28.00', Wallet::query()->where('worker_id', $this->worker->id)->firstOrFail()->available_balance);
    }

    private function assignWorker(float $amount): JobWorker
    {
        return JobWorker::query()->create([
            'job_id' => $this->job->id,
            'worker_id' => $this->worker->id,
            'share_amount' => $amount,
            'payout_mode' => 'daily',
        ]);
    }
}
