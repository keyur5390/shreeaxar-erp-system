<?php

namespace App\Support;

class AmountInWords
{
    private const ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
        'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const TENS = [
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
    ];

    public static function format(float $amount, string $currencyCode = 'RWF', int $decimalPlaces = 0): string
    {
        $isNegative = $amount < 0;
        $absolute = abs($amount);

        $multiplier = 10 ** $decimalPlaces;
        $whole = (int) floor($absolute);
        $fraction = (int) round(($absolute - $whole) * $multiplier);

        if ($fraction >= $multiplier) {
            $whole += 1;
            $fraction = 0;
        }

        $words = self::convertWholeNumber($whole).' '.self::currencyName($currencyCode, $whole !== 1);

        if ($decimalPlaces > 0) {
            $fractionLabel = self::fractionLabel($currencyCode);
            $words .= ' and '.self::convertWholeNumber($fraction).' '.$fractionLabel;
        }

        $result = trim($words);

        return $isNegative ? 'Minus '.$result : $result;
    }

    private static function convertWholeNumber(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $parts = [];

        foreach ([1_000_000_000 => 'Billion', 1_000_000 => 'Million', 1_000 => 'Thousand'] as $value => $label) {
            if ($number >= $value) {
                $count = intdiv($number, $value);
                $parts[] = self::convertHundreds($count).' '.$label;
                $number %= $value;
            }
        }

        if ($number > 0) {
            $parts[] = self::convertHundreds($number);
        }

        return trim(implode(' ', $parts));
    }

    private static function convertHundreds(int $number): string
    {
        $parts = [];

        if ($number >= 100) {
            $parts[] = self::ONES[intdiv($number, 100)].' Hundred';
            $number %= 100;
        }

        if ($number >= 20) {
            $parts[] = self::TENS[intdiv($number, 10)];
            $number %= 10;
        }

        if ($number > 0) {
            $parts[] = self::ONES[$number];
        }

        return trim(implode(' ', $parts));
    }

    private static function currencyName(string $currencyCode, bool $plural): string
    {
        $code = strtoupper($currencyCode);

        return match ($code) {
            'USD' => $plural ? 'US Dollars' : 'US Dollar',
            'EUR' => $plural ? 'Euros' : 'Euro',
            'GBP' => $plural ? 'Pounds Sterling' : 'Pound Sterling',
            'RWF' => $plural ? 'Rwandan Francs' : 'Rwandan Franc',
            default => $plural ? $code : $code,
        };
    }

    private static function fractionLabel(string $currencyCode): string
    {
        return match (strtoupper($currencyCode)) {
            'USD', 'EUR', 'GBP' => 'Cents',
            default => 'Cents',
        };
    }
}
