<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { ExternalLink, MapPin, Pencil, Trash2 } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import PermissionGate from '@/components/ui/PermissionGate.vue'
import Tabs from '@/components/ui/Tabs.vue'
import TabsList from '@/components/ui/TabsList.vue'
import TabsTrigger from '@/components/ui/TabsTrigger.vue'
import TabsContent from '@/components/ui/TabsContent.vue'
import { customersService } from '@/services/customers.service'
import { quotationsService } from '@/services/quotations.service'
import { useToast } from '@/composables/useToast'
import { abbreviateCurrency, formatCurrency, formatDate } from '@/utils/formatters'
import type { CustomerAddress } from '@/types'

const route = useRoute()
const router = useRouter()
const queryClient = useQueryClient()
const { toast } = useToast()

const customerId = computed(() => String(route.params.id))
const deleteOpen = ref(false)
const optimisticActive = ref<boolean | null>(null)
const activeAddressTab = ref('')

const customerQuery = useQuery({
  queryKey: computed(() => ['customers', customerId.value]),
  queryFn: () => customersService.get(customerId.value),
})

const statsQuery = useQuery({
  queryKey: computed(() => ['customers', customerId.value, 'stats']),
  queryFn: () => customersService.stats(customerId.value),
})

const quotationsQuery = useQuery({
  queryKey: computed(() => ['quotations', 'customer', customerId.value]),
  queryFn: () => quotationsService.list({
    customer_id: customerId.value,
    limit: 5,
    sort: 'created_at_desc',
  }),
})

const customer = computed(() => customerQuery.data.value)
const stats = computed(() => statsQuery.data.value)
const recentQuotations = computed(() => quotationsQuery.data.value ?? [])

const isActive = computed(() => optimisticActive.value ?? customer.value?.is_active ?? false)

const addressGroups = computed(() => {
  const addresses = customer.value?.addresses ?? []
  const groups = new Map<string, { label: string; addresses: CustomerAddress[] }>()

  addresses.forEach((address) => {
    const key = address.address_type_id ?? 'unknown'
    const label = address.address_type_name ?? 'Other'
    const existing = groups.get(key)
    if (existing) {
      existing.addresses.push(address)
    } else {
      groups.set(key, { label, addresses: [address] })
    }
  })

  return Array.from(groups.entries()).map(([key, group]) => ({
    key,
    label: group.label,
    addresses: group.addresses,
  }))
})

const defaultAddressTab = computed(() => addressGroups.value[0]?.key ?? '')
const currentAddressTab = computed({
  get: () => activeAddressTab.value || defaultAddressTab.value,
  set: (value: string) => { activeAddressTab.value = value },
})

const deleteDescription = computed(() => {
  const count = stats.value?.total_quotations ?? 0
  if (count > 0) {
    return `This customer has ${count} quotation${count === 1 ? '' : 's'} and will be deactivated, not permanently deleted.`
  }
  return `Permanently delete ${customer.value?.company_name}? This action cannot be undone.`
})

const toggleMutation = useMutation({
  mutationFn: () => customersService.toggleStatus(customerId.value),
  onMutate: () => {
    optimisticActive.value = !isActive.value
  },
  onSuccess: (data) => {
    optimisticActive.value = data.is_active
    queryClient.invalidateQueries({ queryKey: ['customers'] })
    queryClient.invalidateQueries({ queryKey: ['customers', customerId.value] })
    toast(data.is_active ? 'Customer activated successfully.' : 'Customer deactivated successfully.', 'success')
  },
  onError: (error: unknown) => {
    optimisticActive.value = null
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to update status.')
    toast(message, 'error')
  },
})

const deleteMutation = useMutation({
  mutationFn: () => customersService.remove(customerId.value),
  onSuccess: (result) => {
    queryClient.invalidateQueries({ queryKey: ['customers'] })
    toast(result.message ?? (result.soft_deleted ? 'Customer deactivated successfully.' : 'Customer deleted successfully.'), 'success')
    router.push('/customers')
  },
  onError: (error: unknown) => {
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to delete customer.')
    toast(message, 'error')
    deleteOpen.value = false
  },
})

function formatAddress(address: CustomerAddress): string {
  const parts = [
    address.address_line_1,
    address.address_line_2,
    address.city,
    address.state_name,
    address.country_name,
    address.postal_code,
  ].filter(Boolean)
  return parts.join(', ')
}
</script>

<template>
  <section>
    <PageHeader
      :title="customer?.company_name || 'Customer Detail'"
      subtitle="View customer information, addresses, and quotation history."
      :breadcrumb="[
        { label: 'Customers', href: '/customers' },
        { label: customer?.company_name || 'Detail' },
      ]"
    >
      <template #action>
        <div class="flex flex-wrap items-center gap-2">
          <StatusBadge
            v-if="customer"
            :label="isActive ? 'Active' : 'Inactive'"
            :color="isActive ? '#16a34a' : '#94a3b8'"
          />
          <PermissionGate module="customers" action="edit">
            <button
              type="button"
              class="inline-flex items-center gap-1 rounded-md border px-3 py-2 text-sm hover:bg-slate-50"
              @click="router.push(`/customers/${customerId}/edit`)"
            >
              <Pencil class="h-4 w-4" />
              Edit
            </button>
            <ToggleSwitch
              :model-value="isActive"
              :disabled="toggleMutation.isPending.value"
              label="Toggle customer status"
              @update:model-value="toggleMutation.mutate()"
            />
          </PermissionGate>
          <PermissionGate module="customers" action="delete">
            <button
              type="button"
              class="inline-flex items-center gap-1 rounded-md border border-red-200 px-3 py-2 text-sm text-red-700 hover:bg-red-50"
              @click="deleteOpen = true"
            >
              <Trash2 class="h-4 w-4" />
              Delete
            </button>
          </PermissionGate>
        </div>
      </template>
    </PageHeader>

    <div v-if="customerQuery.isLoading.value" class="rounded-lg border bg-white p-8 text-center text-sm text-slate-500">
      Loading customer…
    </div>

    <template v-else-if="customer">
      <div class="mb-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-lg border bg-white p-6 shadow-card">
          <h2 class="mb-4 text-base font-semibold text-slate-900">Contact Details</h2>
          <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div>
              <dt class="text-slate-500">Primary Email</dt>
              <dd class="font-medium text-slate-900">{{ customer.email || '—' }}</dd>
            </div>
            <div>
              <dt class="text-slate-500">Secondary Email</dt>
              <dd class="font-medium text-slate-900">{{ customer.secondary_email || '—' }}</dd>
            </div>
            <div>
              <dt class="text-slate-500">Primary Contact</dt>
              <dd class="font-medium text-slate-900">{{ customer.contact_number || '—' }}</dd>
            </div>
            <div>
              <dt class="text-slate-500">Secondary Contact</dt>
              <dd class="font-medium text-slate-900">{{ customer.secondary_contact || '—' }}</dd>
            </div>
            <div>
              <dt class="text-slate-500">TIN Number</dt>
              <dd class="font-medium text-slate-900">{{ customer.tin_number || '—' }}</dd>
            </div>
            <div>
              <dt class="text-slate-500">Created</dt>
              <dd class="font-medium text-slate-900">{{ formatDate(customer.created_at) }}</dd>
            </div>
          </dl>
        </div>

        <div class="rounded-lg border bg-white p-6 shadow-card">
          <h2 class="mb-4 text-base font-semibold text-slate-900">Business Stats</h2>
          <div v-if="statsQuery.isLoading.value" class="text-sm text-slate-500">Loading stats…</div>
          <template v-else-if="stats">
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
              <div>
                <dt class="text-slate-500">Total Quotations</dt>
                <dd class="text-lg font-semibold text-slate-900">{{ stats.total_quotations }}</dd>
              </div>
              <div>
                <dt class="text-slate-500">Total Value</dt>
                <dd class="text-lg font-semibold text-slate-900" :title="formatCurrency(stats.total_value)">
                  {{ abbreviateCurrency(stats.total_value) }}
                </dd>
              </div>
              <div>
                <dt class="text-slate-500">Accepted</dt>
                <dd class="text-lg font-semibold text-emerald-600">{{ stats.accepted_count }}</dd>
              </div>
              <div>
                <dt class="text-slate-500">Last Quotation</dt>
                <dd class="font-medium text-slate-900">
                  {{ stats.last_quotation_date ? formatDate(stats.last_quotation_date) : '—' }}
                </dd>
              </div>
            </dl>
            <div v-if="stats.by_status.length" class="mt-4 border-t pt-4">
              <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">By Status</p>
              <div class="flex flex-wrap gap-2">
                <StatusBadge
                  v-for="item in stats.by_status"
                  :key="item.status_name"
                  :label="`${item.status_name} (${item.count})`"
                  color="#64748b"
                  size="sm"
                />
              </div>
            </div>
          </template>
        </div>
      </div>

      <div class="mb-6 rounded-lg border bg-white p-6 shadow-card">
        <h2 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-900">
          <MapPin class="h-4 w-4" />
          Addresses
        </h2>

        <p v-if="!addressGroups.length" class="text-sm text-slate-500">No addresses on file.</p>

        <Tabs v-else v-model="currentAddressTab" :default-value="defaultAddressTab">
          <TabsList class="flex-wrap">
            <TabsTrigger
              v-for="group in addressGroups"
              :key="group.key"
              :value="group.key"
            >
              {{ group.label }}
            </TabsTrigger>
          </TabsList>

          <TabsContent
            v-for="group in addressGroups"
            :key="group.key"
            :value="group.key"
          >
            <div class="space-y-3">
              <article
                v-for="address in group.addresses"
                :key="address.id"
                class="rounded-lg border border-slate-200 p-4 text-sm"
              >
                <p class="font-medium text-slate-900">{{ formatAddress(address) }}</p>
              </article>
            </div>
          </TabsContent>
        </Tabs>
      </div>

      <div class="rounded-lg border bg-white p-6 shadow-card">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-900">Recent Quotations</h2>
          <RouterLink
            :to="`/quotations?customer_id=${customerId}`"
            class="inline-flex items-center gap-1 text-sm font-medium text-brand-blue hover:underline"
          >
            View All
            <ExternalLink class="h-3.5 w-3.5" />
          </RouterLink>
        </div>

        <div v-if="quotationsQuery.isLoading.value" class="text-sm text-slate-500">Loading quotations…</div>

        <p v-else-if="!recentQuotations.length" class="text-sm text-slate-500">No quotations yet.</p>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="border-b text-left text-slate-500">
                <th class="pb-2 pr-4 font-medium">Number</th>
                <th class="pb-2 pr-4 font-medium">Date</th>
                <th class="pb-2 pr-4 font-medium">Status</th>
                <th class="pb-2 font-medium text-right">Amount</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="quotation in recentQuotations"
                :key="quotation.id"
                class="border-b border-slate-100 last:border-0"
              >
                <td class="py-3 pr-4 font-medium text-slate-900">{{ quotation.quotation_number }}</td>
                <td class="py-3 pr-4 text-slate-600">{{ formatDate(quotation.quotation_date) }}</td>
                <td class="py-3 pr-4">
                  <StatusBadge
                    v-if="quotation.status"
                    :label="quotation.status.name"
                    :color="quotation.status.color"
                    size="sm"
                  />
                  <span v-else class="text-slate-400">—</span>
                </td>
                <td class="py-3 text-right font-medium text-slate-900">{{ formatCurrency(quotation.total_amount) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <ConfirmDialog
      :open="deleteOpen"
      title="Delete customer?"
      :description="deleteDescription"
      confirm-label="Delete"
      confirm-variant="destructive"
      :require-type="stats?.total_quotations ? undefined : 'DELETE'"
      @cancel="deleteOpen = false"
      @confirm="deleteMutation.mutate()"
    />
  </section>
</template>
