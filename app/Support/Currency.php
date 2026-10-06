<?php

namespace App\Support;

class Currency
{
    public static function format(int|float|string|null $amount): string
    {
        return 'GH₵ '.number_format((float) $amount, 2);
    }
}
