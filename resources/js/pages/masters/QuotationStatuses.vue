<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { useDebounceFn } from '@vueuse/core'
import { VueDraggableNext as draggable } from 'vue-draggable-next'
import { GripVertical, Plus, Trash2, X } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import { quotationStatusesService } from '@/services/quotation-statuses.service'
import { STALE_TIME } from '@/lib/queryTimes'
import { useInlineEdit } from '@/composables/useInlineEdit'
import { useToast } from '@/composables/useToast'
import { usePermission } from '@/composables/usePermissions'
import type { QuotationStatusMaster } from '@/types'
import { ValidationError } from '@/services/api'

const queryClient = useQueryClient()
const { toast } = useToast()
const { startEdit, cancelEdit, isEditing, getValue, setValue } = useInlineEdit()

const statuses = ref<QuotationStatusMaster[]>([])
const skipReorder = ref(true)
const deleteTarget = ref<QuotationStatusMaster | null>(null)
const addingNew = ref(false)
const newName = ref('')
const newColor = ref('#3B82F6')
const addError = ref('')
const editContainers = ref<Record<string, HTMLElement | null>>({})

const statusesQuery = useQuery({
  queryKey: ['quotation-statuses'],
  queryFn: () => quotationStatusesService.list(),
  staleTime: STALE_TIME.masters,
})

watch(
  () => statusesQuery.data.value,
  (data) => {
    if (data) {
      skipReorder.value = true
      statuses.value = [...data]
      nextTick(() => { skipReorder.value = false })
    }
  },
  { immediate: true },
)

const debouncedReorder = useDebounceFn(async (items: QuotationStatusMaster[]) => {
  try {
    await quotationStatusesService.reorder(
      items.map((item, index) => ({ id: item.id, sort_order: index })),
    )
    queryClient.invalidateQueries({ queryKey: ['quotation-statuses'] })
  } catch {
    toast('Unable to save sort order.', 'error')
    queryClient.invalidateQueries({ queryKey: ['quotation-statuses'] })
  }
}, 1000)

watch(statuses, (items) => {
  if (skipReorder.value) return
  debouncedReorder(items)
}, { flush: 'post' })

const createMutation = useMutation({
  mutationFn: () => quotationStatusesService.create({ name: newName.value.trim(), color: newColor.value }),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['quotation-statuses'] })
    toast('Status created successfully.', 'success')
    addingNew.value = false
    newName.value = ''
    newColor.value = '#3B82F6'
    addError.value = ''
  },
  onError: (error: unknown) => {
    addError.value = error instanceof ValidationError
      ? Object.values(error.errors).flat()[0] ?? error.message
      : error instanceof Error ? error.message : 'Unable to create status.'
  },
})

const updateMutation = useMutation({
  mutationFn: ({ id, payload }: { id: string; payload: Partial<{ name: string; color: string }> }) =>
    quotationStatusesService.update(id, payload),
  onSuccess: (_data, variables) => {
    queryClient.invalidateQueries({ queryKey: ['quotation-statuses'] })
    if (variables.payload.name) toast('Status updated successfully.', 'success')
    cancelEdit(variables.id)
  },
  onError: (error: unknown) => {
    toast(error instanceof Error ? error.message : 'Unable to update status.', 'error')
  },
})

const deleteMutation = useMutation({
  mutationFn: (status: QuotationStatusMaster) => quotationStatusesService.remove(status.id),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['quotation-statuses'] })
    toast('Status deleted successfully.', 'success')
    deleteTarget.value = null
  },
  onError: (error: unknown) => {
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to delete status.')
    toast(message, 'error')
    deleteTarget.value = null
  },
})

const isLoading = computed(() => statusesQuery.isLoading.value)
const statusPermissions = usePermission('quotation_statuses')
const canEditStatuses = computed(() => statusPermissions.value.edit)
const canCreateStatuses = computed(() => statusPermissions.value.create)
const canDeleteStatuses = computed(() => statusPermissions.value.delete)

function setEditContainer(id: string, el: HTMLElement | null) {
  editContainers.value[id] = el
}

function beginEdit(status: QuotationStatusMaster) {
  startEdit(status.id, status.name)
  nextTick(() => editContainers.value[status.id]?.querySelector('input')?.focus())
}

function saveEdit(id: string) {
  const name = getValue(id).trim()
  if (!name) {
    toast('Status name is required.', 'error')
    return
  }
  updateMutation.mutate({ id, payload: { name } })
}

function onColorChange(status: QuotationStatusMaster, event: Event) {
  const color = (event.target as HTMLInputElement).value
  updateMutation.mutate({ id: status.id, payload: { color } })
}

function saveNew() {
  const name = newName.value.trim()
  if (!name) {
    addError.value = 'Status name is required.'
    return
  }
  createMutation.mutate()
}
</script>

<template>
  <section>
    <PageHeader
      title="Quotation Statuses"
      subtitle="Drag to reorder. System statuses cannot be renamed or deleted."
      :breadcrumb="[{ label: 'Masters' }, { label: 'Quotation Statuses' }]"
    />

    <div v-if="isLoading" class="rounded-lg border bg-white p-6 text-sm text-slate-500">Loading statuses…</div>

    <div v-else-if="statusesQuery.isError.value" class="rounded-lg border border-red-200 bg-red-50 p-6 text-sm text-red-700">
      Unable to load quotation statuses. Please refresh the page.
    </div>

    <div v-else class="rounded-lg border bg-white shadow-card">
      <draggable v-model="statuses" handle=".drag-handle" class="divide-y">
        <div
          v-for="status in statuses"
          :key="status.id"
          class="flex items-center gap-3 px-4 py-3"
        >
          <button
            v-if="canEditStatuses"
            type="button"
            class="drag-handle cursor-grab text-slate-400 hover:text-slate-600 active:cursor-grabbing"
          >
            <GripVertical class="h-5 w-5" />
          </button>
          <span v-else class="w-5" />

          <input
            v-if="canEditStatuses"
            type="color"
            :value="status.color"
            class="h-8 w-8 cursor-pointer rounded border-0 bg-transparent p-0"
            @change="onColorChange(status, $event)"
          />
          <span v-else class="inline-block h-8 w-8 rounded border" :style="{ backgroundColor: status.color }" />

          <div class="min-w-0 flex-1">
            <template v-if="!status.is_system && isEditing(status.id)">
              <div :ref="(el) => setEditContainer(status.id, el as HTMLElement | null)">
                <input
                  :value="getValue(status.id)"
                  type="text"
                  class="w-full max-w-xs rounded border px-2 py-1 text-sm"
                  @input="setValue(status.id, ($event.target as HTMLInputElement).value)"
                  @keydown.enter.prevent="saveEdit(status.id)"
                  @keydown.esc.prevent="cancelEdit(status.id)"
                  @blur="saveEdit(status.id)"
                />
              </div>
            </template>
            <button
              v-else
              type="button"
              class="text-left text-sm font-medium text-slate-900"
              :class="{ 'cursor-default': status.is_system }"
              @click="!status.is_system && beginEdit(status)"
            >
              {{ status.name }}
            </button>
          </div>

          <span v-if="status.is_system" class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">System</span>
          <span class="text-xs text-slate-500">{{ status.quotations_count ?? 0 }}</span>

          <button
            v-if="!status.is_system && canDeleteStatuses"
            type="button"
            class="rounded p-1 text-slate-500 hover:bg-red-100 hover:text-red-700"
            @click="deleteTarget = status"
          >
            <Trash2 class="h-4 w-4" />
          </button>
        </div>
      </draggable>

      <div v-if="canCreateStatuses" class="border-t p-4">
        <div v-if="addingNew" class="flex flex-wrap items-center gap-2">
          <input v-model="newColor" type="color" class="h-8 w-8 cursor-pointer rounded border-0 bg-transparent p-0" />
          <input v-model="newName" type="text" class="rounded-md border px-3 py-2 text-sm" placeholder="Status name" @keydown.enter.prevent="saveNew" @keydown.esc.prevent="addingNew = false" />
          <button type="button" class="rounded-md bg-brand-blue px-3 py-2 text-sm text-white" :disabled="createMutation.isPending.value" @click="saveNew">Save</button>
          <button type="button" class="rounded-md border px-3 py-2 text-sm" @click="addingNew = false"><X class="h-4 w-4" /></button>
          <p v-if="addError" class="w-full text-sm text-red-600">{{ addError }}</p>
        </div>
        <button v-else type="button" class="inline-flex items-center gap-1 text-sm text-brand-blue hover:underline" @click="addingNew = true">
          <Plus class="h-4 w-4" /> Add Status
        </button>
      </div>
    </div>

    <ConfirmDialog
      :open="Boolean(deleteTarget)"
      title="Delete status?"
      :description="deleteTarget ? 'Delete ' + deleteTarget.name + '?' : ''"
      confirm-label="Delete"
      confirm-variant="destructive"
      @cancel="deleteTarget = null"
      @confirm="deleteTarget && deleteMutation.mutate(deleteTarget)"
    />
  </section>
</template>
