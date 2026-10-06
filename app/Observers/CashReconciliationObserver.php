<?php

namespace App\Observers;

use App\Models\CashReconciliation;
use App\Services\JobFraudInspector;

class CashReconciliationObserver
{
    public function saved(CashReconciliation $reconciliation): void
    {
        app(JobFraudInspector::class)->inspectCashReconciliation($reconciliation);
    }
}
