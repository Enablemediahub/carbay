<?php

namespace App\Services;

use App\Models\Job;
use App\Models\JobWorker;
use App\Models\Payout;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Worker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function credit(Worker $worker, Job $job, float $amount, string $payoutMode, ?JobWorker $jobWorker = null): WalletTransaction
    {
        $this->validateAmountAndMode($amount, $payoutMode);

        return DB::transaction(function () use ($worker, $job, $amount, $payoutMode, $jobWorker): WalletTransaction {
            $this->assertWorkerAndJobMatch($worker, $job);
            $wallet = Wallet::query()->firstOrCreate(
                ['worker_id' => $worker->id],
                [
                    'tenant_id' => $worker->tenant_id,
                    'branch_id' => $worker->branch_id,
                    'available_balance' => 0,
                    'pending_balance' => 0,
                ],
            );
            $wallet = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            if ($jobWorker) {
                $existing = WalletTransaction::query()->where('job_worker_id', $jobWorker->id)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $availableAt = $this->availableAt($payoutMode);
            $transaction = $wallet->transactions()->create([
                'tenant_id' => $worker->tenant_id,
                'branch_id' => $worker->branch_id,
                'worker_id' => $worker->id,
                'job_id' => $job->id,
                'job_worker_id' => $jobWorker?->id,
                'type' => 'credit',
                'amount' => round($amount, 2),
                'status' => $payoutMode === 'instant' ? 'paid' : 'pending',
                'payout_mode' => $payoutMode,
                'available_at' => $availableAt,
            ]);

            if ($payoutMode !== 'instant' && $jobWorker) {
                Payout::query()->create([
                    'tenant_id' => $worker->tenant_id,
                    'branch_id' => $job->branch_id,
                    'worker_id' => $worker->id,
                    'job_worker_id' => $jobWorker->id,
                    'amount' => round($amount, 2),
                    'payout_mode' => $payoutMode,
                    'status' => 'queued',
                    'available_at' => $availableAt,
                ]);
            }

            $this->recalculateBalance($wallet);

            return $transaction;
        });
    }

    public function debit(Worker $worker, float $amount, string $reference): WalletTransaction
    {
        $this->validateAmount($amount);

        return DB::transaction(function () use ($worker, $amount, $reference): WalletTransaction {
            $wallet = Wallet::query()->where('worker_id', $worker->id)->lockForUpdate()->firstOrFail();
            $wallet = $this->recalculateBalance($wallet);

            if ((float) $wallet->available_balance < $amount) {
                throw ValidationException::withMessages(['amount' => 'The worker wallet does not have enough available balance.']);
            }

            $transaction = $wallet->transactions()->create([
                'tenant_id' => $worker->tenant_id,
                'branch_id' => $worker->branch_id,
                'worker_id' => $worker->id,
                'type' => 'debit',
                'amount' => round($amount, 2),
                'status' => 'paid',
                'reference' => $reference,
            ]);

            $this->recalculateBalance($wallet);

            return $transaction;
        });
    }

    public function cancelJob(Job $job): Job
    {
        return DB::transaction(function () use ($job): Job {
            $job = Job::query()->whereKey($job->id)->lockForUpdate()->firstOrFail();
            if ($job->status !== 'open') {
                throw ValidationException::withMessages(['job' => 'Only open jobs can be cancelled.']);
            }

            $assignments = JobWorker::query()->where('job_id', $job->id)->get();
            foreach ($assignments as $assignment) {
                $credit = WalletTransaction::query()
                    ->where('job_worker_id', $assignment->id)
                    ->where('type', 'credit')
                    ->lockForUpdate()
                    ->first();
                if (! $credit) {
                    continue;
                }

                $wallet = Wallet::query()->where('worker_id', $assignment->worker_id)->lockForUpdate()->firstOrFail();
                if ($credit->status === 'pending') {
                    $credit->update(['status' => 'failed']);
                    Payout::query()
                        ->where('job_worker_id', $assignment->id)
                        ->where('status', 'queued')
                        ->update(['status' => 'rejected']);
                } elseif ($credit->status === 'paid') {
                    $wallet = $this->recalculateBalance($wallet);
                    if ((float) $wallet->available_balance < (float) $credit->amount) {
                        throw ValidationException::withMessages([
                            'job' => 'A worker has already used or requested this job share; resolve the wallet balance before cancelling.',
                        ]);
                    }
                    $wallet->transactions()->create([
                        'tenant_id' => $job->tenant_id,
                        'branch_id' => $job->branch_id,
                        'worker_id' => $assignment->worker_id,
                        'job_id' => $job->id,
                        'type' => 'debit',
                        'amount' => $credit->amount,
                        'status' => 'paid',
                        'reference' => 'JOB-CANCEL-'.$job->id,
                    ]);
                }
                $this->recalculateBalance($wallet);
            }

            $job->update(['status' => 'cancelled']);

            return $job;
        });
    }

    public function payout(Worker $worker, float $amount, string $method, string $reference = ''): Payout
    {
        $this->validateAmount($amount);
        if (! in_array($method, ['cash', 'momo'], true)) {
            throw ValidationException::withMessages(['method' => 'Choose cash or Mobile Money for this payout.']);
        }

        return DB::transaction(function () use ($worker, $amount, $method, $reference): Payout {
            $wallet = Wallet::query()->where('worker_id', $worker->id)->lockForUpdate()->firstOrFail();
            $wallet = $this->recalculateBalance($wallet);

            if ((float) $wallet->available_balance < $amount) {
                throw ValidationException::withMessages(['amount' => 'The requested payout exceeds your available balance.']);
            }

            $payout = Payout::query()->create([
                'tenant_id' => $worker->tenant_id,
                'branch_id' => $worker->branch_id,
                'worker_id' => $worker->id,
                'requested_by' => null,
                'amount' => round($amount, 2),
                'payout_mode' => 'instant',
                'method' => $method,
                'reference' => $reference !== '' ? $reference : null,
                'status' => 'pending',
            ]);

            $wallet->transactions()->create([
                'tenant_id' => $worker->tenant_id,
                'branch_id' => $worker->branch_id,
                'worker_id' => $worker->id,
                'payout_id' => $payout->id,
                'type' => 'payout',
                'amount' => round($amount, 2),
                'status' => 'pending',
                'reference' => $reference !== '' ? $reference : null,
            ]);

            $this->recalculateBalance($wallet);

            return $payout;
        });
    }

    public function settlePayout(Payout $payout, string $method, string $reference, int $managerId): Payout
    {
        if (! in_array($method, ['cash', 'momo'], true)) {
            throw ValidationException::withMessages(['method' => 'Choose cash or Mobile Money.']);
        }
        if ($method === 'momo' && trim($reference) === '') {
            throw ValidationException::withMessages(['reference' => 'Enter the Mobile Money transfer reference.']);
        }

        return DB::transaction(function () use ($payout, $method, $reference, $managerId): Payout {
            $payout = Payout::query()->whereKey($payout->id)->lockForUpdate()->firstOrFail();
            if (! in_array($payout->status, ['queued', 'pending'], true)) {
                throw ValidationException::withMessages(['payout' => 'This payout has already been processed.']);
            }
            if ($payout->status === 'queued' && $payout->available_at?->isFuture()) {
                throw ValidationException::withMessages(['payout' => 'This scheduled payout is not due yet.']);
            }

            $wallet = Wallet::query()->where('worker_id', $payout->worker_id)->lockForUpdate()->firstOrFail();

            if ($payout->job_worker_id) {
                $credit = WalletTransaction::query()
                    ->where('job_worker_id', $payout->job_worker_id)
                    ->where('type', 'credit')
                    ->lockForUpdate()
                    ->firstOrFail();
                $credit->update(['status' => 'paid', 'available_at' => now()]);
            }

            $payoutTransaction = WalletTransaction::query()
                ->where('payout_id', $payout->id)
                ->where('type', 'payout')
                ->lockForUpdate()
                ->first();

            if ($payoutTransaction) {
                $payoutTransaction->update([
                    'status' => 'paid',
                    'reference' => $reference !== '' ? $reference : null,
                ]);
            } else {
                $wallet->transactions()->create([
                    'tenant_id' => $payout->tenant_id,
                    'branch_id' => $payout->branch_id,
                    'worker_id' => $payout->worker_id,
                    'payout_id' => $payout->id,
                    'type' => 'payout',
                    'amount' => $payout->amount,
                    'status' => 'paid',
                    'reference' => $reference !== '' ? $reference : null,
                ]);
            }

            $payout->update([
                'method' => $method,
                'reference' => $reference !== '' ? $reference : null,
                'status' => 'paid',
                'approved_by' => $managerId,
                'paid_at' => now(),
            ]);

            $this->recalculateBalance($wallet);

            return $payout->refresh();
        });
    }

    public function recalculateBalance(Wallet $wallet): Wallet
    {
        $transactions = $wallet->transactions();
        $available = (clone $transactions)->where('status', 'paid')->where('type', 'credit')->sum('amount')
            - (clone $transactions)->where('status', 'paid')->where('type', 'debit')->sum('amount')
            - (clone $transactions)->where('type', 'payout')->whereIn('status', ['pending', 'paid'])->sum('amount');
        $pending = (clone $transactions)->where('status', 'pending')->where('type', 'credit')->sum('amount');

        $wallet->forceFill([
            'available_balance' => round((float) $available, 2),
            'pending_balance' => round((float) $pending, 2),
        ])->save();

        return $wallet->refresh();
    }

    private function assertWorkerAndJobMatch(Worker $worker, Job $job): void
    {
        if ($worker->tenant_id !== $job->tenant_id || $worker->branch_id !== $job->branch_id) {
            throw ValidationException::withMessages(['worker' => 'The worker and job must belong to the same company and branch.']);
        }
    }

    private function availableAt(string $mode): Carbon
    {
        return match ($mode) {
            'instant' => now(),
            'daily' => now()->endOfDay(),
            'weekly' => now()->endOfWeek(),
            'monthly' => now()->endOfMonth(),
            default => throw ValidationException::withMessages(['payout_mode' => 'Choose a valid payout schedule.']),
        };
    }

    private function validateAmountAndMode(float $amount, string $mode): void
    {
        $this->validateAmount($amount);
        if (! in_array($mode, ['instant', 'daily', 'weekly', 'monthly'], true)) {
            throw ValidationException::withMessages(['payout_mode' => 'Choose a valid payout schedule.']);
        }
    }

    private function validateAmount(float $amount): void
    {
        if (! is_finite($amount) || $amount <= 0 || round($amount, 2) !== $amount) {
            throw ValidationException::withMessages(['amount' => 'The amount must be a positive value with no more than two decimal places.']);
        }
    }
}
