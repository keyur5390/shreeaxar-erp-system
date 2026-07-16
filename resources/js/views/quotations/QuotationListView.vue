<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import {
  Copy,
  Download,
  Eye,
  Mail,
  Pencil,
  Plus,
  X,
} from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import DataTable from '@/components/ui/DataTable.vue'
import PermissionGate from '@/components/ui/PermissionGate.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ExpiryBadge from '@/components/ui/ExpiryBadge.vue'
import VTooltip from '@/components/ui/VTooltip.vue'
import QuotationEmailModal from '@/components/quotations/QuotationEmailModal.vue'
import { useQuotationList } from '@/composables/useQuotationList'
import { quotationsService } from '@/services/quotations.service'
import type { QuotationEmailPayload } from '@/services/quotations.service'
import { useToast } from '@/composables/useToast'
import { formatCurrency, formatDate } from '@/utils/formatters'
import { isTerminalQuotationStatus } from '@/utils/quotationStatuses'
import type { QuotationListItem } from '@/types'

const props = defineProps<{
  statusFilter?: string
}>()

const router = useRouter()
const queryClient = useQueryClient()
const { toast } = useToast()

const {
  showStatusFilter,
  pageTitle,
  searchInput,
  statusIdFilter,
  authorizedById,
  myQuotations,
  dateFrom,
  dateTo,
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
} = useQuotationList(props.statusFilter)

const columns = [
  { label: 'QT No', key: 'number' },
  { label: 'Customer Name', key: 'customer' },
  { label: 'Quotation Date', key: 'date' },
  { label: 'Expiry', key: 'expiry' },
  { label: 'Total', key: 'amount' },
  { label: 'Status', key: 'status' },
  { label: 'Authorized By', key: 'authorized' },
  { label: 'Actions', key: 'actions' },
] as const

function goToQuotation(row: QuotationListItem) {
  router.push(`/quotations/${row.id}`)
}

const emailModalOpen = ref(false)
const emailTarget = ref<{ id: string; quotationNumber: string; defaultTo?: string | null } | null>(null)

function openEmailModal(row: QuotationListItem) {
  emailTarget.value = {
    id: row.id,
    quotationNumber: row.quotation_number,
    defaultTo: row.customer?.email ?? null,
  }
  emailModalOpen.value = true
}

function closeEmailModal() {
  emailModalOpen.value = false
  emailTarget.value = null
}

const emailMutation = useMutation({
  mutationFn: ({ id, payload }: { id: string; payload: QuotationEmailPayload }) =>
    quotationsService.sendEmail(id, payload),
  onSuccess: (result) => {
    toast('Quotation email sent successfully.', 'success')
    closeEmailModal()
    if (result.status_changed) {
      queryClient.invalidateQueries({ queryKey: ['quotations'] })
    }
  },
  onError: (error: unknown) => {
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to send email.')
    toast(message, 'error')
  },
})

function sendEmail(payload: QuotationEmailPayload) {
  if (!emailTarget.value) return
  emailMutation.mutate({ id: emailTarget.value.id, payload })
}

const pdfMutation = useMutation({
  mutationFn: (id: string) => quotationsService.downloadPdf(id),
  onError: (error: unknown) => {
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to download PDF.')
    toast(message, 'error')
  },
})

const duplicateMutation = useMutation({
  mutationFn: (id: string) => quotationsService.duplicate(id),
  onSuccess: (result) => {
    queryClient.invalidateQueries({ queryKey: ['quotations'] })
    if (result.warnings.length > 0) {
      result.warnings.forEach((warning) => toast(warning, 'error'))
    }
    router.push(`/quotations/${result.quotation.id}/edit`)
  },
  onError: (error: unknown) => {
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to duplicate quotation.')
    toast(message, 'error')
  },
})
</script>

<template>
  <section>
    <PageHeader
      :title="pageTitle"
      subtitle="Manage furniture quotations, track status, and monitor expiry."
      :breadcrumb="[{ label: 'Quotations', href: '/quotations' }, ...(statusFilter ? [{ label: pageTitle }] : [])]"
    >
      <template #action>
        <PermissionGate module="quotations" action="create">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg bg-brand-blue px-4 py-2 text-sm font-semibold text-white hover:bg-brand-blue/90"
            @click="router.push('/quotations/new')"
          >
            <Plus class="h-4 w-4" />
            Create Quotation
          </button>
        </PermissionGate>
      </template>
    </PageHeader>

    <div v-if="loadError" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      {{ loadError }}
    </div>

    <DataTable
      :columns="[...columns]"
      :data="quotations"
      :is-loading="quotationsQuery.isLoading.value"
      :skeleton-rows="8"
      :pagination="tablePagination"
      @page-change="setPage"
      @row-click="goToQuotation"
    >
      <template #topBar>
        <div class="space-y-3">
          <div class="rounded-lg border bg-slate-50 px-4 py-3 text-sm text-slate-700">
            Showing <span class="font-semibold">{{ summaryCount }}</span> quotations
            <span class="mx-2 text-slate-300">|</span>
            Total Value: <span class="font-semibold">{{ formatCurrency(summaryTotalValue) }}</span>
          </div>

          <div class="rounded-lg border bg-white p-4 shadow-card">
            <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
              <label class="block text-sm lg:col-span-2">
                <span class="mb-1 block font-medium text-slate-700">Search</span>
                <input
                  :value="searchInput"
                  type="search"
                  placeholder="Quotation number or customer…"
                  class="w-full rounded-md border px-3 py-2"
                  @input="updateFilter('search', ($event.target as HTMLInputElement).value)"
                />
              </label>

              <label v-if="showStatusFilter" class="block text-sm">
                <span class="mb-1 block font-medium text-slate-700">Status</span>
                <select
                  :value="statusIdFilter"
                  class="w-full rounded-md border px-3 py-2"
                  @change="updateFilter('statusId', ($event.target as HTMLSelectElement).value)"
                >
                  <option value="">All statuses</option>
                  <option v-for="status in statuses" :key="status.id" :value="status.id">
                    {{ status.name }}
                  </option>
                </select>
              </label>

              <label class="block text-sm">
                <span class="mb-1 block font-medium text-slate-700">Authorized By</span>
                <select
                  :value="authorizedById"
                  class="w-full rounded-md border px-3 py-2"
                  :disabled="myQuotations"
                  @change="updateFilter('authorizedById', ($event.target as HTMLSelectElement).value)"
                >
                  <option value="">All users</option>
                  <option v-for="user in users" :key="user.id" :value="user.id">
                    {{ user.first_name }} {{ user.last_name }}
                  </option>
                </select>
              </label>

              <label class="flex items-end gap-2 text-sm">
                <input
                  :checked="myQuotations"
                  type="checkbox"
                  class="rounded border-slate-300"
                  @change="updateFilter('myQuotations', ($event.target as HTMLInputElement).checked)"
                />
                <span class="font-medium text-slate-700">My Quotations</span>
              </label>

              <label class="block text-sm">
                <span class="mb-1 block font-medium text-slate-700">From</span>
                <input
                  :value="dateFrom"
                  type="date"
                  class="w-full rounded-md border px-3 py-2"
                  @change="updateFilter('dateFrom', ($event.target as HTMLInputElement).value)"
                />
              </label>

              <label class="block text-sm">
                <span class="mb-1 block font-medium text-slate-700">To</span>
                <input
                  :value="dateTo"
                  type="date"
                  class="w-full rounded-md border px-3 py-2"
                  @change="updateFilter('dateTo', ($event.target as HTMLInputElement).value)"
                />
              </label>

              <div class="flex items-end">
                <button
                  v-if="hasActiveFilters"
                  type="button"
                  class="inline-flex items-center gap-1 rounded-md border px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                  @click="clearFilters"
                >
                  <X class="h-4 w-4" />
                  Clear All
                </button>
              </div>
            </div>
          </div>
        </div>
      </template>

      <template v-if="!quotationsQuery.isLoading.value && !quotations.length" #emptyState>
        <EmptyState
          :title="hasActiveFilters ? 'No quotations match your filters' : 'No quotations yet'"
          :description="hasActiveFilters ? 'Try adjusting your search or filter criteria.' : 'Create your first quotation to get started.'"
        >
          <template v-if="!hasActiveFilters" #action>
            <PermissionGate module="quotations" action="create">
              <button
                type="button"
                class="rounded-lg bg-brand-blue px-4 py-2 text-sm font-semibold text-white hover:bg-brand-blue/90"
                @click="router.push('/quotations/new')"
              >
                Create Quotation
              </button>
            </PermissionGate>
          </template>
        </EmptyState>
      </template>

      <template #cell-number="{ row }">
        <RouterLink
          :to="`/quotations/${(row as QuotationListItem).id}`"
          class="block truncate font-mono text-sm font-semibold text-brand-blue hover:underline"
          @click.stop
        >
          {{ (row as QuotationListItem).quotation_number }}
        </RouterLink>
      </template>

      <template #cell-customer="{ row }">
        <span class="block truncate">{{ (row as QuotationListItem).customer?.company_name || '—' }}</span>
      </template>

      <template #cell-date="{ row }">
        {{ formatDate((row as QuotationListItem).quotation_date) }}
      </template>

      <template #cell-expiry="{ row }">
        <ExpiryBadge
          :expiry-date="(row as QuotationListItem).expiry_date"
          :expiry-status="(row as QuotationListItem).expiry_status ?? undefined"
        />
      </template>

      <template #cell-amount="{ row }">
        <span class="block text-right font-medium">{{ formatCurrency((row as QuotationListItem).total_amount) }}</span>
      </template>

      <template #cell-status="{ row }">
        <StatusBadge
          v-if="(row as QuotationListItem).status"
          :label="(row as QuotationListItem).status!.name"
          :color="(row as QuotationListItem).status!.color"
          size="sm"
        />
      </template>

      <template #cell-authorized="{ row }">
        <span v-if="(row as QuotationListItem).authorized_by">
          {{ (row as QuotationListItem).authorized_by!.first_name }}
          {{ (row as QuotationListItem).authorized_by!.last_name }}
        </span>
        <span v-else>—</span>
      </template>

      <template #cell-actions="{ row }">
        <div class="flex items-center gap-1" @click.stop>
          <RouterLink
            :to="`/quotations/${(row as QuotationListItem).id}`"
            class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
            title="View"
          >
            <Eye class="h-4 w-4" />
          </RouterLink>

          <PermissionGate module="quotations" action="edit">
            <VTooltip
              v-if="isTerminalQuotationStatus((row as QuotationListItem).status?.name)"
              text="Cannot edit a terminal quotation. Duplicate it instead."
            >
              <span class="inline-flex">
                <button
                  type="button"
                  class="cursor-not-allowed rounded p-1.5 text-slate-300"
                  disabled
                >
                  <Pencil class="h-4 w-4" />
                </button>
              </span>
            </VTooltip>
            <RouterLink
              v-else
              :to="`/quotations/${(row as QuotationListItem).id}/edit`"
              class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
              title="Edit"
            >
              <Pencil class="h-4 w-4" />
            </RouterLink>

            <button
              type="button"
              class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-50"
              title="Email"
              :disabled="emailMutation.isPending.value"
              @click="openEmailModal(row as QuotationListItem)"
            >
              <Mail class="h-4 w-4" />
            </button>

            <button
              type="button"
              class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-50"
              title="Download PDF"
              :disabled="pdfMutation.isPending.value"
              @click="pdfMutation.mutate((row as QuotationListItem).id)"
            >
              <Download class="h-4 w-4" />
            </button>

            <button
              type="button"
              class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-50"
              title="Duplicate"
              :disabled="duplicateMutation.isPending.value"
              @click="duplicateMutation.mutate((row as QuotationListItem).id)"
            >
              <Copy class="h-4 w-4" />
            </button>
          </PermissionGate>
        </div>
      </template>
    </DataTable>

    <QuotationEmailModal
      :open="emailModalOpen"
      :quotation-number="emailTarget?.quotationNumber ?? ''"
      :default-to="emailTarget?.defaultTo"
      :is-sending="emailMutation.isPending.value"
      @close="closeEmailModal"
      @send="sendEmail"
    />
  </section>
</template>
