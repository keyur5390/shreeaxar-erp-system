<?php

namespace Tests\Unit;

use App\Services\CalculationService;
use PHPUnit\Framework\TestCase;

class CalculationServiceTest extends TestCase
{
    private CalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CalculationService;
    }

    public function test_vat_applies_to_all_lines_when_none_include_tax(): void
    {
        $totals = $this->service->calculateQuotationTotals([
            ['rate' => 100, 'quantity' => 2, 'discount_rate' => 0, 'is_tax_included' => false],
            ['rate' => 50, 'quantity' => 1, 'discount_rate' => 0, 'is_tax_included' => false],
        ], 18);

        $this->assertSame(250.0, $totals['subTotal']);
        $this->assertSame(45.0, $totals['vatAmount']);
        $this->assertSame(295.0, $totals['total']);
    }

    public function test_vat_skips_tax_included_lines(): void
    {
        $totals = $this->service->calculateQuotationTotals([
            ['rate' => 100, 'quantity' => 1, 'discount_rate' => 0, 'is_tax_included' => true],
            ['rate' => 200, 'quantity' => 1, 'discount_rate' => 0, 'is_tax_included' => false],
        ], 18);

        $this->assertSame(300.0, $totals['subTotal']);
        $this->assertSame(36.0, $totals['vatAmount']);
        $this->assertSame(336.0, $totals['total']);
    }

    public function test_vat_is_zero_when_all_lines_include_tax(): void
    {
        $totals = $this->service->calculateQuotationTotals([
            ['rate' => 118, 'quantity' => 1, 'discount_rate' => 0, 'is_tax_included' => true],
        ], 18);

        $this->assertSame(118.0, $totals['subTotal']);
        $this->assertSame(0.0, $totals['vatAmount']);
        $this->assertSame(118.0, $totals['total']);
    }
}
