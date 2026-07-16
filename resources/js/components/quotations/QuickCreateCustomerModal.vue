<script setup lang="ts">
import { ref } from 'vue'
import { useMutation } from '@tanstack/vue-query'
import { customersService } from '@/services/customers.service'
import type { CustomerSearchResult } from '@/types'

const props = defineProps<{
  open: boolean
}>()

const emit = defineEmits<{
  close: []
  selected: [customer: CustomerSearchResult]
}>()

const companyName = ref('')
const email = ref('')
const contact = ref('')
const formError = ref('')
const conflictCustomer = ref<{ id: string; company_name: string; email: string | null } | null>(null)

function resetForm(): void {
  companyName.value = ''
  email.value = ''
  contact.value = ''
  formError.value = ''
  conflictCustomer.value = null
}

function close(): void {
  resetForm()
  emit('close')
}

function toSearchResult(customer: {
  id: string
  company_name: string
  email?: string | null
  contact_number?: string | null
  tin_number?: string | null
  is_active?: boolean
}): CustomerSearchResult {
  return {
    id: customer.id,
    company_name: customer.company_name,
    tin_number: customer.tin_number ?? null,
    email: customer.email ?? null,
    contact_number: customer.contact_number ?? null,
    is_active: customer.is_active ?? true,
  }
}

const createMutation = useMutation({
  mutationFn: () => customersService.quickCreate({
    company_name: companyName.value.trim(),
    email: email.value.trim() || null,
    contact_number: contact.value.trim() || null,
  }),
  onSuccess: (customer) => {
    emit('selected', toSearchResult(customer))
    close()
  },
  onError: (error: unknown) => {
    const response = (error as {
      response?: {
        status?: number
        data?: {
          message?: string
          data?: { existing_customer?: { id: string; company_name: string; email: string | null } }
        }
      }
    })?.response

    if (response?.status === 409 && response.data?.data?.existing_customer) {
      conflictCustomer.value = response.data.data.existing_customer
      formError.value = ''
      return
    }

    formError.value = response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to create customer.')
  },
})

function submit(): void {
  formError.value = ''
  conflictCustomer.value = null
  if (!companyName.value.trim()) {
    formError.value = 'Company name is required.'
    return
  }
  createMutation.mutate()
}

function useExisting(): void {
  if (!conflictCustomer.value) return
  emit('selected', toSearchResult(conflictCustomer.value))
  close()
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/50 sm:items-center sm:p-4"
      @click.self="close"
    >
      <section
        role="dialog"
        aria-labelledby="quick-create-customer-title"
        class="flex max-h-[100dvh] w-full flex-col overflow-hidden rounded-t-2xl bg-white shadow-card sm:max-h-[90vh] sm:max-w-md sm:rounded-lg"
      >
        <div class="overflow-y-auto p-6">
        <h2 id="quick-create-customer-title" class="text-lg font-semibold text-slate-900">
          Add New Customer
        </h2>

        <form class="mt-5 space-y-4" @submit.prevent="submit">
          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">Company Name *</span>
            <input
              v-model="companyName"
              type="text"
              required
              maxlength="300"
              class="w-full rounded-md border px-3 py-2"
            />
          </label>

          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">Email</span>
            <input
              v-model="email"
              type="email"
              maxlength="255"
              class="w-full rounded-md border px-3 py-2"
            />
          </label>

          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">Contact</span>
            <input
              v-model="contact"
              type="text"
              maxlength="50"
              class="w-full rounded-md border px-3 py-2"
            />
          </label>

          <div
            v-if="conflictCustomer"
            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
          >
            Customer with this email exists: {{ conflictCustomer.company_name }}. Use them instead?
            <button
              type="button"
              class="mt-2 rounded-md bg-brand-blue px-3 py-1.5 text-xs font-semibold text-white"
              @click="useExisting"
            >
              Use Existing
            </button>
          </div>

          <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>
        </form>
        </div>

          <div class="flex justify-end gap-2 border-t p-4" style="padding-bottom: calc(1rem + env(safe-area-inset-bottom))">
            <button
              type="button"
              class="rounded-md border px-4 py-2 text-sm"
              :disabled="createMutation.isPending.value"
              @click="close"
            >
              Cancel
            </button>
            <button
              type="button"
              class="rounded-md bg-brand-blue px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
              :disabled="createMutation.isPending.value"
              @click="submit"
            >
              {{ createMutation.isPending.value ? 'Saving…' : 'Save & Select' }}
            </button>
          </div>
      </section>
    </div>
  </Teleport>
</template>
