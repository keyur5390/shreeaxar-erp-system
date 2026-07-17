<script setup lang="ts">
import { computed, ref } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { Pencil, Plus, Trash2 } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import SkeletonTable from '@/components/ui/SkeletonTable.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import PermissionGate from '@/components/ui/PermissionGate.vue'
import { currenciesService } from '@/services/currencies.service'
import { STALE_TIME } from '@/lib/queryTimes'
import { useToast } from '@/composables/useToast'
import type { Currency } from '@/types'
import { ValidationError } from '@/services/api'

const queryClient = useQueryClient()
const { toast } = useToast()

const modalOpen = ref(false)
const editingCurrency = ref<Currency | null>(null)
const form = ref({
  code: '',
  name: '',
  symbol: '',
  decimal_places: 0,
  exchange_rate: 1,
  is_default: false,
  is_active: true,
})
const formError = ref('')
const deleteTarget = ref<Currency | null>(null)

const currenciesQuery = useQuery({
  queryKey: ['currencies'],
  queryFn: () => currenciesService.list(),
  staleTime: STALE_TIME.masters,
})

const currencies = computed(() => currenciesQuery.data.value ?? [])

function resetForm() {
  form.value = {
    code: '',
    name: '',
    symbol: '',
    decimal_places: 0,
    exchange_rate: 1,
    is_default: false,
    is_active: true,
  }
}

const saveMutation = useMutation({
  mutationFn: async () => {
    const payload = {
      code: form.value.code.trim().toUpperCase(),
      name: form.value.name.trim(),
      symbol: form.value.symbol.trim(),
      decimal_places: Number(form.value.decimal_places),
      exchange_rate: Number(form.value.exchange_rate),
      is_default: form.value.is_default,
      is_active: form.value.is_active,
    }
    if (!/^[A-Z]{3}$/.test(payload.code)) throw new Error('Code must be a 3-letter ISO code.')
    if (!payload.name) throw new Error('Name is required.')
    if (!payload.symbol) throw new Error('Symbol is required.')
    if (payload.exchange_rate <= 0) throw new Error('Exchange rate must be greater than 0.')
    if (editingCurrency.value) return currenciesService.update(editingCurrency.value.id, payload)
    return currenciesService.create(payload)
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['currencies'] })
    toast(editingCurrency.value ? 'Currency updated successfully.' : 'Currency created successfully.', 'success')
    closeModal()
  },
  onError: (error: unknown) => {
    formError.value = error instanceof ValidationError
      ? Object.values(error.errors).flat()[0] ?? error.message
      : error instanceof Error ? error.message : 'Unable to save currency.'
  },
})

const deleteMutation = useMutation({
  mutationFn: (currency: Currency) => currenciesService.remove(currency.id),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['currencies'] })
    toast('Currency deleted successfully.', 'success')
    deleteTarget.value = null
  },
  onError: (error: unknown) => {
    toast(error instanceof Error ? error.message : 'Unable to delete currency.', 'error')
    deleteTarget.value = null
  },
})

function openCreateModal() {
  editingCurrency.value = null
  resetForm()
  formError.value = ''
  modalOpen.value = true
}

function openEditModal(currency: Currency) {
  editingCurrency.value = currency
  form.value = {
    code: currency.code,
    name: currency.name,
    symbol: currency.symbol,
    decimal_places: currency.decimal_places,
    exchange_rate: currency.exchange_rate,
    is_default: currency.is_default,
    is_active: currency.is_active,
  }
  formError.value = ''
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
  editingCurrency.value = null
  resetForm()
  formError.value = ''
}
</script>

<template>
  <section>
    <PageHeader
      title="Currencies"
      subtitle="Manage supported currencies and exchange rates relative to the base currency."
      :breadcrumb="[{ label: 'Masters' }, { label: 'Currencies' }]"
    >
      <template #action>
        <PermissionGate module="currencies" action="create">
          <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-brand-blue px-4 py-2 text-sm font-semibold text-white hover:bg-brand-blue/90" @click="openCreateModal">
            <Plus class="h-4 w-4" />
            Add Currency
          </button>
        </PermissionGate>
      </template>
    </PageHeader>

    <p class="mb-4 text-sm text-slate-600">
      Exchange rate = how many base-currency units equal 1 unit of this currency. The default currency always uses rate 1.
    </p>

    <SkeletonTable v-if="currenciesQuery.isLoading.value" :cols="7" :rows="5" />

    <div v-else class="overflow-hidden rounded-lg border bg-white shadow-card">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Code</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Name</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Symbol</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Decimals</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Exchange Rate</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Status</th>
              <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-500">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="currency in currencies" :key="currency.id">
              <td class="px-4 py-3 text-sm font-semibold">{{ currency.code }} <span v-if="currency.is_default" class="ml-1 rounded bg-brand-blue/10 px-2 py-0.5 text-xs text-brand-blue">Default</span></td>
              <td class="px-4 py-3 text-sm">{{ currency.name }}</td>
              <td class="px-4 py-3 text-sm">{{ currency.symbol }}</td>
              <td class="px-4 py-3 text-sm">{{ currency.decimal_places }}</td>
              <td class="px-4 py-3 text-sm">{{ currency.exchange_rate }}</td>
              <td class="px-4 py-3 text-sm">{{ currency.is_active ? 'Active' : 'Inactive' }}</td>
              <td class="px-4 py-3 text-right">
                <div class="inline-flex gap-1">
                  <PermissionGate module="currencies" action="edit">
                    <button type="button" class="rounded p-2 hover:bg-slate-100" @click="openEditModal(currency)"><Pencil class="h-4 w-4" /></button>
                  </PermissionGate>
                  <PermissionGate module="currencies" action="delete">
                    <button type="button" class="rounded p-2 text-red-600 hover:bg-red-50" :disabled="currency.is_default" @click="deleteTarget = currency"><Trash2 class="h-4 w-4" /></button>
                  </PermissionGate>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4">
      <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-xl">
        <h3 class="text-lg font-semibold">{{ editingCurrency ? 'Edit Currency' : 'Add Currency' }}</h3>
        <p v-if="formError" class="mt-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ formError }}</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <label class="block text-sm"><span class="mb-1 block font-medium">Code</span><input v-model="form.code" maxlength="3" class="w-full rounded-md border px-3 py-2 uppercase" /></label>
          <label class="block text-sm"><span class="mb-1 block font-medium">Symbol</span><input v-model="form.symbol" class="w-full rounded-md border px-3 py-2" /></label>
          <label class="block text-sm sm:col-span-2"><span class="mb-1 block font-medium">Name</span><input v-model="form.name" class="w-full rounded-md border px-3 py-2" /></label>
          <label class="block text-sm"><span class="mb-1 block font-medium">Decimal Places</span><input v-model.number="form.decimal_places" type="number" min="0" max="4" class="w-full rounded-md border px-3 py-2" /></label>
          <label class="block text-sm"><span class="mb-1 block font-medium">Exchange Rate</span><input v-model.number="form.exchange_rate" type="number" min="0.00000001" step="0.0001" class="w-full rounded-md border px-3 py-2" :disabled="form.is_default" /></label>
          <label class="flex items-center gap-2 text-sm sm:col-span-2"><input v-model="form.is_default" type="checkbox" class="rounded" /><span>Default currency</span></label>
          <label class="flex items-center gap-2 text-sm sm:col-span-2"><input v-model="form.is_active" type="checkbox" class="rounded" /><span>Active</span></label>
        </div>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" class="rounded-md border px-4 py-2 text-sm" @click="closeModal">Cancel</button>
          <button type="button" class="rounded-md bg-brand-blue px-4 py-2 text-sm font-semibold text-white" :disabled="saveMutation.isPending.value" @click="saveMutation.mutate()">Save</button>
        </div>
      </div>
    </div>

    <ConfirmDialog
      :open="Boolean(deleteTarget)"
      title="Delete currency?"
      :description="`Delete ${deleteTarget?.code}? This cannot be undone.`"
      confirm-label="Delete"
      confirm-variant="destructive"
      @confirm="deleteTarget && deleteMutation.mutate(deleteTarget)"
      @cancel="deleteTarget = null"
    />
  </section>
</template>
