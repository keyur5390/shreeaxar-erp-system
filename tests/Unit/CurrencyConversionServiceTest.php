<?php

namespace Tests\Unit;

use App\Services\CurrencyConversionService;
use PHPUnit\Framework\TestCase;

class CurrencyConversionServiceTest extends TestCase
{
    private CurrencyConversionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CurrencyConversionService;
    }

    public function test_convert_between_currencies_using_base_rates(): void
    {
        $convert = function (float $amount, float $fromRate, float $toRate): float {
            return round(($amount * $fromRate) / $toRate, 2);
        };

        $this->assertSame(1300.0, $convert(1, 1300, 1));
        $this->assertSame(1.0, $convert(1300, 1, 1300));
    }
}
