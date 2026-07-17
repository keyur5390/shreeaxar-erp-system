<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useDebounceFn } from '@vueuse/core'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import {
  Copy,
  Eye,
  LayoutGrid,
  LayoutList,
  Package,
  Pencil,
  Plus,
  Trash2,
  X,
} from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import DataTable from '@/components/ui/DataTable.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import PermissionGate from '@/components/ui/PermissionGate.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FilterPanel from '@/components/ui/FilterPanel.vue'
import LazyImage from '@/components/ui/LazyImage.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue'
import { productsService } from '@/services/products.service'
import { unitsService } from '@/services/units.service'
import { useToast } from '@/composables/useToast'
import { STALE_TIME } from '@/lib/queryTimes'
import { formatCurrency, formatDate } from '@/utils/formatters'
import type { ProductListItem } from '@/types'

const router = useRouter()
const queryClient = useQueryClient()
const { toast } = useToast()

const searchInput = ref('')
const debouncedSearch = ref('')
const unitFilter = ref('')
const statusFilter = ref<'' | 'active' | 'inactive'>('')
const page = ref(1)
const viewMode = ref<'grid' | 'table'>('grid')
const deleteTarget = ref<ProductListItem | null>(null)
const optimisticStatus = ref<Record<string, boolean>>({})

const applySearch = useDebounceFn((value: string) => {
  debouncedSearch.value = value
  page.value = 1
}, 400)

watch(searchInput, (value) => applySearch(value))

const filters = computed(() => ({
  search: debouncedSearch.value || undefined,
  unit_id: unitFilter.value || undefined,
  is_active: statusFilter.value === 'active' ? true : statusFilter.value === 'inactive' ? false : undefined,
  page: page.value,
  grid: viewMode.value === 'grid',
}))

const productsQuery = useQuery({
  queryKey: computed(() => ['products', filters.value]),
  queryFn: () => productsService.list(filters.value),
})

const unitsQuery = useQuery({
  queryKey: ['units'],
  queryFn: () => unitsService.list(),
  staleTime: STALE_TIME.masters,
})

const products = computed(() => productsQuery.data.value?.items ?? [])
const pagination = computed(() => productsQuery.data.value?.pagination)
const units = computed(() => unitsQuery.data.value ?? [])
const loadError = computed(() => productsQuery.isError.value
  ? (productsQuery.error.value instanceof Error ? productsQuery.error.value.message : 'Unable to load products.')
  : '')

const hasActiveFilters = computed(() => Boolean(debouncedSearch.value || unitFilter.value || statusFilter.value))

const emptyTitle = computed(() => hasActiveFilters.value ? 'No products match your filters' : 'No products yet')
const emptyDescription = computed(() => hasActiveFilters.value
  ? 'Try adjusting your search or filter criteria.'
  : 'Get started by adding your first product.')

const tablePagination = computed(() => {
  if (!pagination.value) return undefined
  return {
    page: pagination.value.current_page,
    limit: pagination.value.per_page,
    total: pagination.value.total,
    totalPages: pagination.value.last_page,
  }
})

const columns = [
  { label: 'Image', key: 'image' },
  { label: 'Product', key: 'product' },
  { label: 'Rate', key: 'rate' },
  { label: 'Unit', key: 'unit' },
  { label: 'Status', key: 'status' },
  { label: 'Created', key: 'created' },
  { label: 'Actions', key: 'actions' },
] as const

function productStatus(product: ProductListItem): boolean {
  return optimisticStatus.value[product.id] ?? product.is_active
}

function clearFilters() {
  searchInput.value = ''
  debouncedSearch.value = ''
  unitFilter.value = ''
  statusFilter.value = ''
  page.value = 1
}

function goToProduct(product: ProductListItem) {
  router.push(`/products/${product.id}`)
}

function setViewMode(mode: 'grid' | 'table') {
  if (viewMode.value !== mode) {
    viewMode.value = mode
    page.value = 1
  }
}

const deleteDescription = computed(() => {
  if (!deleteTarget.value) return ''
  return `Delete ${deleteTarget.value.title}? If this product is used in quotations it will be deactivated instead of permanently deleted.`
})

const toggleMutation = useMutation({
  mutationFn: (product: ProductListItem) => productsService.toggleStatus(product.id),
  onMutate: async (product) => {
    const current = productStatus(product)
    optimisticStatus.value = { ...optimisticStatus.value, [product.id]: !current }
  },
  onSuccess: (data) => {
    optimisticStatus.value = { ...optimisticStatus.value, [data.id]: data.is_active }
    queryClient.invalidateQueries({ queryKey: ['products'] })
    toast(data.is_active ? 'Product activated successfully.' : 'Product deactivated successfully.', 'success')
  },
  onError: (error: unknown, product) => {
    const reverted = { ...optimisticStatus.value }
    delete reverted[product.id]
    optimisticStatus.value = reverted
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to update product status.')
    toast(message, 'error')
  },
})

const deleteMutation = useMutation({
  mutationFn: (product: ProductListItem) => productsService.remove(product.id),
  onSuccess: (result) => {
    queryClient.invalidateQueries({ queryKey: ['products'] })
    toast(result.message ?? (result.soft_deleted ? 'Product deactivated successfully.' : 'Product deleted successfully.'), 'success')
    deleteTarget.value = null
  },
  onError: (error: unknown) => {
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to delete product.')
    toast(message, 'error')
    deleteTarget.value = null
  },
})

const duplicateMutation = useMutation({
  mutationFn: (product: ProductListItem) => productsService.duplicate(product.id),
  onSuccess: (result) => {
    queryClient.invalidateQueries({ queryKey: ['products'] })
    if (result.warnings.length) {
      toast(`Product duplicated with ${result.warnings.length} image warning(s).`, 'info')
    } else {
      toast('Product duplicated successfully.', 'success')
    }
    router.push(`/products/${result.product.id}/edit`)
  },
  onError: (error: unknown) => {
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to duplicate product.')
    toast(message, 'error')
  },
})
</script>

<template>
  <section>
    <PageHeader
      title="Product Catalogue"
      subtitle="Browse, search, and manage your product inventory."
      :breadcrumb="[{ label: 'Products' }]"
    >
      <template #action>
        <div class="flex items-center gap-2">
          <div class="inline-flex rounded-lg border bg-white p-1">
            <button
              type="button"
              class="rounded-md p-2 transition-colors"
              :class="viewMode === 'grid' ? 'bg-brand-blue text-white' : 'text-slate-600 hover:bg-slate-50'"
              title="Grid view"
              @click="setViewMode('grid')"
            >
              <LayoutGrid class="h-4 w-4" />
            </button>
            <button
              type="button"
              class="rounded-md p-2 transition-colors"
              :class="viewMode === 'table' ? 'bg-brand-blue text-white' : 'text-slate-600 hover:bg-slate-50'"
              title="Table view"
              @click="setViewMode('table')"
            >
              <LayoutList class="h-4 w-4" />
            </button>
          </div>
          <PermissionGate module="products" action="create">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-lg bg-brand-blue px-4 py-2 text-sm font-semibold text-white hover:bg-brand-blue/90"
              @click="router.push('/products/new')"
            >
              <Plus class="h-4 w-4" />
              Add New Product
            </button>
          </PermissionGate>
        </div>
      </template>
    </PageHeader>

    <div v-if="loadError" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      {{ loadError }}
    </div>

    <FilterPanel class="mb-4" :has-active-filters="hasActiveFilters">
      <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
        <label class="block text-sm">
          <span class="mb-1 block font-medium text-slate-700">Search</span>
          <input
            v-model="searchInput"
            type="search"
            placeholder="Title or model number…"
            class="w-full rounded-md border px-3 py-2"
          />
        </label>
        <label class="block text-sm">
          <span class="mb-1 block font-medium text-slate-700">Unit</span>
          <select v-model="unitFilter" class="w-full rounded-md border px-3 py-2" @change="page = 1">
            <option value="">All units</option>
            <option v-for="unit in units" :key="unit.id" :value="unit.id">
              {{ unit.code }} — {{ unit.name }}
            </option>
          </select>
        </label>
        <label class="block text-sm">
          <span class="mb-1 block font-medium text-slate-700">Status</span>
          <select v-model="statusFilter" class="w-full rounded-md border px-3 py-2" @change="page = 1">
            <option value="">All</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </label>
        <div class="flex items-end">
          <button
            v-if="hasActiveFilters"
            type="button"
            class="inline-flex items-center gap-1 rounded-md border px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
            @click="clearFilters"
          >
            <X class="h-4 w-4" />
            Clear
          </button>
        </div>
      </div>
    </FilterPanel>

    <!-- Grid view -->
    <template v-if="viewMode === 'grid'">
      <div v-if="productsQuery.isLoading.value" class="grid gap-4 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4">
        <div v-for="n in 8" :key="n" class="animate-pulse overflow-hidden rounded-lg border bg-white shadow-card">
          <div class="aspect-square bg-slate-200" />
          <div class="space-y-2 p-4">
            <div class="h-4 w-3/4 rounded bg-slate-200" />
            <div class="h-3 w-1/2 rounded bg-slate-200" />
            <div class="h-4 w-1/3 rounded bg-slate-200" />
          </div>
        </div>
      </div>

      <EmptyState
        v-else-if="!products.length"
        :title="emptyTitle"
        :description="emptyDescription"
      >
        <template v-if="!hasActiveFilters" #action>
          <PermissionGate module="products" action="create">
            <button
              type="button"
              class="rounded-lg bg-brand-blue px-4 py-2 text-sm font-semibold text-white hover:bg-brand-blue/90"
              @click="router.push('/products/new')"
            >
              Add New Product
            </button>
          </PermissionGate>
        </template>
      </EmptyState>

      <div v-else class="grid gap-4 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4">
        <article
          v-for="product in products"
          :key="product.id"
          class="group relative cursor-pointer overflow-hidden rounded-lg border bg-white shadow-card transition-shadow hover:shadow-md"
          @click="goToProduct(product)"
        >
          <div class="relative aspect-square overflow-hidden bg-slate-100">
            <LazyImage
              v-if="product.primary_image_url"
              :src="product.primary_image_url"
              :alt="product.title"
              class="h-full w-full"
            />
            <div v-else class="flex h-full w-full items-center justify-center text-slate-300">
              <Package class="h-16 w-16" />
            </div>
            <div class="absolute left-2 top-2">
              <StatusBadge
                :label="productStatus(product) ? 'Active' : 'Inactive'"
                :color="productStatus(product) ? '#16a34a' : '#94a3b8'"
                size="sm"
              />
            </div>
            <div
              class="absolute inset-0 flex items-center justify-center gap-2 bg-slate-950/60 opacity-100 transition-opacity lg:opacity-0 lg:group-hover:opacity-100"
              @click.stop
            >
              <PermissionGate module="products" action="edit">
                <button
                  type="button"
                  class="rounded-lg bg-white p-2 text-slate-700 hover:bg-slate-100"
                  title="Edit"
                  @click="router.push(`/products/${product.id}/edit`)"
                >
                  <Pencil class="h-4 w-4" />
                </button>
                <button
                  type="button"
                  class="rounded-lg bg-white p-2 text-slate-700 hover:bg-slate-100"
                  title="Toggle status"
                  :disabled="toggleMutation.isPending.value"
                  @click="toggleMutation.mutate(product)"
                >
                  <Eye class="h-4 w-4" />
                </button>
              </PermissionGate>
              <PermissionGate module="products" action="create">
                <button
                  type="button"
                  class="rounded-lg bg-white p-2 text-slate-700 hover:bg-slate-100"
                  title="Duplicate"
                  :disabled="duplicateMutation.isPending.value"
                  @click="duplicateMutation.mutate(product)"
                >
                  <Copy class="h-4 w-4" />
                </button>
              </PermissionGate>
              <PermissionGate module="products" action="delete">
                <button
                  type="button"
                  class="rounded-lg bg-white p-2 text-red-600 hover:bg-red-50"
                  title="Delete"
                  @click="deleteTarget = product"
                >
                  <Trash2 class="h-4 w-4" />
                </button>
              </PermissionGate>
            </div>
          </div>
          <div class="p-4">
            <h3 class="truncate font-semibold text-slate-900">{{ product.title }}</h3>
            <p v-if="product.model_number" class="truncate text-xs text-slate-500">{{ product.model_number }}</p>
            <p v-else class="text-xs text-slate-400">No model number</p>
            <div class="mt-2 flex items-center justify-between gap-2">
              <span class="font-bold text-brand-blue">{{ formatCurrency(product.rate, product.currency) }}</span>
              <span
                v-if="product.unit"
                class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600"
              >
                {{ product.unit.code }}
              </span>
            </div>
          </div>
        </article>
      </div>

      <div
        v-if="tablePagination && products.length"
        class="mt-4 flex flex-col gap-3 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between"
      >
        <span>Page {{ tablePagination.page }} of {{ tablePagination.totalPages }} · {{ tablePagination.total }} total</span>
        <div class="flex items-center gap-2">
          <button
            class="rounded-md border px-3 py-1 disabled:opacity-50"
            :disabled="tablePagination.page <= 1"
            @click="page = tablePagination.page - 1"
          >
            Previous
          </button>
          <button
            class="rounded-md border px-3 py-1 disabled:opacity-50"
            :disabled="tablePagination.page >= tablePagination.totalPages"
            @click="page = tablePagination.page + 1"
          >
            Next
          </button>
        </div>
      </div>
    </template>

    <!-- Table view -->
    <DataTable
      v-else
      :columns="[...columns]"
      :data="products"
      :is-loading="productsQuery.isLoading.value"
      :skeleton-rows="8"
      :pagination="tablePagination"
      @page-change="page = $event"
      @row-click="goToProduct"
    >
      <template v-if="!productsQuery.isLoading.value && !products.length" #emptyState>
        <EmptyState :title="emptyTitle" :description="emptyDescription">
          <template v-if="!hasActiveFilters" #action>
            <PermissionGate module="products" action="create">
              <button
                type="button"
                class="rounded-lg bg-brand-blue px-4 py-2 text-sm font-semibold text-white hover:bg-brand-blue/90"
                @click="router.push('/products/new')"
              >
                Add New Product
              </button>
            </PermissionGate>
          </template>
        </EmptyState>
      </template>

      <template #cell-image="{ row }">
        <div class="h-12 w-12 overflow-hidden rounded-md border bg-slate-100">
          <LazyImage
            v-if="(row as ProductListItem).primary_image_url"
            :src="(row as ProductListItem).primary_image_url!"
            :alt="(row as ProductListItem).title"
            class="h-full w-full"
          />
          <div v-else class="flex h-full w-full items-center justify-center text-slate-300">
            <Package class="h-5 w-5" />
          </div>
        </div>
      </template>

      <template #cell-product="{ row }">
        <div>
          <p class="font-medium text-slate-900">{{ (row as ProductListItem).title }}</p>
          <p v-if="(row as ProductListItem).model_number" class="text-xs text-slate-500">
            {{ (row as ProductListItem).model_number }}
          </p>
        </div>
      </template>

      <template #cell-rate="{ row }">
        <span class="font-semibold text-brand-blue">{{ formatCurrency((row as ProductListItem).rate, (row as ProductListItem).currency) }}</span>
      </template>

      <template #cell-unit="{ row }">
        {{ (row as ProductListItem).unit?.code ?? '—' }}
      </template>

      <template #cell-status="{ row }">
        <StatusBadge
          :label="productStatus(row as ProductListItem) ? 'Active' : 'Inactive'"
          :color="productStatus(row as ProductListItem) ? '#16a34a' : '#94a3b8'"
          size="sm"
        />
      </template>

      <template #cell-created="{ row }">
        {{ formatDate((row as ProductListItem).created_at) }}
      </template>

      <template #cell-actions="{ row }">
        <div class="flex items-center gap-2" @click.stop>
          <RouterLink
            :to="`/products/${(row as ProductListItem).id}`"
            class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
            title="View"
          >
            <Eye class="h-4 w-4" />
          </RouterLink>
          <PermissionGate module="products" action="edit">
            <RouterLink
              :to="`/products/${(row as ProductListItem).id}/edit`"
              class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
              title="Edit"
            >
              <Pencil class="h-4 w-4" />
            </RouterLink>
            <ToggleSwitch
              :model-value="productStatus(row as ProductListItem)"
              :disabled="toggleMutation.isPending.value"
              @update:model-value="toggleMutation.mutate(row as ProductListItem)"
            />
          </PermissionGate>
          <PermissionGate module="products" action="create">
            <button
              type="button"
              class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
              title="Duplicate"
              :disabled="duplicateMutation.isPending.value"
              @click="duplicateMutation.mutate(row as ProductListItem)"
            >
              <Copy class="h-4 w-4" />
            </button>
          </PermissionGate>
          <PermissionGate module="products" action="delete">
            <button
              type="button"
              class="rounded p-1.5 text-red-500 hover:bg-red-50"
              title="Delete"
              @click="deleteTarget = row as ProductListItem"
            >
              <Trash2 class="h-4 w-4" />
            </button>
          </PermissionGate>
        </div>
      </template>
    </DataTable>

    <ConfirmDialog
      :open="Boolean(deleteTarget)"
      title="Delete product?"
      :description="deleteDescription"
      confirm-label="Delete"
      confirm-variant="destructive"
      @cancel="deleteTarget = null"
      @confirm="deleteTarget && deleteMutation.mutate(deleteTarget)"
    />
  </section>
</template>
