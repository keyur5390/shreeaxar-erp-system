<script setup lang="ts">
import { computed, ref } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { Eye, EyeOff, Pencil, Plus, Star, Trash2 } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import PermissionGate from '@/components/ui/PermissionGate.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import { bankDetailsService, type BankDetailPayload } from '@/services/bank-details.service'
import { STALE_TIME } from '@/lib/queryTimes'
import { useToast } from '@/composables/useToast'
import type { BankDetail } from '@/types'
import { ValidationError } from '@/services/api'

const queryClient = useQueryClient()
const { toast } = useToast()

const modalOpen = ref(false)
const editingBank = ref<BankDetail | null>(null)
const deleteTarget = ref<BankDetail | null>(null)
const revealedAccounts = ref<Record<string, boolean>>({})
const formError = ref('')

const form = ref<BankDetailPayload>({
  bank_name: '',
  account_number: '',
  account_holder_name: '',
  branch_name: '',
  swift_code: '',
  is_primary: false,
})

const banksQuery = useQuery({
  queryKey: ['bank-details'],
  queryFn: () => bankDetailsService.list(),
  staleTime: STALE_TIME.masters,
})

const banks = computed(() => banksQuery.data.value ?? [])

const saveMutation = useMutation({
  mutationFn: async () => {
    const payload: BankDetailPayload = {
      bank_name: form.value.bank_name.trim(),
      account_number: form.value.account_number.trim(),
      account_holder_name: form.value.account_holder_name.trim(),
      branch_name: form.value.branch_name?.trim() || null,
      swift_code: form.value.swift_code?.trim() || null,
      is_primary: form.value.is_primary,
    }
    if (!payload.bank_name || !payload.account_number || !payload.account_holder_name) {
      throw new Error('Bank name, account number, and account holder are required.')
    }
    if (editingBank.value) return bankDetailsService.update(editingBank.value.id, payload)
    return bankDetailsService.create(payload)
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['bank-details'] })
    toast(editingBank.value ? 'Bank detail updated successfully.' : 'Bank detail created successfully.', 'success')
    closeModal()
  },
  onError: (error: unknown) => {
    formError.value = error instanceof ValidationError
      ? Object.values(error.errors).flat()[0] ?? error.message
      : error instanceof Error ? error.message : 'Unable to save bank detail.'
  },
})

const setPrimaryMutation = useMutation({
  mutationFn: (id: string) => bankDetailsService.setPrimary(id),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['bank-details'] })
    toast('Primary bank updated.', 'success')
  },
  onError: (error: unknown) => {
    toast(error instanceof Error ? error.message : 'Unable to set primary bank.', 'error')
  },
})

const deleteMutation = useMutation({
  mutationFn: (bank: BankDetail) => bankDetailsService.remove(bank.id),
  onSuccess: (response) => {
    queryClient.invalidateQueries({ queryKey: ['bank-details'] })
    toast(response?.message ?? 'Bank detail deleted successfully.', response?.message ? 'info' : 'success')
    deleteTarget.value = null
  },
  onError: (error: unknown) => {
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to delete bank detail.')
    toast(message, 'error')
    deleteTarget.value = null
  },
})

function maskAccount(number: string): string {
  if (number.length <= 4) return '****'
  return '*'.repeat(number.length - 4) + number.slice(-4)
}

function toggleReveal(id: string) {
  revealedAccounts.value[id] = !revealedAccounts.value[id]
}

function openCreateModal() {
  editingBank.value = null
  form.value = { bank_name: '', account_number: '', account_holder_name: '', branch_name: '', swift_code: '', is_primary: false }
  formError.value = ''
  modalOpen.value = true
}

function openEditModal(bank: BankDetail) {
  editingBank.value = bank
  form.value = {
    bank_name: bank.bank_name,
    account_number: bank.account_number,
    account_holder_name: bank.account_holder_name,
    branch_name: bank.branch_name ?? '',
    swift_code: bank.swift_code ?? '',
    is_primary: bank.is_primary,
  }
  formError.value = ''
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
  editingBank.value = null
  formError.value = ''
}
</script>

<template>
  <section>
    <PageHeader
      title="Bank Details"
      subtitle="Manage company bank accounts for quotations."
      :breadcrumb="[{ label: 'Masters' }, { label: 'Bank Details' }]"
    >
      <template #action>
        <PermissionGate module="bank_details" action="create">
          <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-brand-blue px-4 py-2 text-sm font-semibold text-white hover:bg-brand-blue/90" @click="openCreateModal">
            <Plus class="h-4 w-4" />
            Add Bank
          </button>
        </PermissionGate>
      </template>
    </PageHeader>

    <div v-if="banksQuery.isLoading.value" class="rounded-lg border bg-white p-6 text-sm text-slate-500">Loading bank details…</div>
    <div v-else-if="!banks.length" class="rounded-lg border bg-white p-8 text-center text-sm text-slate-500">No bank details configured.</div>

    <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <article
        v-for="bank in banks"
        :key="bank.id"
        class="relative rounded-lg border bg-white p-5 shadow-card"
        :class="{ 'border-amber-400 ring-1 ring-amber-200': bank.is_primary }"
      >
        <Star v-if="bank.is_primary" class="absolute right-4 top-4 h-5 w-5 fill-amber-400 text-amber-400" />

        <h3 class="pr-8 text-lg font-semibold text-slate-900">{{ bank.bank_name }}</h3>
        <p class="mt-1 text-sm text-slate-600">{{ bank.account_holder_name }}</p>
        <p v-if="bank.branch_name" class="text-xs text-slate-500">{{ bank.branch_name }}</p>

        <div class="mt-3 flex items-center gap-2 font-mono text-sm">
          <span>{{ revealedAccounts[bank.id] ? bank.account_number : maskAccount(bank.account_number) }}</span>
          <button type="button" class="rounded p-1 text-slate-500 hover:bg-slate-100" @click="toggleReveal(bank.id)">
            <Eye v-if="!revealedAccounts[bank.id]" class="h-4 w-4" />
            <EyeOff v-else class="h-4 w-4" />
          </button>
        </div>

        <p v-if="bank.swift_code" class="mt-2 text-xs text-slate-500">SWIFT: {{ bank.swift_code }}</p>

        <div class="mt-4 flex flex-wrap gap-2">
          <PermissionGate v-if="!bank.is_primary" module="bank_details" action="edit">
            <button type="button" class="rounded-md border px-3 py-1.5 text-xs font-medium hover:bg-slate-50" :disabled="setPrimaryMutation.isPending.value" @click="setPrimaryMutation.mutate(bank.id)">
              Set Primary
            </button>
          </PermissionGate>
          <PermissionGate module="bank_details" action="edit">
            <button type="button" class="inline-flex items-center gap-1 rounded-md border px-3 py-1.5 text-xs font-medium hover:bg-slate-50" @click="openEditModal(bank)">
              <Pencil class="h-3.5 w-3.5" /> Edit
            </button>
          </PermissionGate>
          <PermissionGate module="bank_details" action="delete">
            <button type="button" class="inline-flex items-center gap-1 rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50" @click="deleteTarget = bank">
              <Trash2 class="h-3.5 w-3.5" /> Delete
            </button>
          </PermissionGate>
        </div>
      </article>
    </div>

    <Teleport to="body">
      <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4">
        <section class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-lg bg-white p-6 shadow-card">
          <h2 class="text-lg font-semibold">{{ editingBank ? 'Edit Bank Detail' : 'Add Bank Detail' }}</h2>

          <label class="mt-4 block text-sm font-medium">Bank Name<input v-model="form.bank_name" type="text" class="mt-1 w-full rounded-md border px-3 py-2" /></label>
          <label class="mt-4 block text-sm font-medium">Account Holder<input v-model="form.account_holder_name" type="text" class="mt-1 w-full rounded-md border px-3 py-2" /></label>
          <label class="mt-4 block text-sm font-medium">Account Number<input v-model="form.account_number" type="text" class="mt-1 w-full rounded-md border px-3 py-2 font-mono" /></label>
          <label class="mt-4 block text-sm font-medium">Branch Name<input v-model="form.branch_name" type="text" class="mt-1 w-full rounded-md border px-3 py-2" /></label>
          <label class="mt-4 block text-sm font-medium">SWIFT Code<input v-model="form.swift_code" type="text" class="mt-1 w-full rounded-md border px-3 py-2 font-mono uppercase" /></label>

          <label v-if="!editingBank?.is_primary" class="mt-4 flex items-center gap-2 text-sm">
            <input v-model="form.is_primary" type="checkbox" class="rounded border" />
            Set as primary bank
          </label>

          <p v-if="formError" class="mt-2 text-sm text-red-600">{{ formError }}</p>

          <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="rounded-md border px-4 py-2" @click="closeModal">Cancel</button>
            <button type="button" class="rounded-md bg-brand-blue px-4 py-2 text-white disabled:opacity-50" :disabled="saveMutation.isPending.value" @click="saveMutation.mutate()">Save</button>
          </div>
        </section>
      </div>
    </Teleport>

    <ConfirmDialog
      :open="Boolean(deleteTarget)"
      title="Delete bank detail?"
      :description="deleteTarget ? 'Delete ' + deleteTarget.bank_name + '? If used by quotations it will be deactivated instead.' : ''"
      confirm-label="Delete"
      confirm-variant="destructive"
      @cancel="deleteTarget = null"
      @confirm="deleteTarget && deleteMutation.mutate(deleteTarget)"
    />
  </section>
</template>
