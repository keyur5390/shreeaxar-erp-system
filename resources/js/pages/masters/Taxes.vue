<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { AlertTriangle, Pencil } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import PermissionGate from '@/components/ui/PermissionGate.vue'
import { taxesService } from '@/services/taxes.service'
import { useInlineEdit } from '@/composables/useInlineEdit'
import { useToast } from '@/composables/useToast'
import type { Tax } from '@/types'
import { ValidationError } from '@/services/api'

const queryClient = useQueryClient()
const { toast } = useToast()
const { startEdit, cancelEdit, isEditing, getValue, setValue } = useInlineEdit()

const editContainers = ref<Record<string, HTMLElement | null>>({})

const taxesQuery = useQuery({
  queryKey: ['taxes'],
  queryFn: () => taxesService.list(),
})

const taxes = computed(() => taxesQuery.data.value ?? [])

const updateMutation = useMutation({
  mutationFn: ({ id, rate }: { id: string; rate: number }) => taxesService.update(id, { rate }),
  onSuccess: (_data, variables) => {
    queryClient.invalidateQueries({ queryKey: ['taxes'] })
    toast('Tax rate updated successfully.', 'success')
    cancelEdit(variables.id)
  },
  onError: (error: unknown, variables) => {
    const message = error instanceof ValidationError
      ? Object.values(error.errors).flat()[0] ?? error.message
      : (error as { response?: { data?: { message?: string } } })?.response?.data?.message
        ?? (error instanceof Error ? error.message : 'Unable to update tax rate.')
    toast(message, 'error')
    cancelEdit(variables.id)
  },
})

function setEditContainer(id: string, el: HTMLElement | null) {
  editContainers.value[id] = el
}

function beginEdit(tax: Tax) {
  startEdit(tax.id, String(tax.rate))
  nextTick(() => editContainers.value[tax.id]?.querySelector('input')?.focus())
}

function saveEdit(id: string) {
  const rate = Number(getValue(id))
  if (!Number.isFinite(rate) || rate <= 0 || rate > 100) {
    toast('Rate must be greater than 0 and at most 100.', 'error')
    return
  }
  updateMutation.mutate({ id, rate })
}

function handleEditKeydown(event: KeyboardEvent, id: string) {
  if (event.key === 'Enter') {
    event.preventDefault()
    saveEdit(id)
  } else if (event.key === 'Escape') {
    cancelEdit(id)
  }
}

function handleDocumentClick(event: MouseEvent) {
  const target = event.target as Node
  Object.keys(editContainers.value).forEach((id) => {
    if (!isEditing(id)) return
    const container = editContainers.value[id]
    if (container && !container.contains(target)) cancelEdit(id)
  })
}

onMounted(() => document.addEventListener('mousedown', handleDocumentClick))
onUnmounted(() => document.removeEventListener('mousedown', handleDocumentClick))
</script>

<template>
  <section>
    <PageHeader
      title="Tax"
      subtitle="Configure the default tax rate applied to new quotations."
      :breadcrumb="[{ label: 'Masters' }, { label: 'Tax' }]"
    />

    <div v-if="taxesQuery.isLoading.value" class="rounded-lg border bg-white p-6 text-sm text-slate-500">
      Loading tax settings…
    </div>

    <template v-else>
      <div class="mb-4 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
        <p>Rate change applies to <strong>NEW</strong> quotations only. Existing quotations are unaffected.</p>
      </div>

      <div v-for="tax in taxes" :key="tax.id" class="rounded-lg border bg-white p-6 shadow-card">
        <div class="flex flex-wrap items-center gap-3">
          <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tax Name</p>
            <p class="mt-1 text-lg font-semibold text-slate-900">{{ tax.name }}</p>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <span
              v-if="tax.is_fixed"
              class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700"
            >
              Fixed
            </span>
            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-800">
              Active
            </span>
          </div>
        </div>

        <div class="mt-6 border-t pt-6">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rate %</p>

          <div class="mt-2 flex items-center gap-2">
            <template v-if="isEditing(tax.id)">
              <div :ref="(el) => setEditContainer(tax.id, el as HTMLElement | null)" class="inline-flex items-center gap-2">
                <input
                  :value="getValue(tax.id)"
                  type="number"
                  min="0.01"
                  max="100"
                  step="0.01"
                  class="w-28 rounded-md border px-3 py-2 text-sm"
                  @input="setValue(tax.id, ($event.target as HTMLInputElement).value)"
                  @keydown="handleEditKeydown($event, tax.id)"
                />
                <span class="text-sm text-slate-600">%</span>
              </div>
            </template>
            <template v-else>
              <span class="text-2xl font-semibold text-slate-900">{{ tax.rate }}%</span>
              <PermissionGate module="taxes" action="edit">
                <button
                  type="button"
                  class="rounded-md border p-1.5 text-slate-600 hover:bg-slate-50"
                  title="Edit rate"
                  @click="beginEdit(tax)"
                >
                  <Pencil class="h-4 w-4" />
                </button>
              </PermissionGate>
            </template>
          </div>
        </div>
      </div>

      <div v-if="!taxes.length" class="rounded-lg border bg-white p-8 text-center text-sm text-slate-500">
        No tax configuration found.
      </div>
    </template>
  </section>
</template>
