<?php

namespace App\Services;

use App\Models\Currency;

class CurrencyConversionService
{
    /**
     * Convert an amount between currencies using exchange rates relative to the base currency.
     * exchange_rate = how many base-currency units equal 1 unit of this currency.
     */
    public function convert(float $amount, string $fromCurrencyId, string $toCurrencyId): float
    {
        if ($fromCurrencyId === $toCurrencyId) {
            return round($amount, 2);
        }

        $from = Currency::query()->findOrFail($fromCurrencyId);
        $to = Currency::query()->findOrFail($toCurrencyId);

        $fromRate = (float) $from->exchange_rate;
        $toRate = (float) $to->exchange_rate;

        if ($fromRate <= 0 || $toRate <= 0) {
            throw new \InvalidArgumentException('Invalid exchange rate.');
        }

        $baseAmount = $amount * $fromRate;

        return round($baseAmount / $toRate, 2);
    }

    public function defaultCurrency(): Currency
    {
        return Currency::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->first()
            ?? Currency::query()->where('is_active', true)->orderBy('code')->firstOrFail();
    }
}
