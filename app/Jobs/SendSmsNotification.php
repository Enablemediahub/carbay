<?php

namespace App\Jobs;

use App\Services\ArkeselService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSmsNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $smsLogId) {}

    public function handle(ArkeselService $arkesel): void
    {
        $arkesel->deliver($this->smsLogId);
    }
}
