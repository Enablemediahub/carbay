<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use App\Services\SubscriptionBillingService;
use Illuminate\Http\Request;

class SubscriptionPaymentController extends Controller
{
    public function checkout(Request $request, int $invoice, SubscriptionBillingService $billing)
    {
        $record = SubscriptionInvoice::withoutGlobalScopes()->findOrFail($invoice);

        return redirect()->away($billing->checkout($record, $request->user()));
    }

    public function callback(Request $request, SubscriptionBillingService $billing)
    {
        abort_unless($request->user()?->role === 'ceo' && $request->user()->status === 'active', 403);
        $reference = $request->validate(['reference' => ['required', 'string', 'max:255']])['reference'];
        $payment = SubscriptionPayment::withoutGlobalScopes()->where('reference', $reference)->where('tenant_id', $request->user()->tenant_id)->firstOrFail();
        $paid = $billing->verify($payment);

        return redirect('/app/subscription')->with('billing_status', $paid ? 'Payment received. Your subscription has been updated.' : 'Payment is not yet confirmed. Please check again after completing payment.');
    }

    public function webhook(Request $request, SubscriptionBillingService $billing)
    {
        $key = PlatformSetting::current()->billing_paystack_secret_key;
        $signature = $request->header('x-paystack-signature');
        abort_unless($key && is_string($signature) && hash_equals(hash_hmac('sha512', $request->getContent(), $key), $signature), 401);
        if ($request->input('event') !== 'charge.success') {
            return response('OK');
        }
        $data = $request->input('data', []);
        if (is_array($data) && ! SubscriptionPayment::withoutGlobalScopes()->where('reference', (string) ($data['reference'] ?? ''))->where('method', 'paystack')->exists()) {
            return response('OK'); // Abidale Group may receive other payments on this account.
        }
        abort_unless(is_array($data) && $billing->settleGateway((string) ($data['reference'] ?? ''), $data), 422);

        return response('OK');
    }
}
