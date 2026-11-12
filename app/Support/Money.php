<?php

namespace App\Support;

final class Money
{
    /**
     * Bangladeshi digit grouping (12,50,000) with the taka sign, matching the
     * way fee amounts are written on receipts and notice boards locally.
     */
    public static function format(float|int $amount, string $symbol = '৳'): string
    {
        $value = (int) round($amount);
        $sign = $value < 0 ? '-' : '';
        $digits = (string) abs($value);

        if (strlen($digits) > 3) {
            $tail = substr($digits, -3);
            $head = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($digits, 0, -3));
            $digits = $head.','.$tail;
        }

        return $sign.$symbol.$digits;
    }
}
