export interface QuotationCalcItem { rate: number; qty: number; discountRate?: number; isTaxIncluded?: boolean }
export interface QuotationTotals { subTotal: number; vatAmount: number; discountAmount: number; total: number }

export function calculateLineTotal(rate: number, qty: number, discountRate = 0): number {
  const gross = (rate || 0) * (qty || 0)
  return Math.max(gross - (gross * (discountRate || 0)) / 100, 0)
}

export function calculateQuotationTotals(items: QuotationCalcItem[], vatRate = 0): QuotationTotals {
  let subTotal = 0
  let discountAmount = 0
  let taxableSubTotal = 0

  for (const item of items) {
    const gross = (item.rate || 0) * (item.qty || 0)
    const lineTotal = calculateLineTotal(item.rate, item.qty, item.discountRate || 0)
    subTotal += lineTotal
    discountAmount += gross - lineTotal
    if (!item.isTaxIncluded) {
      taxableSubTotal += lineTotal
    }
  }

  const vatAmount = (taxableSubTotal * (vatRate || 0)) / 100

  return {
    subTotal,
    vatAmount,
    discountAmount,
    total: subTotal + vatAmount,
  }
}
