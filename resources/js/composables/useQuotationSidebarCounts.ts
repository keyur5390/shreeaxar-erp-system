import { computed } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { quotationsService } from '@/services/quotations.service'
import { QUOTATION_STATUS_STATS_KEYS } from '@/utils/quotationStatuses'

export function useQuotationSidebarCounts() {
  const statsQuery = useQuery({
    queryKey: ['quotations', 'sidebar-stats'],
    queryFn: () => quotationsService.stats(),
    refetchInterval: 60_000,
    refetchIntervalInBackground: false,
  })

  const counts = computed<Record<string, number>>(() => {
    const stats = statsQuery.data.value
    if (!stats) return {}

    const payload: Record<string, number> = {
      all: stats.total_quotations,
    }

    stats.by_status.forEach((row) => {
      const key = QUOTATION_STATUS_STATS_KEYS[row.status_name]
      if (key) payload[key] = row.count
    })

    return payload
  })

  return {
    counts,
    isLoading: statsQuery.isLoading,
    refetch: statsQuery.refetch,
  }
}
