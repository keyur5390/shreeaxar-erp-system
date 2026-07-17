import type { Currency } from '@/types'

export type CurrencyLike = Pick<Currency, 'code' | 'symbol' | 'decimal_places'> & { id?: string }

export function convertCurrencyAmount(
  amount: number,
  from: Pick<Currency, 'id' | 'exchange_rate'>,
  to: Pick<Currency, 'id' | 'exchange_rate'>,
): number {
  if (from.id === to.id) {
    return amount
  }

  const fromRate = from.exchange_rate || 1
  const toRate = to.exchange_rate || 1

  return Math.round(((amount * fromRate) / toRate) * 100) / 100
}

export function defaultCurrencyFromList(currencies: Currency[]): Currency | null {
  return currencies.find((currency) => currency.is_default && currency.is_active)
    ?? currencies.find((currency) => currency.is_active)
    ?? null
}
