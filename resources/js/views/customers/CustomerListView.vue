<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useDebounceFn } from '@vueuse/core'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { Eye, MapPin, Pencil, Plus, Trash2, X } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import DataTable from '@/components/ui/DataTable.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import PermissionGate from '@/components/ui/PermissionGate.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import FilterPanel from '@/components/ui/FilterPanel.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue'
import { customersService } from '@/services/customers.service'
import { useToast } from '@/composables/useToast'
import type { CustomerListItem } from '@/types'

const router = useRouter()
const queryClient = useQueryClient()
const { toast } = useToast()

const searchInput = ref('')
const debouncedSearch = ref('')
const statusFilter = ref<'' | 'active' | 'inactive'>('')
const page = ref(1)
const deleteTarget = ref<CustomerListItem | null>(null)
const optimisticStatus = ref<Record<string, boolean>>({})

const applySearch = useDebounceFn((value: string) => {
  debouncedSearch.value = value
  page.value = 1
}, 400)

watch(searchInput, (value) => applySearch(value))

const filters = computed(() => ({
  search: debouncedSearch.value || undefined,
  is_active: statusFilter.value === 'active' ? true : statusFilter.value === 'inactive' ? false : undefined,
  page: page.value,
}))

const customersQuery = useQuery({
  queryKey: computed(() => ['customers', filters.value]),
  queryFn: () => customersService.list(filters.value),
})

const customers = computed(() => customersQuery.data.value?.items ?? [])
const pagination = computed(() => customersQuery.data.value?.pagination)
const loadError = computed(() => customersQuery.isError.value
  ? (customersQuery.error.value instanceof Error ? customersQuery.error.value.message : 'Unable to load customers.')
  : '')

const hasActiveFilters = computed(() => Boolean(debouncedSearch.value || statusFilter.value))

const emptyTitle = computed(() => hasActiveFilters.value ? 'No customers match your filters' : 'No customers yet')
const emptyDescription = computed(() => hasActiveFilters.value
  ? 'Try adjusting your search or filter criteria.'
  : 'Get started by adding your first customer.')

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
  { label: 'Company', key: 'company' },
  { label: 'Email', key: 'email' },
  { label: 'Contact', key: 'contact' },
  { label: 'Addresses', key: 'addresses' },
  { label: 'Quotations', key: 'quotations' },
  { label: 'Status', key: 'status' },
  { label: 'Actions', key: 'actions' },
] as const

function customerStatus(customer: CustomerListItem): boolean {
  return optimisticStatus.value[customer.id] ?? customer.is_active
}

function clearFilters() {
  searchInput.value = ''
  debouncedSearch.value = ''
  statusFilter.value = ''
  page.value = 1
}

function goToCustomer(customer: CustomerListItem) {
  router.push(`/customers/${customer.id}`)
}

function goToQuotations(customer: CustomerListItem) {
  router.push(`/quotations?customer_id=${customer.id}`)
}

const deleteDescription = computed(() => {
  if (!deleteTarget.value) return ''
  if (deleteTarget.value.quotations_count > 0) {
    return `This customer has ${deleteTarget.value.quotations_count} quotation${deleteTarget.value.quotations_count === 1 ? '' : 's'} and will be deactivated, not permanently deleted.`
  }
  return `Permanently delete ${deleteTarget.value.company_name}? This action cannot be undone.`
})

const toggleMutation = useMutation({
  mutationFn: (customer: CustomerListItem) => customersService.toggleStatus(customer.id),
  onMutate: async (customer) => {
    const current = customerStatus(customer)
    optimisticStatus.value = { ...optimisticStatus.value, [customer.id]: !current }
  },
  onSuccess: (data) => {
    optimisticStatus.value = { ...optimisticStatus.value, [data.id]: data.is_active }
    queryClient.invalidateQueries({ queryKey: ['customers'] })
    toast(data.is_active ? 'Customer activated successfully.' : 'Customer deactivated successfully.', 'success')
  },
  onError: (error: unknown, customer) => {
    const reverted = { ...optimisticStatus.value }
    delete reverted[customer.id]
    optimisticStatus.value = reverted
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to update customer status.')
    toast(message, 'error')
  },
})

const deleteMutation = useMutation({
  mutationFn: (customer: CustomerListItem) => customersService.remove(customer.id),
  onSuccess: (result) => {
    queryClient.invalidateQueries({ queryKey: ['customers'] })
    toast(result.message ?? (result.soft_deleted ? 'Customer deactivated successfully.' : 'Customer deleted successfully.'), 'success')
    deleteTarget.value = null
  },
  onError: (error: unknown) => {
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to delete customer.')
    toast(message, 'error')
    deleteTarget.value = null
  },
})
</script>

<template>
  <section>
    <PageHeader
      title="Customer Management"
      subtitle="Manage customer companies, contacts, and addresses."
      :breadcrumb="[{ label: 'Customers' }]"
    >
      <template #action>
        <PermissionGate module="customers" action="create">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg bg-brand-blue px-4 py-2 text-sm font-semibold text-white hover:bg-brand-blue/90"
            @click="router.push('/customers/new')"
          >
            <Plus class="h-4 w-4" />
            Add New Customer
          </button>
        </PermissionGate>
      </template>
    </PageHeader>

    <div v-if="loadError" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      {{ loadError }}
    </div>

    <DataTable
      :columns="[...columns]"
      :data="customers"
      :is-loading="customersQuery.isLoading.value"
      :skeleton-rows="8"
      :pagination="tablePagination"
      @page-change="page = $event"
      @row-click="goToCustomer"
    >
      <template #topBar>
        <FilterPanel :has-active-filters="hasActiveFilters">
          <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
            <label class="block text-sm">
              <span class="mb-1 block font-medium text-slate-700">Search</span>
              <input
                v-model="searchInput"
                type="search"
                placeholder="Name, email, or TIN…"
                class="w-full rounded-md border px-3 py-2"
              />
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
      </template>

      <template v-if="!customersQuery.isLoading.value && !customers.length" #emptyState>
        <EmptyState :title="emptyTitle" :description="emptyDescription">
          <template v-if="!hasActiveFilters" #action>
            <PermissionGate module="customers" action="create">
              <button
                type="button"
                class="rounded-lg bg-brand-blue px-4 py-2 text-sm font-semibold text-white hover:bg-brand-blue/90"
                @click="router.push('/customers/new')"
              >
                Add New Customer
              </button>
            </PermissionGate>
          </template>
        </EmptyState>
      </template>

      <template #cell-company="{ row }">
        <div>
          <p class="truncate font-medium text-slate-900">{{ (row as CustomerListItem).company_name }}</p>
          <p v-if="(row as CustomerListItem).tin_number" class="text-xs text-slate-500">
            TIN: {{ (row as CustomerListItem).tin_number }}
          </p>
        </div>
      </template>

      <template #cell-email="{ row }">
        {{ (row as CustomerListItem).email || '—' }}
      </template>

      <template #cell-contact="{ row }">
        {{ (row as CustomerListItem).contact_number || '—' }}
      </template>

      <template #cell-addresses="{ row }">
        <span
          class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700"
        >
          <MapPin class="h-3 w-3" />
          {{ (row as CustomerListItem).addresses_count }}
        </span>
      </template>

      <template #cell-quotations="{ row }">
        <button
          type="button"
          class="inline-flex items-center rounded-full bg-brand-blue/10 px-2.5 py-0.5 text-xs font-medium text-brand-blue hover:bg-brand-blue/20"
          @click.stop="goToQuotations(row as CustomerListItem)"
        >
          {{ (row as CustomerListItem).quotations_count }}
        </button>
      </template>

      <template #cell-status="{ row }">
        <StatusBadge
          :label="customerStatus(row as CustomerListItem) ? 'Active' : 'Inactive'"
          :color="customerStatus(row as CustomerListItem) ? '#16a34a' : '#94a3b8'"
          size="sm"
        />
      </template>

      <template #cell-actions="{ row }">
        <div class="flex items-center gap-2" @click.stop>
          <RouterLink
            :to="`/customers/${(row as CustomerListItem).id}`"
            class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
            title="View"
          >
            <Eye class="h-4 w-4" />
          </RouterLink>
          <PermissionGate module="customers" action="edit">
            <RouterLink
              :to="`/customers/${(row as CustomerListItem).id}/edit`"
              class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
              title="Edit"
            >
              <Pencil class="h-4 w-4" />
            </RouterLink>
            <ToggleSwitch
              :model-value="customerStatus(row as CustomerListItem)"
              :disabled="toggleMutation.isPending.value"
              @update:model-value="toggleMutation.mutate(row as CustomerListItem)"
            />
          </PermissionGate>
          <PermissionGate module="customers" action="delete">
            <button
              type="button"
              class="rounded p-1.5 text-red-500 hover:bg-red-50"
              title="Delete"
              @click="deleteTarget = row as CustomerListItem"
            >
              <Trash2 class="h-4 w-4" />
            </button>
          </PermissionGate>
        </div>
      </template>
    </DataTable>

    <ConfirmDialog
      :open="Boolean(deleteTarget)"
      title="Delete customer?"
      :description="deleteDescription"
      confirm-label="Delete"
      confirm-variant="destructive"
      :require-type="deleteTarget?.quotations_count ? undefined : 'DELETE'"
      @cancel="deleteTarget = null"
      @confirm="deleteTarget && deleteMutation.mutate(deleteTarget)"
    />
  </section>
</template>
