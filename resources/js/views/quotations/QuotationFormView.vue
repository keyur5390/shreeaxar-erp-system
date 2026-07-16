<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useForm, useFieldArray, ErrorMessage } from 'vee-validate'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { useDebounceFn } from '@vueuse/core'
import * as yup from 'yup'
import { Plus, Trash2, ArrowUp, ArrowDown } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import AsyncCombobox from '@/components/ui/AsyncCombobox.vue'
import CurrencyInput from '@/components/ui/CurrencyInput.vue'
import QuickCreateCustomerModal from '@/components/quotations/QuickCreateCustomerModal.vue'
import { quotationsService } from '@/services/quotations.service'
import { quotationStatusesService } from '@/services/quotation-statuses.service'
import { customersService } from '@/services/customers.service'
import { productsService } from '@/services/products.service'
import { bankDetailsService } from '@/services/bank-details.service'
import { taxesService } from '@/services/taxes.service'
import { settingsService } from '@/services/settings.service'
import { useAuthStore } from '@/stores/auth.store'
import { STALE_TIME } from '@/lib/queryTimes'
import { useToast } from '@/composables/useToast'
import { useFormDirtyGuard } from '@/composables/useFormDirtyGuard'
import { ValidationError } from '@/services/api'
import { calculateLineTotal, calculateQuotationTotals } from '@/utils/quotationCalc'
import { formatCurrency } from '@/utils/formatters'
import type { CustomerSearchResult, ProductSearchResult, QuotationItemForm } from '@/types'

const DRAFT_STORAGE_KEY = 'quotation_draft_new'
const DRAFT_MAX_AGE_MS = 24 * 60 * 60 * 1000

const route = useRoute()
const router = useRouter()
const queryClient = useQueryClient()
const authStore = useAuthStore()
const { toast } = useToast()

const quotationId = computed(() => {
  const id = route.params.id
  return typeof id === 'string' && id !== 'new' ? id : undefined
})
const isEdit = computed(() => Boolean(quotationId.value))

const submitError = ref('')
const expiryInlineError = ref('')
const selectedCustomer = ref<CustomerSearchResult | null>(null)
const lastModifiedAt = ref<string | null>(null)
const quickCreateOpen = ref(false)
const concurrencyOpen = ref(false)
const pendingSubmitValues = ref<typeof values | null>(null)
const draftRestoreOpen = ref(false)
const pendingDraft = ref<{
  savedAt: number
  values: Record<string, unknown>
  selectedCustomer: CustomerSearchResult | null
} | null>(null)
const draftSavedAt = ref<string | null>(null)
const saveMode = ref<'draft' | 'quotation'>('quotation')

const rateManuallyEdited = ref(new Set<string>())
const productRates = ref(new Map<string, number>())
const selectedProducts = ref(new Map<string, ProductSearchResult>())

function todayIso(): string {
  return new Date().toISOString().slice(0, 10)
}

function addDaysIso(days: number): string {
  const date = new Date()
  date.setDate(date.getDate() + days)
  return date.toISOString().slice(0, 10)
}

function defaultExpiryDays(): number {
  const fromEnv = import.meta.env.VITE_QUOTATION_DEFAULT_EXPIRY_DAYS
  if (fromEnv) {
    const parsed = Number.parseInt(String(fromEnv), 10)
    if (!Number.isNaN(parsed) && parsed > 0) return parsed
  }
  return 30
}

const emptyItem = (): QuotationItemForm => ({
  product_id: null,
  description: '',
  image_url: null,
  unit: '',
  rate: 0,
  quantity: 1,
  discount_rate: 0,
})

const schema = yup.object({
  customer_id: yup.string().required('Customer is required.'),
  status_id: yup.string().required('Status is required.'),
  quotation_date: yup.string().required('Quotation date is required.'),
  expiry_date: yup.string().required('Expiry date is required.')
    .test('after-quotation', 'Expiry date must be after quotation date.', function afterQuotation(value) {
      const { quotation_date: quotationDate } = this.parent as { quotation_date?: string }
      if (!value || !quotationDate) return true
      return new Date(value) > new Date(quotationDate)
    }),
  authorized_by_id: yup.string().required('Authorized by is required.'),
  bank_detail_id: yup.string().nullable(),
  terms_conditions: yup.string().nullable(),
  notes: yup.string().nullable(),
  items: yup.array().of(
    yup.object({
      product_id: yup.string().nullable(),
      description: yup.string().required('Description is required.').max(500),
      image_url: yup.string().nullable(),
      unit: yup.string().required('Unit is required.').max(50),
      rate: yup.number().min(0).required(),
      quantity: yup.number().integer().min(1).required(),
      discount_rate: yup.number().min(0).max(100).default(0),
    }),
  ).min(1, 'At least one line item is required.'),
})

const {
  handleSubmit,
  resetForm,
  meta,
  values,
  errors,
  setFieldValue,
  defineField,
} = useForm({
  validationSchema: schema,
  initialValues: {
    customer_id: '',
    status_id: '',
    quotation_date: todayIso(),
    expiry_date: addDaysIso(defaultExpiryDays()),
    authorized_by_id: authStore.user?.id ?? '',
    bank_detail_id: '',
    terms_conditions: '',
    notes: '',
    items: [emptyItem()],
  },
})

const [statusId] = defineField('status_id')
const [quotationDate] = defineField('quotation_date')
const [expiryDate] = defineField('expiry_date')
const [bankDetailId] = defineField('bank_detail_id')
const [termsConditions] = defineField('terms_conditions')
const [notes] = defineField('notes')

const { fields: itemFields, push: pushItem, remove: removeItem, move: moveItem, update: updateLineItem } = useFieldArray<QuotationItemForm>('items')
const { allowNavigation } = useFormDirtyGuard(computed(() => meta.value.dirty))

const tinNumber = computed(() => selectedCustomer.value?.tin_number ?? '—')
const isInactiveCustomer = computed(() => selectedCustomer.value && !selectedCustomer.value.is_active)

const minExpiryDate = computed(() => {
  if (!quotationDate.value) return ''
  const date = new Date(quotationDate.value)
  date.setDate(date.getDate() + 1)
  return date.toISOString().split('T')[0]
})

const quotationQuery = useQuery({
  queryKey: computed(() => ['quotations', quotationId.value]),
  queryFn: () => quotationsService.get(quotationId.value!),
  enabled: computed(() => isEdit.value),
})

const statusesQuery = useQuery({
  queryKey: ['quotation-statuses'],
  queryFn: () => quotationStatusesService.list(),
  staleTime: STALE_TIME.masters,
})

const banksQuery = useQuery({
  queryKey: ['bank-details'],
  queryFn: () => bankDetailsService.list(),
  staleTime: STALE_TIME.masters,
})

const taxesQuery = useQuery({
  queryKey: ['taxes'],
  queryFn: () => taxesService.list(),
  staleTime: STALE_TIME.masters,
})

const settingsQuery = useQuery({
  queryKey: ['settings', 'quotation_default_expiry_days'],
  queryFn: () => settingsService.get('quotation_default_expiry_days'),
  enabled: computed(() => !isEdit.value),
})

const statuses = computed(() => statusesQuery.data.value ?? [])
const banks = computed(() => banksQuery.data.value ?? [])
const draftedStatusId = computed(() => statuses.value.find((status) => status.name === 'Drafted')?.id ?? '')

const effectiveVatRate = computed(() => {
  if (isEdit.value && quotationQuery.data.value?.vat_rate !== undefined) {
    return quotationQuery.data.value.vat_rate
  }
  const taxes = taxesQuery.data.value ?? []
  const defaultTax = taxes.find((tax) => tax.is_default) ?? taxes[0]
  return defaultTax?.rate ?? 0
})

const totals = computed(() => calculateQuotationTotals(
  (values.items ?? []).map((item) => ({
    rate: Number(item.rate) || 0,
    qty: Number(item.quantity) || 0,
    discountRate: Number(item.discount_rate) || 0,
  })),
  effectiveVatRate.value,
))

const hasItemErrors = computed(() =>
  Object.keys(errors.value).some((key) => key.startsWith('items')),
)

watch(
  () => settingsQuery.data.value,
  (setting) => {
    if (isEdit.value || !setting?.value) return
    const days = Number.parseInt(setting.value, 10)
    if (!Number.isNaN(days) && days > 0) {
      setFieldValue('expiry_date', addDaysIso(days))
    }
  },
)

watch(
  () => statusesQuery.data.value,
  (statusList) => {
    if (isEdit.value || !statusList?.length || values.status_id) return
    const drafted = statusList.find((status) => status.is_default || status.name === 'Drafted')
    if (drafted) setFieldValue('status_id', drafted.id)
  },
)

watch(
  () => banksQuery.data.value,
  (bankList) => {
    if (isEdit.value || bankDetailId.value || !bankList?.length) return
    const primary = bankList.find((bank) => bank.is_primary)
    if (primary) setFieldValue('bank_detail_id', primary.id)
  },
)

watch(quotationDate, (newDate, oldDate) => {
  if (!newDate || newDate === oldDate) return
  if (expiryDate.value && new Date(expiryDate.value) <= new Date(newDate)) {
    setFieldValue('expiry_date', '')
    expiryInlineError.value = 'Expiry date must be after quotation date. Please select a new expiry date.'
  } else {
    expiryInlineError.value = ''
  }
})

watch(expiryDate, (value) => {
  if (!value || !quotationDate.value) return
  if (new Date(value) > new Date(quotationDate.value)) {
    expiryInlineError.value = ''
  }
})

watch(
  () => quotationQuery.data.value,
  (quotation) => {
    if (!quotation) return
    lastModifiedAt.value = quotation.last_modified_at ?? null
    selectedCustomer.value = quotation.customer ? {
      id: quotation.customer.id,
      company_name: quotation.customer.company_name,
      tin_number: quotation.customer.tin_number ?? null,
      email: quotation.customer.email ?? null,
      contact_number: quotation.customer.contact_number ?? null,
      is_active: quotation.customer.is_active,
    } : null

    const items = quotation.items.length
      ? quotation.items.map((item) => ({
        product_id: item.product_id ?? null,
        description: item.description,
        image_url: item.image_url ?? null,
        unit: item.unit,
        rate: Number(item.rate),
        quantity: Number(item.quantity),
        discount_rate: Number(item.discount_rate),
      }))
      : [emptyItem()]

    resetForm({
      values: {
        customer_id: quotation.customer_id,
        status_id: quotation.status_id,
        quotation_date: quotation.quotation_date,
        expiry_date: quotation.expiry_date,
        authorized_by_id: quotation.authorized_by_id,
        bank_detail_id: quotation.bank_detail_id ?? '',
        terms_conditions: quotation.terms_conditions ?? '',
        notes: quotation.notes ?? '',
        items,
      },
    })

    rateManuallyEdited.value = new Set()
    productRates.value = new Map()
    selectedProducts.value = new Map()
  },
  { immediate: true },
)

async function loadCustomers(query: string, signal: AbortSignal): Promise<CustomerSearchResult[]> {
  if (query.trim().length < 2) return []
  const results = await customersService.search(query.trim(), 20)
  if (signal.aborted) return []
  return results
}

async function loadProducts(query: string, signal: AbortSignal): Promise<ProductSearchResult[]> {
  if (query.trim().length < 2) return []
  const results = await productsService.search(query.trim(), 15)
  if (signal.aborted) return []
  return results
}

function onCustomerSelected(customer: CustomerSearchResult | null) {
  selectedCustomer.value = customer
  setFieldValue('customer_id', customer?.id ?? '')
}

function onQuickCreateSelected(customer: CustomerSearchResult) {
  onCustomerSelected(customer)
}

function updateItemField(index: number, key: keyof QuotationItemForm, value: unknown) {
  updateLineItem(index, {
    ...lineItemAt(index),
    [key]: value,
  })
}

function addLineItem() {
  pushItem(emptyItem())
}

function lineItemAt(index: number): QuotationItemForm {
  return values.items?.[index] ?? itemFields.value[index]?.value ?? emptyItem()
}

function onProductSelected(index: number, fieldKey: string, product: ProductSearchResult | null) {
  if (!product) {
    const nextProducts = new Map(selectedProducts.value)
    const nextRates = new Map(productRates.value)
    const nextEdited = new Set(rateManuallyEdited.value)
    nextProducts.delete(fieldKey)
    nextRates.delete(fieldKey)
    nextEdited.delete(fieldKey)
    selectedProducts.value = nextProducts
    productRates.value = nextRates
    rateManuallyEdited.value = nextEdited
    updateItemField(index, 'product_id', null)
    return
  }

  const nextProducts = new Map(selectedProducts.value)
  const nextRates = new Map(productRates.value)
  const nextEdited = new Set(rateManuallyEdited.value)
  nextProducts.set(fieldKey, product)
  nextRates.set(fieldKey, Number(product.rate))
  nextEdited.delete(fieldKey)
  selectedProducts.value = nextProducts
  productRates.value = nextRates
  rateManuallyEdited.value = nextEdited

  updateLineItem(index, {
    ...lineItemAt(index),
    product_id: product.id,
    description: product.title,
    unit: product.unit?.code ?? product.unit?.name ?? 'pcs',
    rate: Number(product.rate),
    image_url: product.primary_image_url ?? null,
  })
}

function onRateChange(fieldKey: string, index: number, rate: number | null) {
  const nextEdited = new Set(rateManuallyEdited.value)
  nextEdited.add(fieldKey)
  rateManuallyEdited.value = nextEdited
  updateItemField(index, 'rate', rate ?? 0)
}

function lineTotal(item: QuotationItemForm): number {
  return calculateLineTotal(Number(item.rate) || 0, Number(item.quantity) || 0, Number(item.discount_rate) || 0)
}

function showRateBadge(fieldKey: string): boolean {
  return rateManuallyEdited.value.has(fieldKey) && productRates.value.has(fieldKey)
}

function productRateFor(fieldKey: string): number {
  return productRates.value.get(fieldKey) ?? 0
}

function removeLineItem(index: number) {
  const fieldKey = itemFields.value[index]?.key
  if (fieldKey) {
    const nextProducts = new Map(selectedProducts.value)
    const nextRates = new Map(productRates.value)
    const nextEdited = new Set(rateManuallyEdited.value)
    nextProducts.delete(fieldKey)
    nextRates.delete(fieldKey)
    nextEdited.delete(fieldKey)
    selectedProducts.value = nextProducts
    productRates.value = nextRates
    rateManuallyEdited.value = nextEdited
  }
  removeItem(index)
}

function buildPayload(formValues: typeof values, options?: { force?: boolean; asDraft?: boolean }) {
  const statusId = options?.asDraft && draftedStatusId.value
    ? draftedStatusId.value
    : formValues.status_id

  return {
    customer_id: formValues.customer_id,
    status_id: statusId,
    quotation_date: formValues.quotation_date,
    expiry_date: formValues.expiry_date,
    authorized_by_id: formValues.authorized_by_id,
    bank_detail_id: formValues.bank_detail_id || null,
    terms_conditions: formValues.terms_conditions || null,
    notes: formValues.notes || null,
    items: (formValues.items ?? []).map((item) => ({
      product_id: item.product_id || null,
      description: item.description,
      image_url: item.image_url || null,
      unit: item.unit,
      rate: Number(item.rate),
      quantity: Number(item.quantity),
      discount_rate: Number(item.discount_rate) || 0,
    })),
    ...(isEdit.value && lastModifiedAt.value && !options?.force
      ? { last_modified_at: lastModifiedAt.value }
      : {}),
  }
}

const saveMutation = useMutation({
  mutationFn: async ({
    formValues,
    force = false,
    asDraft = false,
    silent = false,
  }: {
    formValues: typeof values
    force?: boolean
    asDraft?: boolean
    silent?: boolean
  }) => {
    const payload = buildPayload(formValues, { force, asDraft })

    if (isEdit.value) {
      const result = await quotationsService.update(quotationId.value!, payload)
      return { quotation: result, silent, asDraft }
    }
    const result = await quotationsService.create(payload)
    return { quotation: result, silent, asDraft }
  },
  onSuccess: ({ quotation, silent, asDraft }) => {
    lastModifiedAt.value = quotation.last_modified_at ?? lastModifiedAt.value
    queryClient.invalidateQueries({ queryKey: ['quotations'] })

    if (silent) {
      draftSavedAt.value = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
      return
    }

    if (!isEdit.value) {
      localStorage.removeItem(DRAFT_STORAGE_KEY)
    }

    if (asDraft) {
      toast('Quotation saved as draft.', 'success')
      if (!isEdit.value) {
        allowNavigation()
        router.replace(`/quotations/${quotation.id}/edit`)
      }
      return
    }

    allowNavigation()
    toast(isEdit.value ? 'Quotation updated successfully.' : 'Quotation created successfully.', 'success')
    router.push(`/quotations/${quotation.id}`)
  },
  onError: (error: unknown, variables) => {
    if (variables.silent) return

    if (error instanceof ValidationError) {
      submitError.value = error.message
      return
    }

    const response = (error as { response?: { status?: number; data?: { message?: string } } })?.response
    if (response?.status === 409 && isEdit.value) {
      concurrencyOpen.value = true
      pendingSubmitValues.value = variables.formValues
      return
    }

    submitError.value = response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to save quotation.')
    toast(submitError.value, 'error')
  },
})

function submitForm(asDraft: boolean) {
  saveMode.value = asDraft ? 'draft' : 'quotation'
  onSubmit()
}

const onSubmit = handleSubmit((formValues) => {
  submitError.value = ''
  saveMutation.mutate({
    formValues,
    asDraft: saveMode.value === 'draft',
  })
})

function reloadAfterConflict() {
  concurrencyOpen.value = false
  pendingSubmitValues.value = null
  queryClient.invalidateQueries({ queryKey: ['quotations', quotationId.value] })
}

function forceSaveAfterConflict() {
  if (!pendingSubmitValues.value) return
  concurrencyOpen.value = false
  saveMutation.mutate({
    formValues: pendingSubmitValues.value,
    force: true,
    asDraft: saveMode.value === 'draft',
  })
  pendingSubmitValues.value = null
}

function cancelForm() {
  router.push(isEdit.value ? `/quotations/${quotationId.value}` : '/quotations')
}

function saveDraftToLocalStorage() {
  if (isEdit.value || !meta.value.dirty) return
  localStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify({
    savedAt: Date.now(),
    values: values,
    selectedCustomer: selectedCustomer.value,
  }))
}

const debouncedAutoSave = useDebounceFn(() => {
  if (isEdit.value) {
    if (!meta.value.dirty) return
    saveMutation.mutate({ formValues: values, silent: true })
    return
  }
  saveDraftToLocalStorage()
}, 60000)

watch(() => values, () => debouncedAutoSave(), { deep: true })

function restoreLocalDraft() {
  if (!pendingDraft.value) return
  resetForm({ values: pendingDraft.value.values as typeof values })
  selectedCustomer.value = pendingDraft.value.selectedCustomer
  draftRestoreOpen.value = false
  pendingDraft.value = null
  toast('Draft restored.', 'success')
}

function dismissLocalDraft() {
  localStorage.removeItem(DRAFT_STORAGE_KEY)
  draftRestoreOpen.value = false
  pendingDraft.value = null
}

onMounted(() => {
  if (isEdit.value) return
  const raw = localStorage.getItem(DRAFT_STORAGE_KEY)
  if (!raw) return
  try {
    const draft = JSON.parse(raw) as typeof pendingDraft.value
    if (!draft || Date.now() - draft.savedAt > DRAFT_MAX_AGE_MS) {
      localStorage.removeItem(DRAFT_STORAGE_KEY)
      return
    }
    pendingDraft.value = draft
    draftRestoreOpen.value = true
  } catch {
    localStorage.removeItem(DRAFT_STORAGE_KEY)
  }
})
</script>

<template>
  <section>
    <PageHeader
      :title="isEdit ? 'Edit Quotation' : 'Create Quotation'"
      subtitle="Build a quotation with line items, tax, and bank details."
      :breadcrumb="[
        { label: 'Quotations', href: '/quotations' },
        { label: isEdit ? 'Edit' : 'New' },
      ]"
    />

    <div v-if="quotationQuery.isLoading.value && isEdit" class="rounded-lg border bg-white p-8 text-center text-sm text-slate-500">
      Loading quotation…
    </div>

    <form v-else class="space-y-6" @submit.prevent="onSubmit">
      <div
        v-if="draftRestoreOpen"
        class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900"
      >
        <span>A saved draft was found. Restore it?</span>
        <div class="flex gap-2">
          <button type="button" class="rounded-md border bg-white px-3 py-1.5" @click="dismissLocalDraft">Dismiss</button>
          <button type="button" class="rounded-md bg-brand-blue px-3 py-1.5 font-semibold text-white" @click="restoreLocalDraft">Restore</button>
        </div>
      </div>

      <div v-if="draftSavedAt && isEdit" class="text-sm text-slate-500">
        Draft saved {{ draftSavedAt }}
      </div>

      <div v-if="submitError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ submitError }}
      </div>

      <!-- Header card -->
      <div class="rounded-lg border bg-white p-6 shadow-card">
        <h2 class="mb-4 text-base font-semibold text-slate-900">Quotation Details</h2>

        <div
          v-if="isInactiveCustomer"
          class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
          This customer is inactive.
        </div>

        <div class="grid gap-4 md:grid-cols-3">
          <label class="block text-sm md:col-span-1">
            <span class="mb-1 block font-medium text-slate-700">Customer Name *</span>
            <AsyncCombobox
              :model-value="selectedCustomer"
              placeholder="Search customer by name, email, or TIN…"
              :load-options="loadCustomers"
              :render-option="(customer) => customer.company_name"
              @update:model-value="onCustomerSelected"
            >
              <template #option="{ option }">
                <span class="font-semibold">{{ (option as CustomerSearchResult).company_name }}</span>
                <span v-if="(option as CustomerSearchResult).tin_number" class="ml-2 text-slate-500">
                  {{ (option as CustomerSearchResult).tin_number }}
                </span>
              </template>
              <template #footer>
                <button
                  type="button"
                  class="w-full rounded-md px-2 py-1.5 text-left text-sm font-medium text-brand-blue hover:bg-slate-50"
                  @mousedown.prevent="quickCreateOpen = true"
                >
                  + Add New Customer
                </button>
              </template>
            </AsyncCombobox>
            <ErrorMessage name="customer_id" class="mt-1 block text-xs text-red-600" />
          </label>

          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">TIN Number</span>
            <input :value="tinNumber" type="text" readonly class="w-full rounded-md border bg-slate-50 px-3 py-2" />
          </label>

          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">Quotation Status *</span>
            <select v-model="statusId" class="w-full rounded-md border px-3 py-2">
              <option value="">Select status</option>
              <option v-for="status in statuses" :key="status.id" :value="status.id">
                ● {{ status.name }}
              </option>
            </select>
            <div class="mt-1 flex flex-wrap gap-2">
              <span
                v-for="status in statuses.filter((s) => s.id === statusId)"
                :key="status.id"
                class="inline-flex items-center gap-1 text-xs text-slate-600"
              >
                <span class="inline-block h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: status.color }" />
                {{ status.name }}
              </span>
            </div>
            <ErrorMessage name="status_id" class="mt-1 block text-xs text-red-600" />
          </label>

          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">Quotation Date *</span>
            <input v-model="quotationDate" type="date" class="w-full rounded-md border px-3 py-2" />
            <ErrorMessage name="quotation_date" class="mt-1 block text-xs text-red-600" />
          </label>

          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">Expiry Date *</span>
            <input
              v-model="expiryDate"
              type="date"
              :min="minExpiryDate"
              class="w-full rounded-md border px-3 py-2"
            />
            <p v-if="expiryInlineError" class="mt-1 text-xs text-red-600">{{ expiryInlineError }}</p>
            <ErrorMessage name="expiry_date" class="mt-1 block text-xs text-red-600" />
          </label>

          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">Authorized By</span>
            <input
              :value="authStore.userFullName"
              type="text"
              readonly
              class="w-full rounded-md border bg-slate-50 px-3 py-2"
            />
          </label>

          <label class="block text-sm md:col-span-3">
            <span class="mb-1 block font-medium text-slate-700">Bank Detail</span>
            <select v-model="bankDetailId" class="w-full rounded-md border px-3 py-2">
              <option value="">None</option>
              <option v-for="bank in banks" :key="bank.id" :value="bank.id">
                {{ bank.bank_name }} — {{ bank.account_number }}
                <template v-if="bank.is_primary"> (Primary)</template>
              </option>
            </select>
          </label>
        </div>
      </div>

      <!-- Line items -->
      <div class="rounded-lg border bg-white p-6 shadow-card">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-900">Line Items</h2>
          <button
            type="button"
            class="inline-flex items-center gap-1 rounded-md border px-3 py-1.5 text-sm hover:bg-slate-50"
            @click="addLineItem"
          >
            <Plus class="h-4 w-4" />
            Add Item
          </button>
        </div>

        <div class="overflow-x-auto">
        <div class="mb-3 hidden min-w-[960px] grid-cols-[32px_1.5fr_48px_2fr_80px_120px_80px_80px_100px_40px] gap-2 border-b pb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 lg:grid">
          <span />
          <span>Product</span>
          <span>Image</span>
          <span>Description</span>
          <span>Unit</span>
          <span>Rate</span>
          <span>Qty</span>
          <span>Disc%</span>
          <span class="text-right">Line Total</span>
          <span />
        </div>

        <div class="min-w-[960px] space-y-4">
          <div
            v-for="(field, index) in itemFields"
            :key="field.key"
            class="rounded-lg border p-4 lg:border-0 lg:p-0 lg:pb-4"
          >
              <div class="grid gap-3 lg:grid-cols-[32px_1.5fr_48px_2fr_80px_120px_80px_80px_100px_40px] lg:items-start lg:gap-2">
                <div class="flex items-center gap-2">
                  <div class="flex flex-col gap-0.5">
                    <button
                      type="button"
                      class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 disabled:opacity-30"
                      :disabled="index === 0"
                      title="Move up"
                      @click="moveItem(index, index - 1)"
                    >
                      <ArrowUp class="h-4 w-4" />
                    </button>
                    <button
                      type="button"
                      class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 disabled:opacity-30"
                      :disabled="index === itemFields.length - 1"
                      title="Move down"
                      @click="moveItem(index, index + 1)"
                    >
                      <ArrowDown class="h-4 w-4" />
                    </button>
                  </div>
                  <span class="text-sm font-medium text-slate-600 lg:hidden">#{{ index + 1 }}</span>
                </div>

                <div class="lg:pt-1">
                  <span class="mb-1 block text-xs font-medium text-slate-600 lg:hidden">Product *</span>
                  <AsyncCombobox
                    :model-value="selectedProducts.get(field.key) ?? null"
                    placeholder="Search products…"
                    :load-options="loadProducts"
                    :render-option="(product) => product.title"
                    @update:model-value="(product) => onProductSelected(index, field.key, product)"
                  />
                </div>

                <div class="flex items-center lg:justify-center">
                  <img
                    v-if="field.value.image_url"
                    :src="field.value.image_url"
                    alt=""
                    class="h-10 w-10 rounded border object-cover"
                  />
                  <span v-else class="inline-flex h-10 w-10 items-center justify-center rounded border bg-slate-50 text-xs text-slate-400">—</span>
                </div>

                <label class="block text-sm">
                  <span class="mb-1 block text-xs font-medium text-slate-600 lg:hidden">Description *</span>
                  <textarea
                    :value="field.value.description"
                    rows="2"
                    class="w-full rounded-md border px-3 py-2 text-sm"
                    @input="updateItemField(index, 'description', ($event.target as HTMLTextAreaElement).value)"
                  />
                  <ErrorMessage :name="`items.${index}.description`" class="mt-1 block text-xs text-red-600" />
                </label>

                <label class="block text-sm">
                  <span class="mb-1 block text-xs font-medium text-slate-600 lg:hidden">Unit</span>
                  <input
                    :value="field.value.unit"
                    type="text"
                    readonly
                    class="w-full rounded-md border bg-slate-50 px-3 py-2 text-sm"
                  />
                </label>

                <div>
                  <span class="mb-1 block text-xs font-medium text-slate-600 lg:hidden">Rate *</span>
                  <CurrencyInput
                    :model-value="Number(field.value.rate) || 0"
                    @update:model-value="(rate) => onRateChange(field.key, index, rate)"
                  />
                  <span
                    v-if="showRateBadge(field.key)"
                    class="mt-1 inline-block rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-900"
                  >
                    Rate differs from product price ({{ formatCurrency(productRateFor(field.key)) }})
                  </span>
                  <ErrorMessage :name="`items.${index}.rate`" class="mt-1 block text-xs text-red-600" />
                </div>

                <label class="block text-sm">
                  <span class="mb-1 block text-xs font-medium text-slate-600 lg:hidden">Qty *</span>
                  <input
                    :value="field.value.quantity"
                    type="number"
                    min="1"
                    step="1"
                    class="w-full rounded-md border px-3 py-2 text-sm"
                    @input="updateItemField(index, 'quantity', Number(($event.target as HTMLInputElement).value) || 1)"
                  />
                  <ErrorMessage :name="`items.${index}.quantity`" class="mt-1 block text-xs text-red-600" />
                </label>

                <label class="block text-sm">
                  <span class="mb-1 block text-xs font-medium text-slate-600 lg:hidden">Disc %</span>
                  <input
                    :value="field.value.discount_rate"
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    class="w-full rounded-md border px-3 py-2 text-sm"
                    @input="updateItemField(index, 'discount_rate', Number(($event.target as HTMLInputElement).value) || 0)"
                  />
                  <ErrorMessage :name="`items.${index}.discount_rate`" class="mt-1 block text-xs text-red-600" />
                </label>

                <div class="text-right text-sm font-medium lg:pt-2">
                  <span class="mb-1 block text-xs text-slate-500 lg:hidden">Line Total</span>
                  {{ formatCurrency(lineTotal(field.value)) }}
                </div>

                <div class="flex justify-end lg:pt-1">
                  <button
                    type="button"
                    class="rounded p-1 text-red-500 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="itemFields.length <= 1"
                    title="Remove item"
                    @click="removeLineItem(index)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pricing footer -->
      <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
        <div class="space-y-4 rounded-lg border bg-white p-6 shadow-card">
          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">Terms & Conditions</span>
            <textarea v-model="termsConditions" rows="4" class="w-full rounded-md border px-3 py-2" />
          </label>
          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">Notes</span>
            <textarea v-model="notes" rows="3" class="w-full rounded-md border px-3 py-2" />
          </label>
        </div>

        <div class="rounded-lg border bg-white p-6 shadow-card">
          <h2 class="mb-4 text-base font-semibold text-slate-900">Pricing</h2>
          <dl class="grid gap-2 text-sm">
            <div class="flex justify-between">
              <dt class="text-slate-500">Sub Total</dt>
              <dd class="font-medium">{{ formatCurrency(totals.subTotal) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-500">Discount Amount</dt>
              <dd class="font-medium">{{ formatCurrency(totals.discountAmount) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-500">VAT ({{ effectiveVatRate }}%)</dt>
              <dd class="font-medium">{{ formatCurrency(totals.vatAmount) }}</dd>
            </div>
            <div class="flex justify-between border-t pt-2 text-base font-semibold">
              <dt>TOTAL</dt>
              <dd>{{ formatCurrency(totals.total) }}</dd>
            </div>
          </dl>
        </div>
      </div>

      <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap gap-3 border-t bg-white/95 px-4 py-4 backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:px-0 sm:py-0" style="padding-bottom: calc(1rem + env(safe-area-inset-bottom))">
        <button
          type="button"
          class="rounded-lg border px-5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
          :disabled="saveMutation.isPending.value"
          @click="submitForm(true)"
        >
          Save as Draft
        </button>
        <button
          type="button"
          class="rounded-lg bg-brand-blue px-5 py-2 text-sm font-semibold text-white hover:bg-brand-blue/90 disabled:opacity-50"
          :disabled="saveMutation.isPending.value || !meta.valid || hasItemErrors"
          @click="submitForm(false)"
        >
          {{ saveMutation.isPending.value ? 'Saving…' : 'Save Quotation' }}
        </button>
        <button
          type="button"
          class="rounded-lg border px-5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
          @click="cancelForm"
        >
          Cancel
        </button>
      </div>
    </form>

    <QuickCreateCustomerModal
      :open="quickCreateOpen"
      @close="quickCreateOpen = false"
      @selected="onQuickCreateSelected"
    />

    <Teleport to="body">
      <div
        v-if="concurrencyOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"
      >
        <section role="alertdialog" class="w-full max-w-md rounded-lg bg-white p-6 shadow-card">
          <h2 class="text-lg font-semibold text-slate-900">Quotation updated by another user</h2>
          <p class="mt-2 text-sm text-slate-600">
            Reload changes, or force save your version?
          </p>
          <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="rounded-md border px-4 py-2 text-sm" @click="reloadAfterConflict">
              Reload
            </button>
            <button
              type="button"
              class="rounded-md bg-brand-blue px-4 py-2 text-sm font-semibold text-white"
              @click="forceSaveAfterConflict"
            >
              Force Save
            </button>
          </div>
        </section>
      </div>
    </Teleport>
  </section>
</template>
