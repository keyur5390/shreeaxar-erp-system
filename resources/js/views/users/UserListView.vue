<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useDebounceFn } from '@vueuse/core'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { Eye, Pencil, Plus, Trash2, X } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import DataTable from '@/components/ui/DataTable.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import PermissionGate from '@/components/ui/PermissionGate.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue'
import { usersService } from '@/services/users.service'
import { rolesService } from '@/services/roles.service'
import { mastersService } from '@/services/masters.service'
import { STALE_TIME } from '@/lib/queryTimes'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth.store'
import { formatDate, getInitials } from '@/utils/formatters'
import type { UserListItem } from '@/types'

const router = useRouter()
const queryClient = useQueryClient()
const { toast } = useToast()
const authStore = useAuthStore()

const searchInput = ref('')
const debouncedSearch = ref('')
const roleId = ref('')
const departmentId = ref('')
const statusFilter = ref<'' | 'active' | 'inactive'>('')
const page = ref(1)
const deleteTarget = ref<UserListItem | null>(null)
const optimisticStatus = ref<Record<string, boolean>>({})

const applySearch = useDebounceFn((value: string) => {
  debouncedSearch.value = value
  page.value = 1
}, 400)

watch(searchInput, (value) => applySearch(value))

const filters = computed(() => ({
  search: debouncedSearch.value || undefined,
  role_id: roleId.value || undefined,
  department_id: departmentId.value || undefined,
  is_active: statusFilter.value === 'active' ? true : statusFilter.value === 'inactive' ? false : '',
  page: page.value,
}))

const usersQuery = useQuery({
  queryKey: computed(() => ['users', filters.value]),
  queryFn: () => usersService.getUsers(filters.value),
})

const rolesQuery = useQuery({
  queryKey: ['roles'],
  queryFn: () => rolesService.list(),
  staleTime: STALE_TIME.masters,
})

const departmentsQuery = useQuery({
  queryKey: ['departments'],
  queryFn: () => mastersService.getDepartments(),
  staleTime: STALE_TIME.masters,
})

const users = computed(() => usersQuery.data.value?.items ?? [])
const pagination = computed(() => usersQuery.data.value?.pagination)
const loadError = computed(() => usersQuery.isError.value
  ? (usersQuery.error.value instanceof Error ? usersQuery.error.value.message : 'Unable to load users.')
  : '')
const roles = computed(() => rolesQuery.data.value ?? [])
const departments = computed(() => departmentsQuery.data.value ?? [])

const hasActiveFilters = computed(() => Boolean(
  debouncedSearch.value || roleId.value || departmentId.value || statusFilter.value,
))

const emptyTitle = computed(() => hasActiveFilters.value ? 'No users match your filters' : 'No users yet')
const emptyDescription = computed(() => hasActiveFilters.value
  ? 'Try adjusting your search or filter criteria.'
  : 'Get started by adding your first team member.')

const tablePagination = computed(() => {
  if (!pagination.value) return undefined
  return {
    page: pagination.value.current_page,
    limit: pagination.value.per_page,
    total: pagination.value.total,
    totalPages: pagination.value.last_page,
  }
})

const columns = [
  { label: 'User', key: 'user' },
  { label: 'Role', key: 'role' },
  { label: 'Department', key: 'department' },
  { label: 'Status', key: 'status' },
  { label: 'Created', key: 'created_at' },
  { label: 'Actions', key: 'actions' },
] as const

function userStatus(user: UserListItem): boolean {
  return optimisticStatus.value[user.id] ?? user.is_active
}

function clearFilters() {
  searchInput.value = ''
  debouncedSearch.value = ''
  roleId.value = ''
  departmentId.value = ''
  statusFilter.value = ''
  page.value = 1
}

function goToUser(user: UserListItem) {
  router.push(`/users/${user.id}`)
}

const toggleMutation = useMutation({
  mutationFn: (user: UserListItem) => usersService.toggleStatus(user.id),
  onMutate: async (user) => {
    const current = userStatus(user)
    optimisticStatus.value = { ...optimisticStatus.value, [user.id]: !current }
  },
  onSuccess: (data) => {
    optimisticStatus.value = { ...optimisticStatus.value, [data.id]: data.is_active }
    queryClient.invalidateQueries({ queryKey: ['users'] })
    toast(data.is_active ? 'User activated successfully.' : 'User deactivated successfully.', 'success')
  },
  onError: (error: unknown, user) => {
    const reverted = { ...optimisticStatus.value }
    delete reverted[user.id]
    optimisticStatus.value = reverted
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to update user status.')
    toast(message, 'error')
  },
})

const deleteMutation = useMutation({
  mutationFn: (user: UserListItem) => usersService.remove(user.id),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['users'] })
    toast('User deactivated successfully.', 'success')
    deleteTarget.value = null
  },
  onError: (error: unknown) => {
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to delete user.')
    toast(message, 'error')
    deleteTarget.value = null
  },
})

function canToggle(user: UserListItem) {
  return authStore.user?.id !== user.id
}
</script>

<template>
  <section>
    <PageHeader
      title="User Management"
      subtitle="Manage team members, roles, and access."
      :breadcrumb="[{ label: 'Users' }]"
    >
      <template #action>
        <PermissionGate module="users" action="create">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg bg-brand-blue px-4 py-2 text-sm font-semibold text-white hover:bg-brand-blue/90"
            @click="router.push('/users/new')"
          >
            <Plus class="h-4 w-4" />
            Add New User
          </button>
        </PermissionGate>
      </template>
    </PageHeader>

    <div v-if="loadError" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      {{ loadError }}
    </div>

    <DataTable
      :columns="[...columns]"
      :data="users"
      :is-loading="usersQuery.isLoading.value"
      :skeleton-rows="8"
      :pagination="tablePagination"
      @page-change="page = $event"
      @row-click="goToUser"
    >
      <template #topBar>
        <div class="rounded-lg border bg-white p-4 shadow-card">
          <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-5">
            <label class="block text-sm">
              <span class="mb-1 block font-medium text-slate-700">Search</span>
              <input
                v-model="searchInput"
                type="search"
                placeholder="Name or email…"
                class="w-full rounded-md border px-3 py-2"
              />
            </label>
            <label class="block text-sm">
              <span class="mb-1 block font-medium text-slate-700">Role</span>
              <select v-model="roleId" class="w-full rounded-md border px-3 py-2" @change="page = 1">
                <option value="">All roles</option>
                <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
              </select>
            </label>
            <label class="block text-sm">
              <span class="mb-1 block font-medium text-slate-700">Department</span>
              <select v-model="departmentId" class="w-full rounded-md border px-3 py-2" @change="page = 1">
                <option value="">All departments</option>
                <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
              </select>
            </label>
            <label class="block text-sm">
              <span class="mb-1 block font-medium text-slate-700">Status</span>
              <select v-model="statusFilter" class="w-full rounded-md border px-3 py-2" @change="page = 1">
                <option value="">All</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </label>
            <div class="flex items-end">
              <button
                v-if="hasActiveFilters"
                type="button"
                class="inline-flex items-center gap-1 rounded-md border px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                @click="clearFilters"
              >
                <X class="h-4 w-4" />
                Clear All
              </button>
            </div>
          </div>
        </div>
      </template>

      <template v-if="!usersQuery.isLoading.value && !users.length" #emptyState>
        <EmptyState :title="emptyTitle" :description="emptyDescription">
          <template v-if="!hasActiveFilters" #action>
            <PermissionGate module="users" action="create">
              <button
                type="button"
                class="rounded-lg bg-brand-blue px-4 py-2 text-sm font-semibold text-white hover:bg-brand-blue/90"
                @click="router.push('/users/new')"
              >
                Add New User
              </button>
            </PermissionGate>
          </template>
        </EmptyState>
      </template>

      <template #cell-user="{ row }">
        <div class="flex items-center gap-3">
          <div
            v-if="(row as UserListItem).profile_image_url"
            class="h-10 w-10 shrink-0 overflow-hidden rounded-full bg-slate-100"
          >
            <img :src="(row as UserListItem).profile_image_url!" :alt="(row as UserListItem).first_name" class="h-full w-full object-cover" />
          </div>
          <div
            v-else
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-blue/10 text-xs font-semibold text-brand-blue"
          >
            {{ getInitials((row as UserListItem).first_name, (row as UserListItem).last_name) }}
          </div>
          <div>
            <p class="font-medium text-slate-900">{{ (row as UserListItem).first_name }} {{ (row as UserListItem).last_name }}</p>
            <p class="text-xs text-slate-500">{{ (row as UserListItem).email }}</p>
          </div>
        </div>
      </template>

      <template #cell-role="{ row }">
        <StatusBadge
          v-if="(row as UserListItem).role_name"
          :label="(row as UserListItem).role_name!"
          color="#1F4E79"
          size="sm"
        />
        <span v-else class="text-slate-400">—</span>
      </template>

      <template #cell-department="{ row }">
        {{ (row as UserListItem).department_name || '—' }}
      </template>

      <template #cell-status="{ row }">
        <StatusBadge
          :label="userStatus(row as UserListItem) ? 'Active' : 'Inactive'"
          :color="userStatus(row as UserListItem) ? '#16a34a' : '#94a3b8'"
          size="sm"
        />
      </template>

      <template #cell-created_at="{ row }">
        {{ formatDate((row as UserListItem).created_at) }}
      </template>

      <template #cell-actions="{ row }">
        <div class="flex items-center gap-2" @click.stop>
          <RouterLink
            :to="`/users/${(row as UserListItem).id}`"
            class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
            title="View"
          >
            <Eye class="h-4 w-4" />
          </RouterLink>
          <PermissionGate module="users" action="edit">
            <RouterLink
              :to="`/users/${(row as UserListItem).id}/edit`"
              class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
              title="Edit"
            >
              <Pencil class="h-4 w-4" />
            </RouterLink>
            <ToggleSwitch
              v-if="canToggle(row as UserListItem)"
              :model-value="userStatus(row as UserListItem)"
              :disabled="toggleMutation.isPending.value"
              @update:model-value="toggleMutation.mutate(row as UserListItem)"
            />
          </PermissionGate>
          <PermissionGate module="users" action="delete">
            <button
              v-if="canToggle(row as UserListItem)"
              type="button"
              class="rounded p-1.5 text-red-500 hover:bg-red-50"
              title="Delete"
              @click="deleteTarget = row as UserListItem"
            >
              <Trash2 class="h-4 w-4" />
            </button>
          </PermissionGate>
        </div>
      </template>
    </DataTable>

    <ConfirmDialog
      :open="Boolean(deleteTarget)"
      title="Delete user?"
      :description="deleteTarget ? `Deactivate ${deleteTarget.first_name} ${deleteTarget.last_name}? They will lose access immediately.` : ''"
      confirm-label="Delete"
      confirm-variant="destructive"
      require-type="DELETE"
      @cancel="deleteTarget = null"
      @confirm="deleteTarget && deleteMutation.mutate(deleteTarget)"
    />
  </section>
</template>
