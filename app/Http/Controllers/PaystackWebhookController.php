<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\JobWorker;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Tenant;
use App\Models\Worker;
use App\Services\PaystackService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, PaystackService $paystack, WalletService $wallets): Response
    {
        $payload = $request->getContent();
        $event = $request->json()->all();
        $reference = data_get($event, 'data.reference');
        $payment = is_string($reference)
            ? Payment::withoutGlobalScopes()->where('reference', $reference)->first()
            : null;
        $payout = ! $payment && is_string($reference)
            ? Payout::withoutGlobalScopes()->where('reference', $reference)->first()
            : null;

        if ((! $payment || $payment->method !== 'paystack') && ! $payout) {
            return response('Payment or payout not found.', 404);
        }
        $tenant = Tenant::withoutGlobalScopes()->find($payment?->tenant_id ?? $payout?->tenant_id);
        if (
            ! $tenant
            || ! $tenant->paystack_enabled
            || ! $tenant->hasFeature('paystack')
            || ! $paystack->hasValidSignature($payload, $request->header('x-paystack-signature'), $tenant)
        ) {
            return response('Invalid signature.', 400);
        }

        $eventName = data_get($event, 'event');
        $gatewayAmount = data_get($event, 'data.amount');
        if (data_get($event, 'data.currency') !== 'GHS') {
            return response('Invalid payment currency.', 422);
        }
        if ($payout) {
            if (
                ! $tenant->paystack_transfers_enabled
                || ! in_array($eventName, ['transfer.success', 'transfer.failed'], true)
                || ! is_numeric($gatewayAmount)
                || (int) $gatewayAmount !== (int) round((float) $payout->amount * 100)
            ) {
                return response('Invalid payout event.', 422);
            }

            DB::transaction(function () use ($payout, $event, $eventName, $wallets): void {
                $lockedPayout = Payout::withoutGlobalScopes()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
                if (! in_array($lockedPayout->status, ['pending', 'queued'], true)) {
                    return;
                }
                if ($eventName === 'transfer.failed') {
                    $lockedPayout->update([
                        'reference' => null,
                        'approved_by' => null,
                        'gateway_response_json' => $event,
                    ]);

                    return;
                }

                app()->instance('current_tenant_id', $lockedPayout->tenant_id);
                try {
                    $lockedPayout->update(['gateway_response_json' => $event]);
                    $wallets->settlePayout(
                        $lockedPayout,
                        'momo',
                        (string) data_get($event, 'data.reference'),
                        (int) ($lockedPayout->approved_by ?? 0),
                    );
                } finally {
                    app()->forgetInstance('current_tenant_id');
                }
            });

            return response('OK', 200);
        }

        if (! $payment || $payment->method !== 'paystack') {
            return response('Payment not found.', 404);
        }
        if (
            ! in_array($eventName, ['charge.success', 'charge.failed'], true)
            || ! is_numeric($gatewayAmount)
            || (int) $gatewayAmount !== (int) round((float) $payment->amount * 100)
        ) {
            return response('Invalid payment event.', 422);
        }

        DB::transaction(function () use ($payment, $event, $eventName, $wallets): void {
            $lockedPayment = Payment::withoutGlobalScopes()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($lockedPayment->status !== 'pending') {
                return;
            }

            $status = $eventName === 'charge.success' ? 'paid' : 'failed';
            $lockedPayment->update([
                'status' => $status,
                'gateway_response_json' => $event,
            ]);
            $job = Job::withoutGlobalScopes()->whereKey($lockedPayment->job_id)->lockForUpdate()->firstOrFail();
            $job->update(['payment_status' => $status]);
            if ($status === 'paid' && $job->status !== 'cancelled') {
                app()->instance('current_tenant_id', $job->tenant_id);
                try {
                    $assignments = JobWorker::query()->where('job_id', $job->id)->get();
                    foreach ($assignments as $assignment) {
                        $worker = Worker::withoutGlobalScopes()
                            ->where('tenant_id', $job->tenant_id)
                            ->where('branch_id', $job->branch_id)
                            ->findOrFail($assignment->worker_id);
                        if ((float) $assignment->share_amount > 0) {
                            $wallets->credit(
                                $worker,
                                $job,
                                (float) $assignment->share_amount,
                                $assignment->payout_mode,
                                $assignment,
                            );
                        }
                    }
                } finally {
                    app()->forgetInstance('current_tenant_id');
                }
            }
        });

        return response('OK', 200);
    }
}
