<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SkeletonTable from '@/components/ui/SkeletonTable.vue'
import { rolesService } from '@/services/roles.service'
import { useToast } from '@/composables/useToast'
import type { ModulePermissionMatrix, PermissionAction } from '@/types'

const route = useRoute()
const router = useRouter()
const queryClient = useQueryClient()
const { toast } = useToast()

const roleId = computed(() => String(route.params.id))
const actions: PermissionAction[] = ['view', 'create', 'edit', 'delete']

const roleQuery = useQuery({
  queryKey: computed(() => ['roles', roleId.value]),
  queryFn: () => rolesService.get(roleId.value),
})

const permissionsQuery = useQuery({
  queryKey: computed(() => ['roles', roleId.value, 'permissions']),
  queryFn: () => rolesService.getPermissions(roleId.value),
})

const matrix = ref<ModulePermissionMatrix[]>([])
const savedSnapshot = ref('')

watch(
  () => permissionsQuery.data.value,
  (value) => {
    if (!value) return
    matrix.value = value.map((row) => ({ ...row }))
    enforceDashboardView()
    savedSnapshot.value = JSON.stringify(matrix.value)
  },
  { immediate: true },
)

const isDirty = computed(() => JSON.stringify(matrix.value) !== savedSnapshot.value)
const roleName = computed(() => roleQuery.data.value?.name ?? 'Role')

function formatModuleLabel(module: string) {
  return module.split('_').map((part) => part.charAt(0).toUpperCase() + part.slice(1)).join(' ')
}

function isDashboardRow(module: string) {
  return module === 'dashboard'
}

function enforceDashboardView() {
  const dashboard = matrix.value.find((row) => isDashboardRow(row.module))
  if (dashboard) dashboard.view = true
}

function setAction(module: string, action: PermissionAction, value: boolean) {
  const row = matrix.value.find((item) => item.module === module)
  if (!row) return
  if (isDashboardRow(module) && action === 'view') {
    row.view = true
    return
  }
  row[action] = value
}

function isRowFullyEnabled(row: ModulePermissionMatrix) {
  if (isDashboardRow(row.module)) {
    return row.create && row.edit && row.delete
  }
  return row.view && row.create && row.edit && row.delete
}

function toggleRowAll(module: string, value: boolean) {
  actions.forEach((action) => setAction(module, action, value))
}

function isColumnFullyEnabled(action: PermissionAction) {
  return matrix.value.every((row) => {
    if (isDashboardRow(row.module) && action === 'view') return true
    return row[action]
  })
}

function toggleColumnAll(action: PermissionAction, value: boolean) {
  matrix.value.forEach((row) => setAction(row.module, action, value))
}

function resetChanges() {
  if (!permissionsQuery.data.value) return
  matrix.value = permissionsQuery.data.value.map((row) => ({ ...row }))
  enforceDashboardView()
}

const saveMutation = useMutation({
  mutationFn: () => rolesService.updatePermissions(roleId.value, matrix.value),
  onSuccess: (data) => {
    matrix.value = data.map((row) => ({ ...row }))
    enforceDashboardView()
    savedSnapshot.value = JSON.stringify(matrix.value)
    queryClient.invalidateQueries({ queryKey: ['roles'] })
    queryClient.invalidateQueries({ queryKey: ['roles', roleId.value, 'permissions'] })
    toast('Permissions saved successfully.', 'success')
  },
  onError: (error: unknown) => {
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to save permissions.')
    toast(message, 'error')
  },
})
</script>

<template>
  <section class="pb-24">
    <PageHeader
      :title="`Manage Permissions — ${roleName}`"
      subtitle="Toggle module access. Dashboard view is always enabled."
      :breadcrumb="[{ label: 'Masters' }, { label: 'Roles', href: '/masters/roles' }, { label: 'Permissions' }]"
    />

    <div v-if="permissionsQuery.isLoading.value || roleQuery.isLoading.value" class="rounded-lg border bg-white p-4">
      <SkeletonTable :cols="6" />
    </div>

    <div v-else class="overflow-hidden rounded-lg border bg-white shadow-card">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Module</th>
              <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">All</th>
              <th v-for="action in actions" :key="action" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">
                <div class="flex flex-col items-center gap-2">
                  <span>{{ action.charAt(0).toUpperCase() + action.slice(1) }}</span>
                  <ToggleSwitch
                    :model-value="isColumnFullyEnabled(action)"
                    :label="`Toggle all ${action}`"
                    @update:model-value="toggleColumnAll(action, $event)"
                  />
                </div>
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="row in matrix" :key="row.module" class="hover:bg-slate-50">
              <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ formatModuleLabel(row.module) }}</td>
              <td class="px-4 py-3 text-center">
                <ToggleSwitch
                  :model-value="isRowFullyEnabled(row)"
                  :label="`Toggle all for ${row.module}`"
                  @update:model-value="toggleRowAll(row.module, $event)"
                />
              </td>
              <td v-for="action in actions" :key="`${row.module}-${action}`" class="px-4 py-3 text-center">
                <ToggleSwitch
                  :model-value="row[action]"
                  :disabled="isDashboardRow(row.module) && action === 'view'"
                  :label="`${action} ${row.module}`"
                  @update:model-value="setAction(row.module, action, $event)"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div
      class="fixed inset-x-0 bottom-0 z-40 border-t bg-white/95 backdrop-blur"
      :class="isDirty ? 'border-amber-200 shadow-[0_-8px_24px_rgba(15,23,42,0.08)]' : 'border-slate-200'"
    >
      <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-4">
        <div class="text-sm">
          <span v-if="isDirty" class="font-medium text-amber-700">Unsaved changes</span>
          <span v-else class="text-slate-500">All changes saved</span>
        </div>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="rounded-md border px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50"
            :disabled="!isDirty || saveMutation.isPending.value"
            @click="resetChanges"
          >
            Reset
          </button>
          <button
            type="button"
            class="rounded-md border px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            @click="router.push('/masters/roles')"
          >
            Back
          </button>
          <button
            type="button"
            class="rounded-md bg-brand-blue px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
            :disabled="!isDirty || saveMutation.isPending.value"
            @click="saveMutation.mutate()"
          >
            {{ saveMutation.isPending.value ? 'Saving…' : 'Save Permissions' }}
          </button>
        </div>
      </div>
    </div>
  </section>
</template>
