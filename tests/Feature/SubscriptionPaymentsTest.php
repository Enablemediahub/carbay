<?php

namespace Tests\Feature;

use App\Filament\App\Pages\Subscription;
use App\Filament\Superadmin\Pages\LandingAppearance;
use App\Filament\Superadmin\Resources\SubscriptionInvoiceResource\Pages\ListSubscriptionInvoices;
use App\Models\Feature;
use App\Models\Package;
use App\Models\PlatformSetting;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Services\SubscriptionBillingService;
use App\Services\TenantOnboarder;
use App\Support\SubscriptionAccess;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SubscriptionPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private function company(): array
    {
        $package = Package::create(['name' => 'Plan-'.uniqid(), 'price' => 100, 'billing_cycle' => 'monthly', 'worker_limit' => 10, 'sms_credits' => 0, 'branch_addon_price' => 0, 'is_active' => true]);
        $feature = Feature::firstOrCreate(['key' => 'worker_pin_login'], ['name' => 'Worker PIN', 'is_active' => true]);
        $package->features()->sync([$feature->id => ['enabled' => true]]);
        $tenant = app(TenantOnboarder::class)->create(['name' => 'Subscription Bay', 'email' => uniqid().'@example.test', 'package_id' => $package->id, 'status' => 'suspended'], ['name' => 'Owner', 'email' => uniqid().'@example.test', 'password' => 'password']);
        $owner = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

        return [$tenant, $owner];
    }

    private function invoice(Tenant $tenant, array $overrides = []): SubscriptionInvoice
    {
        return SubscriptionInvoice::withoutGlobalScopes()->create([...[
            'tenant_id' => $tenant->id, 'package_id' => $tenant->package_id, 'invoice_number' => 'INV-'.uniqid(),
            'amount' => 100, 'currency' => 'GHS', 'status' => 'pending', 'period_starts_at' => today(),
            'period_ends_at' => today()->addMonth()->subDay(), 'due_at' => now()->subDays(8),
        ], ...$overrides]);
    }

    private function superadmin(): User
    {
        return User::withoutGlobalScopes()->create(['name' => 'Superadmin', 'email' => uniqid().'@example.test', 'role' => 'super_admin', 'status' => 'active', 'password' => 'password']);
    }

    public function test_superadmin_can_manage_all_tenants_and_confirm_cash_and_momo_receipts(): void
    {
        [$tenant, $owner] = $this->company();
        $invoice = $this->invoice($tenant);
        $admin = $this->superadmin();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('superadmin'));
        $this->get('/superadmin/subscriptions')->assertOk()->assertSee('Subscription Bay');
        $this->get('/superadmin/subscription-invoices')->assertOk();
        $billing = app(SubscriptionBillingService::class);
        Livewire::test(ListSubscriptionInvoices::class)
            ->callTableAction('markPaid', $invoice, data: ['method' => 'cash', 'receipt_reference' => 'CASH-001'])->assertHasNoTableActionErrors();
        $receipt = SubscriptionPayment::withoutGlobalScopes()->where('receipt_reference', 'CASH-001')->firstOrFail();
        $this->assertSame('paid', $receipt->status);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('active', $tenant->fresh()->status);
        $this->get('/superadmin/subscription-payments')->assertOk()->assertSee('CASH-001');
        session()->forget('password_hash_web');
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->get('/app/subscription')->assertOk()->assertSee('CASH-001');
        $this->get('/superadmin/subscriptions')->assertForbidden();
        $this->actingAs($admin);
        $next = $this->invoice($tenant, ['period_starts_at' => today()->addMonth(), 'period_ends_at' => today()->addMonths(2)]);
        $billing->recordManual($next, $admin, 'momo', 'MOMO-002');
        $this->assertDatabaseHas('subscription_payments', ['receipt_reference' => 'MOMO-002', 'method' => 'momo', 'recorded_by' => $admin->id]);
    }

    public function test_duplicate_receipts_and_already_paid_invoices_cannot_be_confirmed_twice(): void
    {
        [$tenant] = $this->company();
        $this->actingAs($admin = $this->superadmin());
        $invoice = $this->invoice($tenant);
        $billing = app(SubscriptionBillingService::class);
        $billing->recordManual($invoice, $admin, 'momo', 'UNIQUE-MOMO');
        foreach ([$invoice, $this->invoice($tenant, ['period_starts_at' => today()->addMonth(), 'period_ends_at' => today()->addMonths(2)])] as $record) {
            try {
                $billing->recordManual($record, $admin, 'momo', 'UNIQUE-MOMO');
                $this->fail('Duplicate receipt should be refused');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('receipt_reference', $exception->errors());
            }
        }
        $this->assertSame(1, SubscriptionPayment::withoutGlobalScopes()->count());
    }

    public function test_checkout_uses_only_abidale_group_key_and_verified_payment_restores_access(): void
    {
        [$tenant, $owner] = $this->company();
        $tenant->update(['paystack_secret_key' => 'sk_test_washing_bay']);
        PlatformSetting::current()->update(['billing_paystack_enabled' => true, 'billing_paystack_secret_key' => 'sk_test_abidale']);
        $invoice = $this->invoice($tenant);
        Http::fake(['*/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/subscription-test']])]);
        $this->actingAs($owner)->post(route('subscription.payment.checkout', $invoice->id))->assertRedirect('https://checkout.paystack.com/subscription-test');
        $this->post(route('subscription.payment.checkout', $invoice->id))->assertRedirect('https://checkout.paystack.com/subscription-test');
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk_test_abidale') && $request['amount'] === 10000 && $request['metadata']['payee'] === 'Abidale Group');
        $payment = SubscriptionPayment::withoutGlobalScopes()->firstOrFail();
        $data = ['status' => 'success', 'reference' => $payment->reference, 'amount' => 10000, 'currency' => 'GHS'];
        Http::fake(['*/verify/*' => Http::response(['status' => true, 'data' => $data])]);
        $this->get(route('subscription.payment.callback', ['reference' => $payment->reference]))->assertRedirect('/app/subscription');
        $this->assertSame('active', $tenant->fresh()->status);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->get(route('subscription.payment.callback', ['reference' => $payment->reference]))->assertRedirect('/app/subscription');
        $this->assertSame(1, SubscriptionPayment::withoutGlobalScopes()->where('status', 'paid')->count());
    }

    public function test_webhook_rejects_bad_signatures_amounts_and_currency_and_is_idempotent(): void
    {
        [$tenant] = $this->company();
        PlatformSetting::current()->update(['billing_paystack_secret_key' => 'sk_test_abidale']);
        $invoice = $this->invoice($tenant);
        $payment = SubscriptionPayment::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'subscription_invoice_id' => $invoice->id, 'reference' => 'GATEWAY-1', 'method' => 'paystack', 'amount' => 100, 'currency' => 'GHS']);
        $send = function (array $data, string $key = 'sk_test_abidale') {
            $payload = json_encode(['event' => 'charge.success', 'data' => $data]);

            return $this->call('POST', route('subscription.payment.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $payload, $key)], $payload);
        };
        $data = ['status' => 'success', 'reference' => 'GATEWAY-1', 'amount' => 10000, 'currency' => 'GHS'];
        $send($data, 'wrong')->assertUnauthorized();
        $send([...$data, 'amount' => 9999])->assertStatus(422);
        $send([...$data, 'currency' => 'NGN'])->assertStatus(422);
        $this->assertSame('pending', $payment->fresh()->status);
        $send($data)->assertOk();
        $send($data)->assertOk();
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('active', $tenant->fresh()->status);
    }

    public function test_admin_manager_and_worker_get_role_specific_expiry_messages(): void
    {
        [$tenant, $owner] = $this->company();
        $this->invoice($tenant);
        $this->actingAs($owner)->get('/app')->assertRedirect('/app/subscription');
        $this->get('/app/subscription')->assertOk()->assertSee('Review invoices &amp; pay', false)->assertSee('Abidale Group');
        $manager = User::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'branch_id' => $tenant->main_branch_id, 'role' => 'manager', 'name' => 'Manager', 'email' => uniqid().'@example.test', 'status' => 'active', 'password' => 'password', 'pin' => '1234', 'phone' => '0244000101']);
        session()->forget('password_hash_web');
        $this->actingAs($manager)->get('/app')->assertRedirect('/subscription-notice');
        $this->get('/subscription-notice')->assertOk()->assertSee('You are not responsible for this payment.')->assertDontSee('Review invoices');
        $this->get('/app/subscription')->assertRedirect('/subscription-notice');
        auth()->logout();
        $worker = Worker::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'branch_id' => $tenant->main_branch_id, 'name' => 'Worker', 'phone' => '0244000102', 'pin' => '5678', 'type' => 'permanent', 'status' => 'active', 'payout_mode' => 'daily']);
        $this->post('/worker/login', ['company_id' => $tenant->id, 'phone' => $worker->phone, 'pin' => '5678'])->assertRedirect('/worker');
        $this->get('/worker')->assertRedirect('/worker/subscription-notice');
        $this->get('/worker/subscription-notice')->assertOk()->assertSee('You are not responsible for this payment.')->assertDontSee('Review invoices');
        $this->post('/worker/payouts', ['method' => 'cash'])->assertRedirect('/worker/subscription-notice');
        $this->post('/worker/logout')->assertRedirect('/worker/login');
    }

    public function test_expired_paid_period_is_detected_without_waiting_for_scheduler_and_old_payment_does_not_restore_access(): void
    {
        [$tenant] = $this->company();
        $tenant->update(['status' => 'active']);
        $this->invoice($tenant, ['status' => 'paid', 'period_starts_at' => today()->subMonths(2), 'period_ends_at' => today()->subDays(10)]);
        $state = app(SubscriptionAccess::class)->state($tenant);
        $this->assertTrue($state['attention']);
        $this->assertFalse($state['allowed']);
        $tenant->update(['status' => 'suspended']);
        $old = $this->invoice($tenant, ['period_ends_at' => today()->subDays(1)]);
        $old->markPaid();
        $this->assertSame('suspended', $tenant->fresh()->status);
    }

    public function test_manager_and_other_company_owner_cannot_pay_or_check_another_company_invoice(): void
    {
        [$tenant, $owner] = $this->company();
        $invoice = $this->invoice($tenant);
        [$other, $otherOwner] = $this->company();
        $this->actingAs($otherOwner)->post(route('subscription.payment.checkout', $invoice->id))->assertForbidden();
        $owner->update(['role' => 'manager']);
        $this->actingAs($owner)->post(route('subscription.payment.checkout', $invoice->id))->assertForbidden();
        $this->actingAs($otherOwner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        auth()->logout();
        $payment = SubscriptionPayment::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'subscription_invoice_id' => $invoice->id, 'reference' => 'PRIVATE-ATTEMPT', 'method' => 'paystack', 'amount' => 100, 'currency' => 'GHS']);
        $this->actingAs($otherOwner);
        $this->expectException(ModelNotFoundException::class);
        Livewire::test(Subscription::class)->call('checkPayment', $payment->id);
    }

    public function test_billing_settings_encrypt_and_retain_secret_without_returning_it_to_the_form(): void
    {
        $this->actingAs($this->superadmin());
        Filament::setCurrentPanel(Filament::getPanel('superadmin'));
        Livewire::test(LandingAppearance::class)->fillForm([
            'billing_momo_number' => '0244000999', 'billing_momo_name' => 'Abidale Group', 'billing_momo_network' => 'MTN',
            'billing_paystack_enabled' => true, 'billing_paystack_secret_key' => 'sk_test_abidale_secret',
        ])->call('save')->assertHasNoFormErrors();
        $this->assertSame('sk_test_abidale_secret', PlatformSetting::current()->billing_paystack_secret_key);
        $this->assertNotSame('sk_test_abidale_secret', DB::table('platform_settings')->value('billing_paystack_secret_key'));
        Livewire::test(LandingAppearance::class)->assertFormSet(['billing_paystack_secret_key' => null])->call('save')->assertHasNoFormErrors();
        $this->assertSame('sk_test_abidale_secret', PlatformSetting::current()->billing_paystack_secret_key);
    }

    public function test_subscription_expiry_blocks_an_action_from_an_already_open_livewire_job_page(): void
    {
        [$tenant, $owner] = $this->company();
        $tenant->update(['status' => 'active']);
        $page = $this->actingAs($owner)->get('/app/today-jobs')->assertOk()->getContent();
        preg_match_all('/wire:snapshot="([^"]+)"/', $page, $matches);
        $snapshot = collect($matches[1])->map(fn ($value) => html_entity_decode($value, ENT_QUOTES))->first(fn ($value) => str_contains($value, 'today-jobs'));
        $this->assertNotNull($snapshot);
        $tenant->update(['status' => 'suspended']);
        $response = $this->postJson('/livewire/update', [
            'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => 'complete', 'params' => [999999]]]]],
        ], ['X-Livewire' => 'true']);
        // The middleware redirects before a job is looked up or changed.
        $response->assertRedirect('/app/subscription');
    }

    public function test_expired_subscription_has_a_grace_popup_and_extra_gateway_payment_is_flagged_for_review(): void
    {
        [$tenant, $owner] = $this->company();
        $tenant->update(['status' => 'active']);
        $invoice = $this->invoice($tenant, ['due_at' => now()->subDay()]);
        $state = app(SubscriptionAccess::class)->state($tenant);
        $this->assertTrue($state['allowed']);
        $this->assertTrue($state['attention']);
        $this->actingAs($owner)->get('/app')->assertOk()->assertSee('Continue for now');
        auth()->logout();
        $attempt = SubscriptionPayment::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'subscription_invoice_id' => $invoice->id, 'reference' => 'LATE-PAYMENT', 'method' => 'paystack', 'amount' => 100, 'currency' => 'GHS']);
        $this->actingAs($admin = $this->superadmin());
        app(SubscriptionBillingService::class)->recordManual($invoice, $admin, 'cash', 'CASH-EARLY');
        $data = ['reference' => 'LATE-PAYMENT', 'amount' => 10000, 'currency' => 'GHS', 'status' => 'success'];
        $this->assertTrue(app(SubscriptionBillingService::class)->settleGateway('LATE-PAYMENT', $data));
        $this->assertSame('review', $attempt->fresh()->status);
        $this->assertSame(1, SubscriptionPayment::withoutGlobalScopes()->where('status', 'paid')->count());
        $this->assertTrue(app(SubscriptionBillingService::class)->settleGateway('LATE-PAYMENT', $data));
        $this->assertSame('review', $attempt->fresh()->status);
    }
}
