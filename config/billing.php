<?php

return [
    'grace_days' => (int) env('CARBAY_BILLING_GRACE_DAYS', 7),
    'upgrade_email' => env('CARBAY_BILLING_EMAIL', 'billing@carbayplus.com'),
];
