export interface QuotationCalcItem { rate: number; qty: number; discountRate?: number }
export interface QuotationTotals { subTotal: number; vatAmount: number; discountAmount: number; total: number }

export function calculateLineTotal(rate: number, qty: number, discountRate = 0): number {
  const gross = (rate || 0) * (qty || 0)
  return Math.max(gross - (gross * (discountRate || 0)) / 100, 0)
}

export function calculateQuotationTotals(items: QuotationCalcItem[], vatRate = 0): QuotationTotals {
  return items.reduce<QuotationTotals>((totals, item) => {
    const gross = (item.rate || 0) * (item.qty || 0)
    const lineTotal = calculateLineTotal(item.rate, item.qty, item.discountRate || 0)
    totals.subTotal += lineTotal
    totals.discountAmount += gross - lineTotal
    totals.vatAmount = (totals.subTotal * (vatRate || 0)) / 100
    totals.total = totals.subTotal + totals.vatAmount
    return totals
  }, { subTotal: 0, vatAmount: 0, discountAmount: 0, total: 0 })
}
