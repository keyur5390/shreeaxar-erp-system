import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDebounceFn } from '@vueuse/core'
import { useQuery } from '@tanstack/vue-query'
import { quotationsService } from '@/services/quotations.service'
import { quotationStatusesService } from '@/services/quotation-statuses.service'
import { usersService } from '@/services/users.service'
import { useAuthStore } from '@/stores/auth.store'
import { STALE_TIME } from '@/lib/queryTimes'
import type { QuotationListItem, QuotationStatusMaster, UserListItem } from '@/types'

export interface QuotationListFilters {
  search: string
  statusId: string
  authorizedById: string
  myQuotations: boolean
  dateFrom: string
  dateTo: string
  expiringWithin: string
  page: number
}

function queryValue(value: unknown): string {
  return typeof value === 'string' ? value : ''
}

export function useQuotationList(statusFilter?: string) {
  const route = useRoute()
  const router = useRouter()
  const authStore = useAuthStore()

  const searchInput = ref('')
  const debouncedSearch = ref('')
  const statusIdFilter = ref('')
  const authorizedById = ref('')
  const myQuotations = ref(false)
  const dateFrom = ref('')
  const dateTo = ref('')
  const expiringWithin = ref('')
  const page = ref(1)

  const isStatusPage = computed(() => Boolean(statusFilter))
  const showStatusFilter = computed(() => !isStatusPage.value)

  const applySearch = useDebounceFn((value: string) => {
    debouncedSearch.value = value
    page.value = 1
    syncQueryToUrl()
  }, 400)

  watch(searchInput, (value) => applySearch(value))

  function readFiltersFromUrl(): void {
    const query = route.query
    searchInput.value = queryValue(query.search)
    debouncedSearch.value = searchInput.value
    statusIdFilter.value = queryValue(query.status_id)
    authorizedById.value = queryValue(query.authorized_by_id)
    myQuotations.value = query.my === '1'
    dateFrom.value = queryValue(query.date_from)
    dateTo.value = queryValue(query.date_to)
    expiringWithin.value = queryValue(query.expiring_within)
    page.value = Number(queryValue(query.page)) || 1
  }

  function syncQueryToUrl(): void {
    const nextQuery: Record<string, string> = {}

    if (typeof route.query.customer_id === 'string' && route.query.customer_id) {
      nextQuery.customer_id = route.query.customer_id
    }
    if (debouncedSearch.value) nextQuery.search = debouncedSearch.value
    if (showStatusFilter.value && statusIdFilter.value) nextQuery.status_id = statusIdFilter.value
    if (!myQuotations.value && authorizedById.value) nextQuery.authorized_by_id = authorizedById.value
    if (myQuotations.value) nextQuery.my = '1'
    if (dateFrom.value) nextQuery.date_from = dateFrom.value
    if (dateTo.value) nextQuery.date_to = dateTo.value
    if (expiringWithin.value) nextQuery.expiring_within = expiringWithin.value
    if (page.value > 1) nextQuery.page = String(page.value)

    router.replace({ query: nextQuery })
  }

  onMounted(readFiltersFromUrl)

  watch(
    () => route.query,
    () => readFiltersFromUrl(),
    { deep: true },
  )

  const statusesQuery = useQuery({
    queryKey: ['quotation-statuses'],
    queryFn: () => quotationStatusesService.list(),
    staleTime: STALE_TIME.masters,
  })

  const usersQuery = useQuery({
    queryKey: ['users-dropdown'],
    queryFn: () => usersService.getUsers({ is_active: true, per_page: 100 }),
  })

  const statuses = computed<QuotationStatusMaster[]>(() => statusesQuery.data.value ?? [])
  const users = computed<UserListItem[]>(() => usersQuery.data.value?.items ?? [])

  const resolvedStatusId = computed(() => {
    if (statusFilter) {
      return statuses.value.find((status) => status.name === statusFilter)?.id
    }
    return statusIdFilter.value || undefined
  })

  const listParams = computed(() => ({
    search: debouncedSearch.value || undefined,
    status_id: resolvedStatusId.value,
    authorized_by_id: myQuotations.value
      ? authStore.user?.id
      : (authorizedById.value || undefined),
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
    expiring_within: expiringWithin.value ? Number(expiringWithin.value) : undefined,
    page: page.value,
    customer_id: typeof route.query.customer_id === 'string' ? route.query.customer_id : undefined,
  }))

  const statsParams = computed(() => ({
    status_id: resolvedStatusId.value,
    authorized_by_id: listParams.value.authorized_by_id,
    customer_id: listParams.value.customer_id,
    search: listParams.value.search,
    date_from: listParams.value.date_from,
    date_to: listParams.value.date_to,
  }))

  const quotationsQuery = useQuery({
    queryKey: computed(() => ['quotations', 'list', listParams.value]),
    queryFn: () => quotationsService.list(listParams.value),
    staleTime: STALE_TIME.quotationList,
    enabled: computed(() => !statusFilter || statusesQuery.isSuccess.value),
  })

  const statsQuery = useQuery({
    queryKey: computed(() => ['quotations', 'list-stats', statsParams.value]),
    queryFn: () => quotationsService.stats(statsParams.value),
  })

  const quotations = computed<QuotationListItem[]>(() => {
    const data = quotationsQuery.data.value
    if (!data) return []
    return Array.isArray(data) ? data as QuotationListItem[] : data.items ?? []
  })

  const pagination = computed(() => {
    const data = quotationsQuery.data.value
    if (!data || Array.isArray(data)) return undefined
    return data.pagination
  })

  const loadError = computed(() => quotationsQuery.isError.value
    ? (quotationsQuery.error.value instanceof Error ? quotationsQuery.error.value.message : 'Unable to load quotations.')
    : '')

  const hasActiveFilters = computed(() => Boolean(
    debouncedSearch.value
    || (showStatusFilter.value && statusIdFilter.value)
    || authorizedById.value
    || myQuotations.value
    || dateFrom.value
    || dateTo.value
    || expiringWithin.value
    || route.query.customer_id,
  ))

  const tablePagination = computed(() => {
    if (!pagination.value) return undefined
    return {
      page: pagination.value.current_page,
      limit: pagination.value.per_page,
      total: pagination.value.total,
      totalPages: pagination.value.last_page,
    }
  })

  const summaryCount = computed(() => statsQuery.data.value?.total_quotations ?? pagination.value?.total ?? quotations.value.length)
  const summaryTotalValue = computed(() => statsQuery.data.value?.total_value ?? 0)

  const pageTitle = computed(() => statusFilter ?? 'All Quotations')

  function updateFilter(key: keyof QuotationListFilters, value: string | boolean | number): void {
    switch (key) {
      case 'search':
        searchInput.value = String(value)
        debouncedSearch.value = String(value)
        break
      case 'statusId':
        statusIdFilter.value = String(value)
        break
      case 'authorizedById':
        authorizedById.value = String(value)
        break
      case 'myQuotations':
        myQuotations.value = Boolean(value)
        if (myQuotations.value) authorizedById.value = ''
        break
      case 'dateFrom':
        dateFrom.value = String(value)
        break
      case 'dateTo':
        dateTo.value = String(value)
        break
      case 'expiringWithin':
        expiringWithin.value = String(value)
        break
      case 'page':
        page.value = Number(value) || 1
        break
    }
    if (key !== 'page') page.value = 1
    syncQueryToUrl()
  }

  function clearFilters(): void {
    searchInput.value = ''
    debouncedSearch.value = ''
    statusIdFilter.value = ''
    authorizedById.value = ''
    myQuotations.value = false
    dateFrom.value = ''
    dateTo.value = ''
    expiringWithin.value = ''
    page.value = 1
    syncQueryToUrl()
  }

  function setPage(nextPage: number): void {
    page.value = nextPage
    syncQueryToUrl()
  }

  return {
    authStore,
    isStatusPage,
    showStatusFilter,
    pageTitle,
    searchInput,
    statusIdFilter,
    authorizedById,
    myQuotations,
    dateFrom,
    dateTo,
    expiringWithin,
    page,
    statuses,
    users,
    quotations,
    quotationsQuery,
    loadError,
    hasActiveFilters,
    tablePagination,
    summaryCount,
    summaryTotalValue,
    updateFilter,
    clearFilters,
    setPage,
  }
}
