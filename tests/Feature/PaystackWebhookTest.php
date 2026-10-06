<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Job;
use App\Models\JobWorker;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\PaystackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaystackWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_paystack_success_updates_payment_and_job_status(): void
    {
        $package = Package::query()->create([
            'name' => 'Paystack package',
            'price' => 0,
            'billing_cycle' => 'monthly',
            'branch_addon_price' => 0,
            'worker_limit' => 10,
            'sms_credits' => 0,
            'is_active' => true,
        ]);
        $feature = Feature::query()->create([
            'key' => 'paystack',
            'name' => 'Paystack',
            'is_active' => true,
        ]);
        $package->features()->attach($feature->id, ['enabled' => true]);
        $tenant = Tenant::withoutGlobalScopes()->create([
            'name' => 'Paystack Company',
            'email' => 'paystack@example.test',
            'package_id' => $package->id,
            'status' => 'active',
            'paystack_secret_key' => 'sk_test_local',
            'paystack_enabled' => true,
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
            'email' => 'paystack-manager@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $job = Job::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'manager_id' => $managerId,
            'plate' => 'GR1234-24',
            'total_amount' => 25,
            'payment_method' => 'paystack',
            'payment_ref' => 'PAYSTACK-REF-1',
            'payment_status' => 'pending',
            'status' => 'open',
        ]);
        $workerId = DB::table('workers')->insertGetId([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'name' => 'Gateway Worker',
            'phone' => '0244000003',
            'pin' => bcrypt('1234'),
            'type' => 'permanent',
            'default_share_pct' => 0,
            'payout_mode' => 'instant',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        JobWorker::query()->create([
            'job_id' => $job->id,
            'worker_id' => $workerId,
            'share_amount' => 10,
            'payout_mode' => 'instant',
        ]);
        $payment = Payment::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'manager_id' => $managerId,
            'job_id' => $job->id,
            'amount' => 25,
            'method' => 'paystack',
            'reference' => 'PAYSTACK-REF-1',
            'status' => 'pending',
        ]);
        $payload = json_encode([
            'event' => 'charge.success',
            'data' => ['reference' => 'PAYSTACK-REF-1', 'amount' => 2500, 'currency' => 'GHS'],
        ], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha512', $payload, 'sk_test_local');

        $response = $this->call(
            'POST',
            route('payments.paystack.webhook'),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $signature],
            $payload,
        );

        $response->assertOk();
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('paid', $job->fresh()->payment_status);
        $this->assertSame(10.0, (float) Wallet::withoutGlobalScopes()->where('worker_id', $workerId)->value('available_balance'));
    }

    public function test_worker_wallet_payout_is_settled_only_after_signed_paystack_transfer_event(): void
    {
        $package = Package::query()->create([
            'name' => 'Transfer package',
            'price' => 0,
            'billing_cycle' => 'monthly',
            'branch_addon_price' => 0,
            'worker_limit' => 10,
            'sms_credits' => 0,
            'is_active' => true,
        ]);
        $feature = Feature::query()->create([
            'key' => 'paystack',
            'name' => 'Paystack',
            'is_active' => true,
        ]);
        $package->features()->attach($feature->id, ['enabled' => true]);
        $tenant = Tenant::withoutGlobalScopes()->create([
            'name' => 'Transfer Company',
            'email' => 'transfer@example.test',
            'package_id' => $package->id,
            'status' => 'active',
            'paystack_secret_key' => 'sk_test_transfer',
            'paystack_enabled' => true,
            'paystack_transfers_enabled' => true,
        ]);
        $branchId = DB::table('branches')->insertGetId([
            'tenant_id' => $tenant->id,
            'name' => 'Transfer branch',
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
            'name' => 'Transfer Manager',
            'email' => 'transfer-manager@example.test',
            'password' => bcrypt('password'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $workerId = DB::table('workers')->insertGetId([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'name' => 'Transfer Worker',
            'phone' => '0244000002',
            'pin' => bcrypt('1234'),
            'type' => 'permanent',
            'default_share_pct' => 0,
            'payout_mode' => 'instant',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $wallet = Wallet::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'worker_id' => $workerId,
            'available_balance' => 100,
            'pending_balance' => 0,
        ]);
        WalletTransaction::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'wallet_id' => $wallet->id,
            'worker_id' => $workerId,
            'type' => 'credit',
            'amount' => 100,
            'status' => 'paid',
        ]);
        $payout = Payout::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'worker_id' => $workerId,
            'amount' => 20,
            'payout_mode' => 'instant',
            'status' => 'pending',
        ]);
        WalletTransaction::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'wallet_id' => $wallet->id,
            'worker_id' => $workerId,
            'payout_id' => $payout->id,
            'type' => 'payout',
            'amount' => 20,
            'status' => 'pending',
        ]);

        Http::fake([
            'api.paystack.co/transfer' => Http::response([
                'status' => true,
                'data' => ['transfer_code' => 'TRF_test'],
            ]),
        ]);
        $this->actingAs(User::withoutGlobalScopes()->findOrFail($managerId));
        $reference = app(PaystackService::class)->initiateTransfer($payout, 'RCP_test', $managerId);
        $this->assertSame('pending', $payout->fresh()->status);
        Http::assertSent(fn ($request) => $request['recipient'] === 'RCP_test'
            && $request['amount'] === 2000
            && $request['reference'] === $reference);

        $payload = json_encode([
            'event' => 'transfer.success',
            'data' => ['reference' => $reference, 'amount' => 2000, 'currency' => 'GHS'],
        ], JSON_THROW_ON_ERROR);
        $response = $this->call(
            'POST',
            route('payments.paystack.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $payload, 'sk_test_transfer'),
            ],
            $payload,
        );

        $response->assertOk();
        $this->assertSame('paid', $payout->fresh()->status);
        $this->assertSame('80.00', $wallet->fresh()->available_balance);
    }
}
