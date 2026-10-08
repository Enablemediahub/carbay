<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionBillingService
{
    public function recordManual(SubscriptionInvoice $invoice, User $actor, string $method, ?string $receipt): SubscriptionPayment
    {
        abort_unless($actor->isSuperAdmin() && $actor->status === 'active', 403);
        if (! in_array($method, ['cash', 'momo'], true) || trim($receipt ?? '') === '') {
            throw ValidationException::withMessages(['receipt_reference' => 'Enter the cash receipt or MoMo transaction reference.']);
        }

        return DB::transaction(function () use ($invoice, $actor, $method, $receipt): SubscriptionPayment {
            $invoice = SubscriptionInvoice::withoutGlobalScopes()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status !== 'pending') {
                throw ValidationException::withMessages(['receipt_reference' => 'This invoice has already been settled.']);
            }
            if (SubscriptionPayment::withoutGlobalScopes()->where('method', $method)->where('receipt_reference', trim($receipt))->where('status', 'paid')->exists()) {
                throw ValidationException::withMessages(['receipt_reference' => 'This receipt has already been recorded.']);
            }
            $payment = SubscriptionPayment::withoutGlobalScopes()->create([
                'tenant_id' => $invoice->tenant_id, 'subscription_invoice_id' => $invoice->id,
                'reference' => 'SUB-'.Str::uuid(), 'receipt_reference' => trim($receipt), 'method' => $method,
                'amount' => $invoice->amount, 'currency' => $invoice->currency, 'status' => 'paid', 'paid_at' => now(), 'recorded_by' => $actor->id,
            ]);
            $invoice->markPaid();

            return $payment;
        });
    }

    public function checkout(SubscriptionInvoice $invoice, User $actor): string
    {
        abort_unless($actor->role === 'ceo' && $actor->status === 'active' && $actor->tenant_id === $invoice->tenant_id, 403);
        $settings = PlatformSetting::current();
        if (! $settings->billing_paystack_enabled || ! $settings->billing_paystack_secret_key) {
            throw ValidationException::withMessages(['payment' => 'Abidale Group online payments are not configured. Please use the displayed billing contact or MoMo details.']);
        }
        $payment = DB::transaction(function () use ($invoice, $actor): SubscriptionPayment {
            $invoice = SubscriptionInvoice::withoutGlobalScopes()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->status !== 'pending' || (float) $invoice->amount <= 0 || $invoice->currency !== 'GHS') {
                throw ValidationException::withMessages(['payment' => 'This invoice is not available for online payment.']);
            }
            // Resolve an outstanding checkout before opening another payment attempt.
            $existing = SubscriptionPayment::withoutGlobalScopes()->where('subscription_invoice_id', $invoice->id)->where('method', 'paystack')->whereIn('status', ['pending', 'review'])->first();
            if ($existing?->status === 'pending' && $existing->checkout_url) {
                return $existing;
            }
            if ($existing) {
                throw ValidationException::withMessages(['payment' => 'A payment attempt is already open. Use Check payment below before trying again.']);
            }

            return SubscriptionPayment::withoutGlobalScopes()->create([
                'tenant_id' => $invoice->tenant_id, 'subscription_invoice_id' => $invoice->id,
                'reference' => 'CARBAY-SUB-'.Str::uuid(), 'method' => 'paystack', 'amount' => $invoice->amount,
                'currency' => $invoice->currency, 'recorded_by' => $actor->id,
            ]);
        });
        if ($payment->checkout_url) {
            return $payment->checkout_url;
        }
        try {
            $response = Http::withToken($settings->billing_paystack_secret_key)->timeout(20)->post('https://api.paystack.co/transaction/initialize', [
                'email' => $actor->email, 'amount' => (int) round((float) $payment->amount * 100), 'currency' => $payment->currency,
                'reference' => $payment->reference, 'callback_url' => route('subscription.payment.callback'), 'channels' => ['card', 'mobile_money'],
                'metadata' => ['subscription_invoice_id' => $invoice->id, 'tenant_id' => $invoice->tenant_id, 'payee' => 'Abidale Group'],
            ]);
            $url = $response->json('data.authorization_url');
            if (! $response->successful() || $response->json('status') !== true || ! is_string($url) || ! preg_match('~^https://checkout\.paystack\.com/~', $url)) {
                $payment->update(['status' => 'failed']);
                throw ValidationException::withMessages(['payment' => 'Payment checkout could not be opened. Please try again or contact billing.']);
            }

            $payment->update(['checkout_url' => $url]);

            return $url;
        } catch (ConnectionException $exception) {
            // A timeout may occur after Paystack created the checkout: verify before retrying.
            throw ValidationException::withMessages(['payment' => 'Paystack did not respond in time. Use Check payment before starting another attempt.']);
        }
    }

    public function verify(SubscriptionPayment $payment): bool
    {
        $key = PlatformSetting::current()->billing_paystack_secret_key;
        abort_unless($key && $payment->method === 'paystack', 422);
        try {
            $response = Http::withToken($key)->timeout(20)->get('https://api.paystack.co/transaction/verify/'.rawurlencode($payment->reference));
        } catch (ConnectionException $exception) {
            throw ValidationException::withMessages(['payment' => 'Could not check Paystack right now. Please try again shortly.']);
        }
        if (! $response->successful() || $response->json('status') !== true) {
            throw ValidationException::withMessages(['payment' => 'Paystack has not confirmed this transaction. Contact billing if money was deducted.']);
        }
        $data = $response->json('data', []);
        if (($data['status'] ?? '') === 'success') {
            return $this->settleGateway($payment->reference, $data);
        }
        if (in_array($data['status'] ?? '', ['failed', 'abandoned', 'reversed'], true)) {
            SubscriptionPayment::withoutGlobalScopes()->whereKey($payment->id)->where('status', 'pending')->update(['status' => 'failed']);
        }

        return false;
    }

    public function settleGateway(string $reference, array $data): bool
    {
        return DB::transaction(function () use ($reference, $data): bool {
            // Same lock ordering as manual receipts: invoice first, then attempt.
            $attempt = SubscriptionPayment::withoutGlobalScopes()->where('reference', $reference)->where('method', 'paystack')->first();
            if (! $attempt) {
                return false;
            }
            $invoice = SubscriptionInvoice::withoutGlobalScopes()->lockForUpdate()->findOrFail($attempt->subscription_invoice_id);
            $payment = SubscriptionPayment::withoutGlobalScopes()->lockForUpdate()->findOrFail($attempt->id);
            if (($data['reference'] ?? '') !== $reference || ($data['status'] ?? '') !== 'success'
                || ($data['currency'] ?? '') !== $payment->currency || ! is_numeric($data['amount'] ?? null)
                || (float) $data['amount'] !== (float) round((float) $payment->amount * 100)
                || (float) $payment->amount !== (float) $invoice->amount || $payment->currency !== $invoice->currency) {
                return false;
            }
            if (in_array($payment->status, ['paid', 'review'], true)) {
                return true;
            }
            if ($invoice->status === 'paid') {
                $payment->update(['status' => 'review', 'paid_at' => now()]);

                return true; // Funds arrived after another receipt; flag for reconciliation, never extend twice.
            }
            if ($invoice->status !== 'pending') {
                return false;
            }
            $payment->update(['status' => 'paid', 'paid_at' => now()]);
            $invoice->markPaid();

            return true;
        });
    }
}
