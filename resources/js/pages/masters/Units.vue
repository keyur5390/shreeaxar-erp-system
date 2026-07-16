<script setup lang="ts">
import { computed, ref } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { Pencil, Plus, Trash2 } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import SkeletonTable from '@/components/ui/SkeletonTable.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import PermissionGate from '@/components/ui/PermissionGate.vue'
import { unitsService } from '@/services/units.service'
import { useToast } from '@/composables/useToast'
import type { Unit } from '@/types'
import { ValidationError } from '@/services/api'

const queryClient = useQueryClient()
const { toast } = useToast()

const modalOpen = ref(false)
const editingUnit = ref<Unit | null>(null)
const unitCode = ref('')
const unitName = ref('')
const formError = ref('')
const deleteTarget = ref<Unit | null>(null)

const unitsQuery = useQuery({
  queryKey: ['units'],
  queryFn: () => unitsService.list(),
})

const units = computed(() => unitsQuery.data.value ?? [])

const saveMutation = useMutation({
  mutationFn: async () => {
    const code = unitCode.value.trim().toUpperCase()
    const name = unitName.value.trim()
    if (!code) throw new Error('Unit code is required.')
    if (!/^[A-Z0-9]+$/.test(code)) throw new Error('Code must contain only uppercase letters and numbers.')
    if (!name) throw new Error('Unit name is required.')
    const payload = { code, name }
    if (editingUnit.value) return unitsService.update(editingUnit.value.id, payload)
    return unitsService.create(payload)
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['units'] })
    toast(editingUnit.value ? 'Unit updated successfully.' : 'Unit created successfully.', 'success')
    closeModal()
  },
  onError: (error: unknown) => {
    if (error instanceof ValidationError) {
      formError.value = Object.values(error.errors).flat()[0] ?? error.message
      return
    }
    formError.value = error instanceof Error ? error.message : 'Unable to save unit.'
  },
})

const deleteMutation = useMutation({
  mutationFn: (unit: Unit) => unitsService.remove(unit.id),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['units'] })
    toast('Unit deleted successfully.', 'success')
    deleteTarget.value = null
  },
  onError: (error: unknown) => {
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to delete unit.')
    toast(message, 'error')
    deleteTarget.value = null
  },
})

function openCreateModal() {
  editingUnit.value = null
  unitCode.value = ''
  unitName.value = ''
  formError.value = ''
  modalOpen.value = true
}

function openEditModal(unit: Unit) {
  editingUnit.value = unit
  unitCode.value = unit.code
  unitName.value = unit.name
  formError.value = ''
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
  editingUnit.value = null
  unitCode.value = ''
  unitName.value = ''
  formError.value = ''
}

function onCodeInput(event: Event) {
  unitCode.value = (event.target as HTMLInputElement).value.toUpperCase().replace(/[^A-Z0-9]/g, '')
}
</script>

<template>
  <section>
    <PageHeader
      title="Units"
      subtitle="Manage product measurement units."
      :breadcrumb="[{ label: 'Masters' }, { label: 'Units' }]"
    >
      <template #action>
        <PermissionGate module="units" action="create">
          <button
            type="button"
            class="inline-flex items-center gap-1 rounded-lg bg-brand-blue px-4 py-2 text-sm font-semibold text-white hover:bg-brand-blue/90"
            @click="openCreateModal"
          >
            <Plus class="h-4 w-4" />
            Add Unit
          </button>
        </PermissionGate>
      </template>
    </PageHeader>

    <div v-if="unitsQuery.isLoading.value" class="rounded-lg border bg-white p-4">
      <SkeletonTable :cols="5" />
    </div>

    <div v-else-if="!units.length" class="rounded-lg border bg-white p-8 text-center text-sm text-slate-500">
      No units found.
    </div>

    <div v-else class="overflow-hidden rounded-lg border bg-white shadow-card">
      <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Code</th>
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Default</th>
            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Product Count</th>
            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="unit in units" :key="unit.id" class="hover:bg-slate-50">
            <td class="px-4 py-3 text-sm font-mono font-medium text-slate-900">{{ unit.code }}</td>
            <td class="px-4 py-3 text-sm text-slate-700">{{ unit.name }}</td>
            <td class="px-4 py-3 text-sm">
              <span
                v-if="unit.is_default"
                class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800"
              >
                Default
              </span>
              <span v-else class="text-slate-400">—</span>
            </td>
            <td class="px-4 py-3 text-sm text-slate-700">{{ unit.products_count ?? 0 }}</td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-end gap-2">
                <PermissionGate module="units" action="edit">
                  <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md border px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                    @click="openEditModal(unit)"
                  >
                    <Pencil class="h-3.5 w-3.5" />
                    Edit
                  </button>
                </PermissionGate>
                <PermissionGate v-if="!unit.is_default" module="units" action="delete">
                  <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50"
                    @click="deleteTarget = unit"
                  >
                    <Trash2 class="h-3.5 w-3.5" />
                    Delete
                  </button>
                </PermissionGate>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <Teleport to="body">
      <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4">
        <section class="w-full max-w-md rounded-lg bg-white p-6 shadow-card">
          <h2 class="text-lg font-semibold">{{ editingUnit ? 'Edit Unit' : 'Add Unit' }}</h2>
          <p class="mt-1 text-sm text-slate-500">Code must be uppercase letters and numbers only.</p>

          <label class="mt-4 block text-sm font-medium text-slate-700">
            Code
            <input
              :value="unitCode"
              type="text"
              class="mt-1 w-full rounded-md border px-3 py-2 font-mono uppercase disabled:bg-slate-100"
              placeholder="e.g. PCS"
              :disabled="Boolean(editingUnit?.is_default)"
              maxlength="20"
              @input="onCodeInput"
              @keyup.enter="saveMutation.mutate()"
            />
          </label>

          <label class="mt-4 block text-sm font-medium text-slate-700">
            Name
            <input
              v-model="unitName"
              type="text"
              class="mt-1 w-full rounded-md border px-3 py-2"
              placeholder="e.g. Pieces"
              @keyup.enter="saveMutation.mutate()"
            />
          </label>

          <p v-if="formError" class="mt-2 text-sm text-red-600">{{ formError }}</p>

          <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="rounded-md border px-4 py-2" @click="closeModal">Cancel</button>
            <button
              type="button"
              class="rounded-md bg-brand-blue px-4 py-2 text-white disabled:opacity-50"
              :disabled="saveMutation.isPending.value"
              @click="saveMutation.mutate()"
            >
              {{ saveMutation.isPending.value ? 'Saving…' : 'Save' }}
            </button>
          </div>
        </section>
      </div>
    </Teleport>

    <ConfirmDialog
      :open="Boolean(deleteTarget)"
      title="Delete unit?"
      :description="deleteTarget ? 'Delete ' + deleteTarget.name + '? This cannot be undone.' : ''"
      confirm-label="Delete"
      confirm-variant="destructive"
      @cancel="deleteTarget = null"
      @confirm="deleteTarget && deleteMutation.mutate(deleteTarget)"
    />
  </section>
</template>
