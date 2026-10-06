<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\Tenant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaystackService
{
    public function initialize(float $amount, string $email, string $reference): string
    {
        $tenantId = auth()->user()?->tenant_id;
        $tenant = $tenantId ? Tenant::withoutGlobalScopes()->find($tenantId) : null;

        if (! $tenant?->paystack_secret_key || ! $tenant->paystack_enabled || ! $tenant->hasFeature('paystack')) {
            throw ValidationException::withMessages(['payment_method' => 'Paystack is not configured for this company.']);
        }

        try {
            $response = Http::withToken($tenant->paystack_secret_key)
                ->acceptJson()
                ->post(config('services.paystack.endpoint', 'https://api.paystack.co/transaction/initialize'), [
                    'email' => $email,
                    'amount' => (int) round($amount * 100),
                    'currency' => 'GHS',
                    'reference' => $reference,
                ])
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $exception) {
            report($exception);
            throw ValidationException::withMessages(['payment_method' => 'Paystack could not initialize this payment. Please try again.']);
        }

        $url = data_get($response, 'data.authorization_url');
        if (! data_get($response, 'status') || ! is_string($url) || $url === '') {
            throw ValidationException::withMessages(['payment_method' => 'Paystack returned an invalid checkout response.']);
        }

        return $url;
    }

    public function hasValidSignature(string $payload, ?string $signature, Tenant $tenant): bool
    {
        return $tenant->paystack_secret_key !== null
            && is_string($signature)
            && hash_equals(hash_hmac('sha512', $payload, $tenant->paystack_secret_key), $signature);
    }

    public function initiateTransfer(Payout $payout, string $recipientCode, int $managerId): string
    {
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($payout->tenant_id);
        if (! $tenant->paystack_enabled || ! $tenant->paystack_transfers_enabled || ! $tenant->hasFeature('paystack') || ! $tenant->paystack_secret_key) {
            throw ValidationException::withMessages(['method' => 'Paystack transfers are not enabled for this company.']);
        }
        if (trim($recipientCode) === '') {
            throw ValidationException::withMessages(['reference' => 'Enter the Paystack transfer recipient code.']);
        }
        $reference = 'CB-PAYOUT-'.$payout->id.'-'.strtoupper(Str::random(10));
        $payout = DB::transaction(function () use ($payout, $reference, $managerId): Payout {
            $lockedPayout = Payout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            if (! in_array($lockedPayout->status, ['pending', 'queued'], true)) {
                throw ValidationException::withMessages(['payout' => 'This payout has already been processed.']);
            }
            if ($lockedPayout->status === 'queued' && $lockedPayout->available_at?->isFuture()) {
                throw ValidationException::withMessages(['payout' => 'This scheduled payout is not due yet.']);
            }
            if (
                $lockedPayout->reference
                && (
                    data_get($lockedPayout->gateway_response_json, 'status') === 'initializing'
                    || data_get($lockedPayout->gateway_response_json, 'data.transfer_code')
                )
            ) {
                throw ValidationException::withMessages(['payout' => 'A Paystack transfer is already awaiting confirmation.']);
            }

            $lockedPayout->update([
                'method' => 'momo',
                'reference' => $reference,
                'approved_by' => $managerId,
                'gateway_response_json' => ['status' => 'initializing'],
            ]);

            return $lockedPayout;
        });

        try {
            $response = Http::withToken($tenant->paystack_secret_key)
                ->acceptJson()
                ->post(config('services.paystack.transfer_endpoint'), [
                    'source' => 'balance',
                    'amount' => (int) round((float) $payout->amount * 100),
                    'recipient' => trim($recipientCode),
                    'reason' => 'Carbay+ worker payout '.$payout->id,
                    'reference' => $reference,
                ])
                ->throw()
                ->json();
        } catch (ConnectionException $exception) {
            report($exception);
            throw ValidationException::withMessages(['method' => 'Paystack did not confirm the transfer response. Check the Paystack dashboard before retrying this payout.']);
        } catch (RequestException $exception) {
            report($exception);
            if ($exception->response->status() < 500) {
                $payout->update([
                    'reference' => null,
                    'approved_by' => null,
                    'gateway_response_json' => ['error' => $exception->response->json()],
                ]);
            }
            throw ValidationException::withMessages(['method' => 'Paystack could not confirm the worker transfer. Review the payout state before retrying.']);
        }

        if (! data_get($response, 'status') || ! is_string(data_get($response, 'data.transfer_code'))) {
            throw ValidationException::withMessages(['method' => 'Paystack returned an incomplete transfer response. Check its dashboard before retrying.']);
        }

        $payout->update(['gateway_response_json' => $response]);

        return $reference;
    }
}
