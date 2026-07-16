<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { Pencil, Shield, Trash2 } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import SkeletonTable from '@/components/ui/SkeletonTable.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import PermissionGate from '@/components/ui/PermissionGate.vue'
import { rolesService } from '@/services/roles.service'
import { STALE_TIME } from '@/lib/queryTimes'
import { useToast } from '@/composables/useToast'
import type { Role } from '@/types'
import { ValidationError } from '@/services/api'

const router = useRouter()
const queryClient = useQueryClient()
const { toast } = useToast()

const modalOpen = ref(false)
const editingRole = ref<Role | null>(null)
const roleName = ref('')
const formError = ref('')
const deleteTarget = ref<Role | null>(null)

const rolesQuery = useQuery({
  queryKey: ['roles'],
  queryFn: () => rolesService.list(),
  staleTime: STALE_TIME.masters,
})

const roles = computed(() => rolesQuery.data.value ?? [])

const saveMutation = useMutation({
  mutationFn: async () => {
    const name = roleName.value.trim()
    if (!name) throw new Error('Role name is required.')
    if (editingRole.value) return rolesService.update(editingRole.value.id, { name })
    return rolesService.create({ name })
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['roles'] })
    toast(editingRole.value ? 'Role updated successfully.' : 'Role created successfully.', 'success')
    closeModal()
  },
  onError: (error: unknown) => {
    if (error instanceof ValidationError) {
      formError.value = Object.values(error.errors).flat()[0] ?? error.message
      return
    }
    formError.value = error instanceof Error ? error.message : 'Unable to save role.'
  },
})

const deleteMutation = useMutation({
  mutationFn: (role: Role) => rolesService.remove(role.id),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['roles'] })
    toast('Role deleted successfully.', 'success')
    deleteTarget.value = null
  },
  onError: (error: unknown) => {
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to delete role.')
    toast(message, 'error')
    deleteTarget.value = null
  },
})

function openCreateModal() {
  editingRole.value = null
  roleName.value = ''
  formError.value = ''
  modalOpen.value = true
}

function openEditModal(role: Role) {
  editingRole.value = role
  roleName.value = role.name
  formError.value = ''
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
  editingRole.value = null
  roleName.value = ''
  formError.value = ''
}

function managePermissions(role: Role) {
  router.push(`/masters/roles/${role.id}/permissions`)
}
</script>

<template>
  <section>
    <PageHeader
      title="Roles & Permissions"
      subtitle="Manage access roles and assign permissions per module."
      :breadcrumb="[{ label: 'Masters' }, { label: 'Roles' }]"
    >
      <template #action>
        <PermissionGate module="roles" action="create">
          <button
            type="button"
            class="rounded-lg bg-brand-blue px-4 py-2 text-sm font-semibold text-white hover:bg-brand-blue/90"
            @click="openCreateModal"
          >
            Add Role
          </button>
        </PermissionGate>
      </template>
    </PageHeader>

    <div v-if="rolesQuery.isLoading.value" class="rounded-lg border bg-white p-4">
      <SkeletonTable :cols="3" />
    </div>

    <div v-else-if="!roles.length" class="rounded-lg border bg-white p-8 text-center text-sm text-slate-500">
      No roles found.
    </div>

    <div v-else class="overflow-hidden rounded-lg border bg-white shadow-card">
          <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Role</th>
                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Users Count</th>
                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="role in roles" :key="role.id" class="hover:bg-slate-50">
                <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ role.name }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ role.users_count ?? 0 }}</td>
                <td class="px-4 py-3">
                  <div class="flex items-center justify-end gap-2">
                    <PermissionGate module="roles" action="edit">
                      <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md border px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                        @click="managePermissions(role)"
                      >
                        <Shield class="h-3.5 w-3.5" />
                        Permissions
                      </button>
                      <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md border px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                        @click="openEditModal(role)"
                      >
                        <Pencil class="h-3.5 w-3.5" />
                        Edit
                      </button>
                    </PermissionGate>
                    <PermissionGate module="roles" action="delete">
                      <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50"
                        @click="deleteTarget = role"
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
          <h2 class="text-lg font-semibold">{{ editingRole ? 'Edit Role' : 'Add Role' }}</h2>
          <p class="mt-1 text-sm text-slate-500">Enter a unique role name.</p>
          <label class="mt-4 block text-sm font-medium text-slate-700">
            Role Name
            <input
              v-model="roleName"
              type="text"
              class="mt-1 w-full rounded-md border px-3 py-2"
              placeholder="e.g. Sales Staff"
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
      title="Delete role?"
      :description="deleteTarget ? 'Delete ' + deleteTarget.name + '? This cannot be undone.' : ''"
      confirm-label="Delete"
      confirm-variant="destructive"
      @cancel="deleteTarget = null"
      @confirm="deleteTarget && deleteMutation.mutate(deleteTarget)"
    />
  </section>
</template>
