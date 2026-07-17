import { computed } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { currenciesService } from '@/services/currencies.service'
import { STALE_TIME } from '@/lib/queryTimes'
import { defaultCurrencyFromList } from '@/utils/currency'
import type { Currency } from '@/types'

export function useCurrencies() {
  const query = useQuery({
    queryKey: ['currencies'],
    queryFn: () => currenciesService.list(),
    staleTime: STALE_TIME.masters,
  })

  const currencies = computed(() => query.data.value ?? [])
  const activeCurrencies = computed(() => currencies.value.filter((currency) => currency.is_active))
  const defaultCurrency = computed(() => defaultCurrencyFromList(currencies.value))

  function findCurrency(id?: string | null): Currency | null {
    if (!id) return null
    return currencies.value.find((currency) => currency.id === id) ?? null
  }

  return {
    query,
    currencies,
    activeCurrencies,
    defaultCurrency,
    findCurrency,
  }
}
