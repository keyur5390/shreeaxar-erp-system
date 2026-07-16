import { QueryClient } from '@tanstack/vue-query'
import { STALE_TIME } from './queryTimes'

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      staleTime: STALE_TIME.quotationList,
    },
  },
})
