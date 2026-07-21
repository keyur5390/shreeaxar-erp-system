<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { ArrowRight, Copy, Download, Loader2, Mail, Pencil, Trash2 } from 'lucide-vue-next'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ExpiryBadge from '@/components/ui/ExpiryBadge.vue'
import PermissionGate from '@/components/ui/PermissionGate.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import VTooltip from '@/components/ui/VTooltip.vue'
import QuotationEmailModal from '@/components/quotations/QuotationEmailModal.vue'
import ChangeStatusModal from '@/components/quotations/ChangeStatusModal.vue'
import { quotationsService } from '@/services/quotations.service'
import type { QuotationEmailPayload } from '@/services/quotations.service'
import { useToast } from '@/composables/useToast'
import { formatCurrency, formatDate, formatDateTime } from '@/utils/formatters'
import { amountInWords } from '@/utils/amountInWords'
import { isTerminalQuotationStatus } from '@/utils/quotationStatuses'
import type { QuotationItemDetail, QuotationStatusMaster } from '@/types'

const route = useRoute()
const router = useRouter()
const queryClient = useQueryClient()
const { toast } = useToast()

const quotationId = computed(() => String(route.params.id))
const deleteOpen = ref(false)
const emailModalOpen = ref(false)
const changeStatusOpen = ref(false)
const selectedStatus = ref<QuotationStatusMaster | null>(null)
const statusDropdownValue = ref('')
const emailError = ref<string | null>(null)
const statusError = ref<string | null>(null)

const quotationQuery = useQuery({
  queryKey: computed(() => ['quotation', quotationId.value]),
  queryFn: () => quotationsService.get(quotationId.value),
})

const allowedStatusesQuery = useQuery({
  queryKey: computed(() => ['quotation', quotationId.value, 'allowed-statuses']),
  queryFn: () => quotationsService.getAllowedStatuses(quotationId.value),
})

const quotation = computed(() => quotationQuery.data.value)
const quotationCurrency = computed(() => quotation.value?.currency ?? quotation.value?.currency_snapshot ?? null)
const totalInWords = computed(() => {
  if (!quotation.value || !quotationCurrency.value) return ''
  return amountInWords(
    quotation.value.total_amount,
    quotationCurrency.value.code,
    quotationCurrency.value.decimal_places ?? 0,
  )
})
const allowedStatuses = computed(() => allowedStatusesQuery.data.value ?? [])

const canDelete = computed(() => quotation.value?.status?.name === 'Drafted')
const isTerminal = computed(() => isTerminalQuotationStatus(quotation.value?.status?.name))

const expiryAlert = computed(() => {
  if (!quotation.value?.expiry_status) return null
  if (quotation.value.expiry_status === 'expired') {
    return {
      tone: 'red' as const,
      message: `This quotation expired on ${formatDate(quotation.value.expiry_date)}.`,
    }
  }
  if (quotation.value.expiry_status === 'expiring_soon') {
    return {
      tone: 'orange' as const,
      message: `This quotation expires on ${formatDate(quotation.value.expiry_date)}.`,
    }
  }
  return null
})

const deleteDescription = computed(() =>
  `Permanently delete quotation ${quotation.value?.quotation_number}? This action cannot be undone.`,
)

const statusMutation = useMutation({
  mutationFn: (payload: { statusId: string; note: string | null }) =>
    quotationsService.updateStatus(quotationId.value, payload.statusId, payload.note),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['quotation', quotationId.value] })
    queryClient.invalidateQueries({ queryKey: ['quotations'] })
    toast('Quotation status updated successfully.', 'success')
    changeStatusOpen.value = false
    selectedStatus.value = null
    statusDropdownValue.value = ''
    statusError.value = null
  },
  onError: (error: unknown) => {
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to update status.')
    statusError.value = message
  },
})

const duplicateMutation = useMutation({
  mutationFn: () => quotationsService.duplicate(quotationId.value),
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

const deleteMutation = useMutation({
  mutationFn: () => quotationsService.remove(quotationId.value),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['quotations'] })
    toast('Quotation deleted successfully.', 'success')
    router.push('/quotations')
  },
  onError: (error: unknown) => {
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to delete quotation.')
    toast(message, 'error')
    deleteOpen.value = false
  },
})

const emailMutation = useMutation({
  mutationFn: (payload: QuotationEmailPayload) => quotationsService.sendEmail(quotationId.value, payload),
  onSuccess: (result) => {
    emailError.value = null
    toast('Quotation email sent successfully.', 'success')
    emailModalOpen.value = false
    if (result.status_changed) {
      queryClient.invalidateQueries({ queryKey: ['quotation', quotationId.value] })
      queryClient.invalidateQueries({ queryKey: ['quotations'] })
    }
  },
  onError: (error: unknown) => {
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to send email.')
    emailError.value = message
  },
})

const pdfMutation = useMutation({
  mutationFn: () => quotationsService.downloadPdf(quotationId.value, quotation.value?.quotation_number),
  onSuccess: () => toast('PDF downloaded successfully.', 'success'),
  onError: (error: unknown) => {
    toast(error instanceof Error ? error.message : 'Unable to download PDF.', 'error')
  },
})

function onStatusDropdownChange(event: Event): void {
  const value = (event.target as HTMLSelectElement).value
  statusDropdownValue.value = value
  if (!value) return

  const status = allowedStatuses.value.find((item) => item.id === value) ?? null
  selectedStatus.value = status
  statusError.value = null
  changeStatusOpen.value = true
}

function submitStatusChange(note: string | null): void {
  if (!selectedStatus.value) return
  statusMutation.mutate({ statusId: selectedStatus.value.id, note })
}

function itemImage(item: QuotationItemDetail): string | null {
  return item.image_url ?? item.product?.primary_image_url ?? null
}

function productLink(item: QuotationItemDetail): string | null {
  if (!item.product_id) return null
  return `/products/${item.product_id}`
}
</script>

<template>
  <section class="relative">
    <div
      v-if="pdfMutation.isPending.value"
      class="fixed inset-0 z-40 flex items-center justify-center bg-slate-950/20"
    >
      <div class="flex items-center gap-3 rounded-lg bg-white px-5 py-4 shadow-card">
        <Loader2 class="h-5 w-5 animate-spin text-brand-blue" />
        <span class="text-sm font-medium text-slate-700">Generating PDF…</span>
      </div>
    </div>

    <nav class="mb-4 flex flex-wrap items-center gap-2 text-sm text-slate-500">
      <router-link to="/quotations" class="hover:text-brand-blue">Quotations</router-link>
      <span>/</span>
      <span>{{ quotation?.quotation_number || 'Detail' }}</span>
    </nav>

    <div
      v-if="expiryAlert"
      class="mb-4 rounded-lg border px-4 py-3 text-sm font-medium"
      :class="expiryAlert.tone === 'red'
        ? 'border-red-200 bg-red-50 text-red-800'
        : 'border-amber-200 bg-amber-50 text-amber-800'"
    >
      {{ expiryAlert.message }}
    </div>

    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div class="flex flex-wrap items-center gap-3">
        <h1 class="font-mono text-2xl font-bold tracking-tight text-slate-950">
          {{ quotation?.quotation_number || '—' }}
        </h1>
        <StatusBadge
          v-if="quotation?.status"
          :label="quotation.status.name"
          :color="quotation.status.color"
        />
        <span
          v-if="quotation"
          class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700"
        >
          Rev. {{ quotation.revision_number }}
        </span>
        <ExpiryBadge
          v-if="quotation"
          :expiry-date="quotation.expiry_date"
          :expiry-status="quotation.expiry_status"
        />
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <PermissionGate module="quotations" action="edit">
          <VTooltip
            v-if="isTerminal"
            text="Cannot edit a terminal quotation. Duplicate it instead."
          >
            <span class="inline-flex">
              <button
                type="button"
                class="inline-flex cursor-not-allowed items-center gap-1 rounded-md border px-3 py-2 text-sm text-slate-300"
                disabled
              >
                <Pencil class="h-4 w-4" />
                Edit
              </button>
            </span>
          </VTooltip>
          <button
            v-else
            type="button"
            class="inline-flex items-center gap-1 rounded-md border px-3 py-2 text-sm hover:bg-slate-50"
            @click="router.push(`/quotations/${quotationId}/edit`)"
          >
            <Pencil class="h-4 w-4" />
            Edit
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-1 rounded-md border px-3 py-2 text-sm hover:bg-slate-50"
            :disabled="duplicateMutation.isPending.value"
            @click="duplicateMutation.mutate()"
          >
            <Copy class="h-4 w-4" />
            Duplicate
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-1 rounded-md border px-3 py-2 text-sm hover:bg-slate-50 disabled:opacity-50"
            :disabled="emailMutation.isPending.value"
            @click="emailModalOpen = true"
          >
            <Mail class="h-4 w-4" />
            Email
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-1 rounded-md border px-3 py-2 text-sm hover:bg-slate-50 disabled:opacity-50"
            :disabled="pdfMutation.isPending.value"
            @click="pdfMutation.mutate()"
          >
            <Download class="h-4 w-4" />
            Download PDF
          </button>
          <label
            v-if="allowedStatuses.length"
            class="inline-flex items-center gap-1 rounded-md border px-3 py-2 text-sm hover:bg-slate-50"
          >
            <span class="text-slate-600">Change Status</span>
            <select
              :value="statusDropdownValue"
              class="rounded border-0 bg-transparent py-0 pl-1 pr-6 text-sm font-medium text-slate-900 focus:ring-0"
              @change="onStatusDropdownChange"
            >
              <option value="">Select…</option>
              <option v-for="status in allowedStatuses" :key="status.id" :value="status.id">
                {{ status.name }}
              </option>
            </select>
          </label>
        </PermissionGate>
        <PermissionGate module="quotations" action="delete">
          <button
            v-if="canDelete"
            type="button"
            class="inline-flex items-center gap-1 rounded-md border border-red-200 px-3 py-2 text-sm text-red-700 hover:bg-red-50"
            @click="deleteOpen = true"
          >
            <Trash2 class="h-4 w-4" />
            Delete
          </button>
        </PermissionGate>
      </div>
    </div>

    <div v-if="quotationQuery.isLoading.value" class="rounded-lg border bg-white p-8 text-center text-sm text-slate-500">
      Loading quotation…
    </div>

    <template v-else-if="quotation">
      <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <div class="rounded-lg border bg-white p-6 shadow-card lg:col-span-2">
          <h2 class="mb-4 text-base font-semibold text-slate-900">Information</h2>
          <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div>
              <dt class="text-slate-500">Customer</dt>
              <dd class="font-medium text-slate-900">
                <router-link
                  v-if="quotation.customer?.id"
                  :to="`/customers/${quotation.customer.id}`"
                  class="text-brand-blue hover:underline"
                >
                  {{ quotation.customer.company_name }}
                </router-link>
                <span v-else>—</span>
              </dd>
            </div>
            <div>
              <dt class="text-slate-500">TIN</dt>
              <dd>{{ quotation.customer?.tin_number || '—' }}</dd>
            </div>
            <div>
              <dt class="text-slate-500">Quotation Date</dt>
              <dd>{{ formatDate(quotation.quotation_date) }}</dd>
            </div>
            <div>
              <dt class="text-slate-500">Expiry Date</dt>
              <dd>{{ formatDate(quotation.expiry_date) }}</dd>
            </div>
            <div>
              <dt class="text-slate-500">Authorized By</dt>
              <dd>
                {{ quotation.authorized_by?.first_name }}
                {{ quotation.authorized_by?.last_name }}
              </dd>
            </div>
            <div v-if="quotation.bank_snapshot">
              <dt class="text-slate-500">Bank</dt>
              <dd>
                {{ quotation.bank_snapshot.bank_name || '—' }}
                <span v-if="quotation.bank_snapshot.account_number">
                  — {{ quotation.bank_snapshot.account_number }}
                </span>
              </dd>
            </div>
          </dl>
        </div>

        <div class="rounded-lg border bg-white p-6 shadow-card">
          <h2 class="mb-4 text-base font-semibold text-slate-900">Pricing</h2>
          <dl class="space-y-2 text-sm">
            <div class="flex justify-between">
              <dt class="text-slate-500">Sub Total</dt>
              <dd>{{ formatCurrency(quotation.sub_total, quotationCurrency) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-500">Discount Amount</dt>
              <dd>{{ formatCurrency(quotation.discount_amount, quotationCurrency) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-500">
                <template v-if="quotation.exclude_vat">VAT</template>
                <template v-else>VAT ({{ quotation.vat_rate }}%)</template>
              </dt>
              <dd>
                <template v-if="quotation.exclude_vat">Excluded</template>
                <template v-else>{{ formatCurrency(quotation.vat_amount, quotationCurrency) }}</template>
              </dd>
            </div>
            <div class="flex justify-between border-t pt-2 text-base font-semibold">
              <dt>TOTAL</dt>
              <dd>{{ formatCurrency(quotation.total_amount, quotationCurrency) }}</dd>
            </div>
          </dl>
          <p v-if="totalInWords" class="mt-3 border-t pt-3 text-xs leading-relaxed text-slate-600">
            <span class="font-medium text-slate-700">Amount in words:</span>
            {{ totalInWords }} Only
          </p>
        </div>
      </div>

      <div class="mb-6 overflow-hidden rounded-lg border bg-white shadow-card">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">#</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Product</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Description</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Unit</th>
              <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-500">Rate</th>
              <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-500">Qty</th>
              <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-500">Disc%</th>
              <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-500">Line Total</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="(item, index) in quotation.items" :key="item.id">
              <td class="px-4 py-3 text-sm text-slate-500">{{ index + 1 }}</td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                  <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded border bg-slate-50 text-[10px] font-medium text-slate-400"
                  >
                    <img
                      v-if="itemImage(item)"
                      :src="itemImage(item)!"
                      :alt="item.product?.title || item.description"
                      class="h-full w-full object-cover"
                    />
                    <span v-else-if="!item.product_id">[Deleted]</span>
                  </div>
                  <div class="min-w-0 text-sm">
                    <router-link
                      v-if="productLink(item)"
                      :to="productLink(item)!"
                      class="font-medium text-brand-blue hover:underline"
                    >
                      {{ item.product?.title || item.description }}
                    </router-link>
                    <p v-else class="font-medium text-slate-900">
                      {{ item.product_id ? (item.product?.title || item.description) : '[Product Deleted]' }}
                    </p>
                  </div>
                </div>
              </td>
              <td class="px-4 py-3 text-sm text-slate-600">{{ item.description }}</td>
              <td class="px-4 py-3 text-sm">{{ item.unit }}</td>
              <td class="px-4 py-3 text-right text-sm">{{ formatCurrency(item.rate, quotationCurrency) }}</td>
              <td class="px-4 py-3 text-right text-sm">{{ item.quantity }}</td>
              <td class="px-4 py-3 text-right text-sm">{{ item.discount_rate }}%</td>
              <td class="px-4 py-3 text-right text-sm font-medium">{{ formatCurrency(item.line_total, quotationCurrency) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <div class="rounded-lg border bg-white p-6 shadow-card">
          <h3 class="mb-2 text-sm font-semibold text-slate-900">Terms & Conditions</h3>
          <p class="whitespace-pre-wrap text-sm text-slate-600">{{ quotation.terms_conditions || '—' }}</p>
        </div>
        <div class="rounded-lg border bg-white p-6 shadow-card">
          <h3 class="mb-2 text-sm font-semibold text-slate-900">Notes</h3>
          <p class="whitespace-pre-wrap text-sm text-slate-600">{{ quotation.notes || '—' }}</p>
        </div>
        <div class="rounded-lg border bg-white p-6 shadow-card">
          <h3 class="mb-2 text-sm font-semibold text-slate-900">Bank Details</h3>
          <dl v-if="quotation.bank_snapshot" class="space-y-2 text-sm">
            <div><dt class="text-slate-500">Bank</dt><dd class="font-medium">{{ quotation.bank_snapshot.bank_name || '—' }}</dd></div>
            <div><dt class="text-slate-500">Account Holder</dt><dd>{{ quotation.bank_snapshot.account_holder_name || '—' }}</dd></div>
            <div><dt class="text-slate-500">Account Number</dt><dd>{{ quotation.bank_snapshot.account_number || '—' }}</dd></div>
            <div v-if="quotation.bank_snapshot.branch_name"><dt class="text-slate-500">Branch</dt><dd>{{ quotation.bank_snapshot.branch_name }}</dd></div>
            <div v-if="quotation.bank_snapshot.swift_code"><dt class="text-slate-500">SWIFT</dt><dd>{{ quotation.bank_snapshot.swift_code }}</dd></div>
          </dl>
          <p v-else class="text-sm text-slate-500">No bank details recorded.</p>
        </div>
      </div>

      <div class="rounded-lg border bg-white p-6 shadow-card">
        <h2 class="mb-4 text-base font-semibold text-slate-900">Status History</h2>
        <ol v-if="quotation.status_history.length" class="space-y-4">
          <li
            v-for="entry in quotation.status_history"
            :key="entry.id"
            class="flex flex-wrap items-start gap-2 text-sm"
          >
            <span
              class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full"
              :style="{ backgroundColor: entry.to_status?.color || '#94a3b8' }"
            />
            <div class="min-w-0 flex-1">
              <p class="font-medium text-slate-900">
                <template v-if="entry.from_status">
                  {{ entry.from_status.name }}
                  <ArrowRight class="mx-1 inline h-3.5 w-3.5 text-slate-400" />
                  {{ entry.to_status?.name }}
                </template>
                <template v-else>
                  {{ entry.to_status?.name }}
                </template>
              </p>
              <p class="text-xs text-slate-500">
                by {{ entry.changed_by?.first_name }} {{ entry.changed_by?.last_name }}
                · {{ formatDateTime(entry.created_at) }}
              </p>
              <p v-if="entry.note" class="mt-1 text-sm text-slate-600">{{ entry.note }}</p>
            </div>
          </li>
        </ol>
        <p v-else class="text-sm text-slate-500">No status history recorded.</p>
      </div>
    </template>

    <ConfirmDialog
      :open="deleteOpen"
      title="Delete quotation?"
      :description="deleteDescription"
      confirm-label="Delete"
      confirm-variant="destructive"
      require-type="DELETE"
      @cancel="deleteOpen = false"
      @confirm="deleteMutation.mutate()"
    />

    <QuotationEmailModal
      :open="emailModalOpen"
      :quotation-number="quotation?.quotation_number ?? ''"
      :default-to="quotation?.customer?.email"
      :is-sending="emailMutation.isPending.value"
      :error="emailError"
      @close="emailModalOpen = false; emailError = null"
      @send="emailMutation.mutate"
    />

    <ChangeStatusModal
      :open="changeStatusOpen"
      :status="selectedStatus"
      :is-submitting="statusMutation.isPending.value"
      :error="statusError"
      @close="changeStatusOpen = false; statusDropdownValue = ''; selectedStatus = null; statusError = null"
      @submit="submitStatusChange"
    />
  </section>
</template>
