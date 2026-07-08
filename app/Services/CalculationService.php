<?php

namespace App\Services;

class CalculationService
{
    /**
     * Calculate quotation totals with discounts deducted at line level.
     *
     * The final total intentionally does not subtract discountAmount again,
     * because each lineTotal already has its discount deduction applied.
     */
    public function calculateQuotationTotals(array $items, float $vatRate): array
    {
        $itemsWithTotals = array_map(function (array $item): array {
            $rate = (float) ($item['rate'] ?? 0);
            $quantity = (float) ($item['quantity'] ?? 0);
            $discountRate = (float) ($item['discount_rate'] ?? 0);

            $discountDeduction = round($rate * $quantity * $discountRate / 100, 2);
            $lineTotal = round($rate * $quantity - $discountDeduction, 2);

            return array_merge($item, [
                'discountDeduction' => $discountDeduction,
                'lineTotal' => $lineTotal,
            ]);
        }, $items);

        $subTotal = round(array_sum(array_column($itemsWithTotals, 'lineTotal')), 2);
        $vatAmount = round($subTotal * $vatRate / 100, 2);
        $discountAmount = round(array_sum(array_column($itemsWithTotals, 'discountDeduction')), 2);
        $total = round($subTotal + $vatAmount, 2);

        return [
            'subTotal' => $subTotal,
            'vatAmount' => $vatAmount,
            'discountAmount' => $discountAmount,
            'total' => $total,
            'items' => $itemsWithTotals,
        ];
    }
}
