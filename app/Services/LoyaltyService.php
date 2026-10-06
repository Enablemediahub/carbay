<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientBooking;
use App\Models\Job;
use App\Models\LoyaltyTransaction;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoyaltyService
{
    public function earnForJob(Job $job): int
    {
        if ($job->status !== 'completed' || $job->payment_status !== 'paid' || ! $job->client_id) {
            return 0;
        }

        $tenant = Tenant::withoutGlobalScopes()->findOrFail($job->tenant_id);
        if (! $tenant->hasFeature('loyalty')) {
            return 0;
        }

        return DB::transaction(function () use ($job, $tenant): int {
            $client = Client::withoutGlobalScopes()
                ->where('tenant_id', $job->tenant_id)
                ->whereKey($job->client_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (LoyaltyTransaction::withoutGlobalScopes()->where('job_id', $job->id)->where('type', 'earn')->exists()) {
                return 0;
            }

            $eligibleSpend = max(0, (float) $job->total_amount - (float) $job->discount_amount);
            $points = (int) floor($eligibleSpend / 10) * max(0, (int) $tenant->loyalty_points_per_10);
            if ($points === 0) {
                return 0;
            }

            LoyaltyTransaction::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'client_id' => $client->id,
                'job_id' => $job->id,
                'type' => 'earn',
                'points' => $points,
                'description' => 'Points earned for job '.$job->plate,
            ]);
            $client->increment('loyalty_points', $points);

            return $points;
        });
    }

    public function redeemForBooking(Client $client, ClientBooking $booking, int $points): float
    {
        if ($points < 1) {
            return 0;
        }
        if ($client->tenant_id !== $booking->tenant_id || $client->id !== $booking->client_id) {
            throw ValidationException::withMessages(['loyalty_points' => 'This booking does not belong to your client account.']);
        }

        return DB::transaction(function () use ($client, $booking, $points): float {
            $tenant = Tenant::withoutGlobalScopes()->findOrFail($client->tenant_id);
            if (! $tenant->hasFeature('loyalty')) {
                throw ValidationException::withMessages(['loyalty_points' => 'Loyalty rewards are not enabled for this company.']);
            }
            $required = max(1, (int) $tenant->loyalty_reward_points);
            if ($points !== $required) {
                throw ValidationException::withMessages(['loyalty_points' => 'Redeem exactly '.$required.' points for this reward.']);
            }

            $lockedClient = Client::withoutGlobalScopes()->whereKey($client->id)->lockForUpdate()->firstOrFail();
            if ($lockedClient->loyalty_points < $required) {
                throw ValidationException::withMessages(['loyalty_points' => 'You do not have enough points for this reward.']);
            }
            if (LoyaltyTransaction::withoutGlobalScopes()->where('booking_id', $booking->id)->where('type', 'redeem')->exists()) {
                throw ValidationException::withMessages(['loyalty_points' => 'A reward has already been applied to this booking.']);
            }

            $discount = min((float) $booking->estimated_amount, (float) $tenant->loyalty_reward_value);
            $lockedClient->decrement('loyalty_points', $required);
            $booking->update([
                'discount_amount' => $discount,
                'loyalty_points_redeemed' => $required,
            ]);
            LoyaltyTransaction::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'client_id' => $lockedClient->id,
                'booking_id' => $booking->id,
                'type' => 'redeem',
                'points' => $required,
                'description' => 'Reward redeemed for booking #'.$booking->id,
            ]);

            return $discount;
        });
    }
}
