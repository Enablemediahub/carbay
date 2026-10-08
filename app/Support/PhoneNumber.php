<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class PhoneNumber
{
    public static function normalize(?string $phone): ?string
    {
        $phone = preg_replace('/[\s().-]+/', '', trim((string) $phone));
        if ($phone === '') {
            return null;
        }
        if (str_starts_with($phone, '00')) {
            $phone = '+'.substr($phone, 2);
        }
        $digits = ltrim($phone, '+');
        if (preg_match('/^233[0-9]{9}$/', $digits)) {
            return '0'.substr($digits, 3);
        }

        return $phone;
    }

    public static function variants(string $phone): array
    {
        $phone = self::normalize($phone) ?? '';
        if (preg_match('/^0[0-9]{9}$/', $phone)) {
            $international = '233'.substr($phone, 1);

            return [$phone, $international, '+'.$international, '00'.$international];
        }

        return [$phone, str_starts_with($phone, '+') ? '00'.substr($phone, 1) : $phone];
    }

    public static function match(Builder $query, string $phone): Builder
    {
        $variants = self::variants($phone);
        $placeholders = implode(',', array_fill(0, count($variants), '?'));

        return $query->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(phone), ' ', ''), '-', ''), '(', ''), ')', ''), '.', '') IN ($placeholders)", $variants);
    }
}
