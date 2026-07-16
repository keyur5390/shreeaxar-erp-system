<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { Pencil, Plus, Trash2, X } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import PermissionGate from '@/components/ui/PermissionGate.vue'
import { addressTypesService } from '@/services/address-types.service'
import { useInlineEdit } from '@/composables/useInlineEdit'
import { useToast } from '@/composables/useToast'
import type { AddressType } from '@/types'
import { ValidationError } from '@/services/api'

const queryClient = useQueryClient()
const { toast } = useToast()
const { startEdit, cancelEdit, isEditing, getValue, setValue } = useInlineEdit()

const addingNew = ref(false)
const newName = ref('')
const addError = ref('')
const deleteConfirmId = ref<string | null>(null)
const editContainers = ref<Record<string, HTMLElement | null>>({})

const addressTypesQuery = useQuery({
  queryKey: ['address-types'],
  queryFn: () => addressTypesService.list(),
})

const addressTypes = computed(() => addressTypesQuery.data.value ?? [])

const createMutation = useMutation({
  mutationFn: (name: string) => addressTypesService.create({ name }),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['address-types'] })
    toast('Address type created successfully.', 'success')
    addingNew.value = false
    newName.value = ''
    addError.value = ''
  },
  onError: (error: unknown) => {
    addError.value = error instanceof ValidationError
      ? Object.values(error.errors).flat()[0] ?? error.message
      : error instanceof Error ? error.message : 'Unable to create address type.'
  },
})

const updateMutation = useMutation({
  mutationFn: ({ id, name }: { id: string; name: string }) => addressTypesService.update(id, { name }),
  onSuccess: (_data, variables) => {
    queryClient.invalidateQueries({ queryKey: ['address-types'] })
    toast('Address type updated successfully.', 'success')
    cancelEdit(variables.id)
  },
  onError: (error: unknown, variables) => {
    const message = error instanceof ValidationError
      ? Object.values(error.errors).flat()[0] ?? error.message
      : (error as { response?: { data?: { message?: string } } })?.response?.data?.message
        ?? (error instanceof Error ? error.message : 'Unable to update address type.')
    toast(message, 'error')
    cancelEdit(variables.id)
  },
})

const deleteMutation = useMutation({
  mutationFn: (id: string) => addressTypesService.remove(id),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['address-types'] })
    toast('Address type deleted successfully.', 'success')
    deleteConfirmId.value = null
  },
  onError: (error: unknown) => {
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to delete address type.')
    toast(message, 'error')
    deleteConfirmId.value = null
  },
})

function setEditContainer(id: string, el: HTMLElement | null) {
  editContainers.value[id] = el
}

function beginEdit(addressType: AddressType) {
  deleteConfirmId.value = null
  startEdit(addressType.id, addressType.name)
  nextTick(() => editContainers.value[addressType.id]?.querySelector('input')?.focus())
}

function saveEdit(id: string) {
  const name = getValue(id).trim()
  if (!name) {
    toast('Address type name is required.', 'error')
    return
  }
  updateMutation.mutate({ id, name })
}

function handleEditKeydown(event: KeyboardEvent, id: string) {
  if (event.key === 'Enter') {
    event.preventDefault()
    saveEdit(id)
  } else if (event.key === 'Escape') {
    cancelEdit(id)
  }
}

function startAdd() {
  addingNew.value = true
  newName.value = ''
  addError.value = ''
  nextTick(() => document.getElementById('address-type-add-input')?.focus())
}

function saveNew() {
  const name = newName.value.trim()
  if (!name) {
    addError.value = 'Address type name is required.'
    return
  }
  createMutation.mutate(name)
}

function cancelAdd() {
  addingNew.value = false
  newName.value = ''
  addError.value = ''
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
      title="Address Types"
      subtitle="Classify addresses used across customers and users."
      :breadcrumb="[{ label: 'Masters' }, { label: 'Address Types' }]"
    />

    <div v-if="addressTypesQuery.isLoading.value" class="rounded-lg border bg-white p-6 text-sm text-slate-500">
      Loading address types…
    </div>

    <div v-else class="rounded-lg border bg-white p-6 shadow-card">
      <div v-if="!addressTypes.length && !addingNew" class="mb-4 text-sm text-slate-500">
        No address types yet.
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <div
          v-for="addressType in addressTypes"
          :key="addressType.id"
          class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm"
        >
          <template v-if="isEditing(addressType.id)">
            <div :ref="(el) => setEditContainer(addressType.id, el as HTMLElement | null)" class="inline-flex items-center gap-1">
              <input
                :value="getValue(addressType.id)"
                type="text"
                class="w-36 rounded border px-2 py-0.5 text-sm"
                @input="setValue(addressType.id, ($event.target as HTMLInputElement).value)"
                @keydown="handleEditKeydown($event, addressType.id)"
              />
            </div>
          </template>
          <template v-else>
            <span class="font-medium text-slate-800">{{ addressType.name }}</span>
            <span v-if="addressType.usage_count" class="text-xs text-slate-500">({{ addressType.usage_count }})</span>
          </template>

          <template v-if="deleteConfirmId === addressType.id">
            <span class="ml-1 text-xs text-slate-600">Delete?</span>
            <button type="button" class="rounded bg-red-600 px-2 py-0.5 text-xs text-white" @click="deleteMutation.mutate(addressType.id)">Yes</button>
            <button type="button" class="rounded border px-2 py-0.5 text-xs" @click="deleteConfirmId = null">No</button>
          </template>
          <template v-else-if="!isEditing(addressType.id)">
            <PermissionGate module="address_types" action="edit">
              <button type="button" class="rounded p-0.5 text-slate-500 hover:bg-slate-200 hover:text-slate-700" title="Edit" @click="beginEdit(addressType)">
                <Pencil class="h-3.5 w-3.5" />
              </button>
            </PermissionGate>
            <PermissionGate module="address_types" action="delete">
              <button type="button" class="rounded p-0.5 text-slate-500 hover:bg-red-100 hover:text-red-700" title="Delete" @click="deleteConfirmId = addressType.id">
                <Trash2 class="h-3.5 w-3.5" />
              </button>
            </PermissionGate>
          </template>
          <template v-else>
            <button type="button" class="rounded p-0.5 text-slate-500 hover:bg-slate-200" title="Cancel" @click="cancelEdit(addressType.id)">
              <X class="h-3.5 w-3.5" />
            </button>
          </template>
        </div>

        <template v-if="addingNew">
          <div class="inline-flex items-center gap-2 rounded-full border border-brand-blue/30 bg-brand-accent/30 px-3 py-1.5">
            <input
              id="address-type-add-input"
              v-model="newName"
              type="text"
              class="w-40 rounded border px-2 py-0.5 text-sm"
              placeholder="Address type name"
              @keydown.enter.prevent="saveNew"
              @keydown.esc.prevent="cancelAdd"
            />
            <button type="button" class="rounded bg-brand-blue px-2 py-0.5 text-xs text-white" :disabled="createMutation.isPending.value" @click="saveNew">Save</button>
            <button type="button" class="rounded border px-2 py-0.5 text-xs" @click="cancelAdd">Cancel</button>
          </div>
          <p v-if="addError" class="w-full text-sm text-red-600">{{ addError }}</p>
        </template>

        <PermissionGate v-else module="address_types" action="create">
          <button
            type="button"
            class="inline-flex items-center gap-1 rounded-full border border-dashed border-slate-300 px-3 py-1.5 text-sm text-slate-600 hover:border-brand-blue hover:text-brand-blue"
            @click="startAdd"
          >
            <Plus class="h-4 w-4" />
            Add
          </button>
        </PermissionGate>
      </div>
    </div>
  </section>
</template>
