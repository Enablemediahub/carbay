<?php

namespace App\Services;

use App\Jobs\SendSmsNotification;
use App\Models\SmsCreditBalance;
use App\Models\SmsLog;
use App\Models\Tenant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ArkeselService
{
    public function send(
        Tenant $tenant,
        string $phone,
        string $message,
        string $purpose = 'notification',
        ?int $branchId = null,
        ?int $clientId = null,
        ?string $campaignKey = null,
    ): SmsLog {
        $phone = trim($phone);
        $message = trim($message);
        if ($phone === '' || $message === '') {
            throw new \InvalidArgumentException('An SMS recipient and message are required.');
        }

        $campaignKey ??= (string) Str::uuid();
        $creditUnits = $this->creditUnits($message);

        return DB::transaction(function () use ($tenant, $phone, $message, $purpose, $branchId, $clientId, $campaignKey, $creditUnits): SmsLog {
            $existing = SmsLog::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('campaign_key', $campaignKey)
                ->where('recipient', $phone)
                ->first();
            if ($existing) {
                return $existing;
            }

            $package = $tenant->package()->first();
            $allocation = (int) ($package?->sms_credits ?? 0);
            $start = today()->startOfMonth();
            $end = today()->endOfMonth();

            $balance = SmsCreditBalance::query()->firstOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'credits_remaining' => $allocation,
                    'period_starts_at' => $start->toDateString(),
                    'period_ends_at' => $end->toDateString(),
                ],
            );
            $balance = SmsCreditBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
            if ($balance->period_starts_at->gt($start) || $balance->period_ends_at->lt(today())) {
                $balance->update([
                    'credits_remaining' => $allocation,
                    'period_starts_at' => $start->toDateString(),
                    'period_ends_at' => $end->toDateString(),
                ]);
            }

            if (! $tenant->hasFeature('sms') || $balance->credits_remaining < $creditUnits) {
                return SmsLog::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branchId,
                    'client_id' => $clientId,
                    'recipient' => $phone,
                    'purpose' => $purpose,
                    'campaign_key' => $campaignKey,
                    'message' => $message,
                    'credits_charged' => 0,
                    'credits_period_start' => $balance->period_starts_at,
                    'status' => 'failed',
                    'provider_response_json' => [
                        'error' => ! $tenant->hasFeature('sms') ? 'SMS is not enabled for this company.' : 'Monthly SMS credits exhausted.',
                    ],
                ]);
            }

            $balance->decrement('credits_remaining', $creditUnits);
            $log = SmsLog::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branchId,
                'client_id' => $clientId,
                'recipient' => $phone,
                'purpose' => $purpose,
                'campaign_key' => $campaignKey,
                'message' => $message,
                'credits_charged' => $creditUnits,
                'credits_period_start' => $balance->period_starts_at,
                'status' => 'queued',
            ]);

            SendSmsNotification::dispatch($log->id)->afterCommit();

            return $log;
        });
    }

    public function deliver(int $smsLogId): void
    {
        $log = SmsLog::withoutGlobalScopes()->findOrFail($smsLogId);
        if ($log->status !== 'queued') {
            return;
        }

        $apiKey = config('services.arkesel.api_key');
        $sender = config('services.arkesel.sender_id');
        if (! $apiKey || ! $sender) {
            $this->markFailed($log, ['error' => 'Arkesel credentials or sender ID are not configured.']);

            return;
        }

        try {
            $response = Http::withHeaders(['api-key' => $apiKey])
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->post(config('services.arkesel.endpoint'), [
                    'sender' => $sender,
                    'message' => $log->message,
                    'recipients' => [$log->recipient],
                ]);
        } catch (ConnectionException $exception) {
            report($exception);
            $this->markFailed($log, ['error' => 'The SMS provider could not be reached.']);

            return;
        }

        $body = $response->json();
        if (! $response->successful() || (is_array($body) && ($body['status'] ?? null) === 'error')) {
            $this->markFailed($log, [
                'http_status' => $response->status(),
                'response' => $body ?? $response->body(),
            ]);

            return;
        }

        $log->update([
            'status' => 'sent',
            'provider_response_json' => $body ?? ['http_status' => $response->status()],
            'sent_at' => now(),
        ]);
    }

    private function markFailed(SmsLog $log, array $response): void
    {
        DB::transaction(function () use ($log, $response): void {
            $locked = SmsLog::withoutGlobalScopes()->whereKey($log->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'queued') {
                return;
            }

            $locked->update([
                'status' => 'failed',
                'credit_refunded' => true,
                'provider_response_json' => $response,
            ]);
            SmsCreditBalance::query()
                ->where('tenant_id', $locked->tenant_id)
                ->whereDate('period_starts_at', $locked->credits_period_start)
                ->increment('credits_remaining', $locked->credits_charged);
        });
    }

    private function creditUnits(string $message): int
    {
        $isAscii = preg_match('/^[\x00-\x7F]*$/D', $message) === 1;
        $characters = mb_strlen($message);
        $singleLimit = $isAscii ? 160 : 70;
        $multipartLimit = $isAscii ? 153 : 67;

        return $characters <= $singleLimit ? 1 : (int) ceil($characters / $multipartLimit);
    }
}
