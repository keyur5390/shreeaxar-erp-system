<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { ErrorMessage, Field, Form } from 'vee-validate'
import * as yup from 'yup'
import { Pencil, Trash2 } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ToggleSwitch from '@/components/ui/ToggleSwitch.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import PermissionGate from '@/components/ui/PermissionGate.vue'
import PasswordStrength from '@/components/ui/PasswordStrength.vue'
import { usersService } from '@/services/users.service'
import { rolesService } from '@/services/roles.service'
import { authService } from '@/services/auth.service'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth.store'
import { formatDate, formatDateTime, getInitials } from '@/utils/formatters'
import type { ModulePermissionMatrix } from '@/types'

const route = useRoute()
const router = useRouter()
const queryClient = useQueryClient()
const { toast } = useToast()
const authStore = useAuthStore()

const userId = computed(() => String(route.params.id))
const isOwnProfile = computed(() => authStore.user?.id === userId.value)

const activeTab = ref<'profile' | 'addresses' | 'permissions' | 'password'>('profile')
const deleteOpen = ref(false)
const optimisticActive = ref<boolean | null>(null)

const tabs = computed(() => {
  const base = [
    { id: 'profile' as const, label: 'Profile Info' },
    { id: 'addresses' as const, label: 'Addresses' },
    { id: 'permissions' as const, label: 'Permissions' },
  ]
  if (isOwnProfile.value) {
    base.push({ id: 'password' as const, label: 'Change Password' })
  }
  return base
})

const userQuery = useQuery({
  queryKey: computed(() => ['users', userId.value]),
  queryFn: () => usersService.get(userId.value),
})

const user = computed(() => userQuery.data.value)
const roleId = computed(() => user.value?.role?.id ?? user.value?.roles[0]?.id)

const permissionsQuery = useQuery({
  queryKey: computed(() => ['roles', roleId.value, 'permissions']),
  queryFn: () => rolesService.getPermissions(roleId.value!),
  enabled: computed(() => Boolean(roleId.value)),
})

const permissionsMatrix = computed(() => permissionsQuery.data.value ?? [])

const isActive = computed(() => optimisticActive.value ?? user.value?.is_active ?? false)
const fullName = computed(() => user.value ? `${user.value.first_name} ${user.value.last_name}` : '')
const roleName = computed(() => user.value?.role?.name ?? user.value?.roles[0]?.name ?? '—')
const departmentName = computed(() => user.value?.department?.name ?? '—')

function formatModuleLabel(module: string) {
  return module.split('_').map((part) => part.charAt(0).toUpperCase() + part.slice(1)).join(' ')
}

const toggleMutation = useMutation({
  mutationFn: () => usersService.toggleStatus(userId.value),
  onMutate: () => {
    optimisticActive.value = !isActive.value
  },
  onSuccess: (data) => {
    optimisticActive.value = data.is_active
    queryClient.invalidateQueries({ queryKey: ['users'] })
    queryClient.invalidateQueries({ queryKey: ['users', userId.value] })
    toast(data.is_active ? 'User activated successfully.' : 'User deactivated successfully.', 'success')
  },
  onError: (error: unknown) => {
    optimisticActive.value = null
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to update status.')
    toast(message, 'error')
  },
})

const deleteMutation = useMutation({
  mutationFn: () => usersService.remove(userId.value),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['users'] })
    toast('User deactivated successfully.', 'success')
    router.push('/users')
  },
  onError: (error: unknown) => {
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to delete user.')
    toast(message, 'error')
    deleteOpen.value = false
  },
})

const passwordSchema = yup.object({
  current_password: yup.string().required('Current password is required.'),
  new_password: yup.string().min(8, 'Password must be at least 8 characters.').required('New password is required.'),
  new_password_confirmation: yup.string()
    .oneOf([yup.ref('new_password')], 'Passwords must match.')
    .required('Please confirm your password.'),
})

const passwordError = ref('')

async function changePassword(values: Record<string, unknown>, form: { resetForm: () => void }) {
  passwordError.value = ''
  try {
    await authService.changePassword(
      String(values.current_password ?? ''),
      String(values.new_password ?? ''),
      String(values.new_password_confirmation ?? ''),
    )
    toast('Password changed. You will be logged out shortly.', 'success')
    form.resetForm()
    setTimeout(() => {
      authStore.clearAuth()
      router.push('/login')
    }, 2000)
  } catch (error: unknown) {
    passwordError.value = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to change password.')
  }
}

function permissionRowClass(row: ModulePermissionMatrix) {
  const enabled = row.view || row.create || row.edit || row.delete
  return enabled ? 'text-slate-900' : 'text-slate-400'
}
</script>

<template>
  <section>
    <PageHeader
      :title="fullName || 'User Profile'"
      subtitle="View user details, addresses, and permissions."
      :breadcrumb="[{ label: 'Users', href: '/users' }, { label: 'Profile' }]"
    >
      <template #action>
        <div class="flex flex-wrap items-center gap-2">
          <PermissionGate module="users" action="edit">
            <button
              type="button"
              class="inline-flex items-center gap-1 rounded-md border px-3 py-2 text-sm hover:bg-slate-50"
              @click="router.push(`/users/${userId}/edit`)"
            >
              <Pencil class="h-4 w-4" />
              Edit
            </button>
            <ToggleSwitch
              v-if="!isOwnProfile"
              :model-value="isActive"
              :disabled="toggleMutation.isPending.value"
              label="Toggle user status"
              @update:model-value="toggleMutation.mutate()"
            />
          </PermissionGate>
          <PermissionGate module="users" action="delete">
            <button
              v-if="!isOwnProfile"
              type="button"
              class="inline-flex items-center gap-1 rounded-md border border-red-200 px-3 py-2 text-sm text-red-700 hover:bg-red-50"
              @click="deleteOpen = true"
            >
              <Trash2 class="h-4 w-4" />
              Delete
            </button>
          </PermissionGate>
        </div>
      </template>
    </PageHeader>

    <div v-if="userQuery.isLoading.value" class="rounded-lg border bg-white p-8 text-center text-sm text-slate-500">
      Loading profile…
    </div>

    <template v-else-if="user">
      <div class="mb-6 flex flex-wrap items-center gap-4 rounded-lg border bg-white p-6 shadow-card">
        <div
          v-if="user.profile_image_url"
          class="h-20 w-20 overflow-hidden rounded-full bg-slate-100"
        >
          <img :src="user.profile_image_url" :alt="fullName" class="h-full w-full object-cover" />
        </div>
        <div
          v-else
          class="flex h-20 w-20 items-center justify-center rounded-full bg-brand-blue/10 text-lg font-semibold text-brand-blue"
        >
          {{ getInitials(user.first_name, user.last_name) }}
        </div>
        <div class="min-w-0 flex-1">
          <h2 class="text-xl font-semibold text-slate-900">{{ fullName }}</h2>
          <p class="text-sm text-slate-500">{{ user.email }}</p>
          <div class="mt-2 flex flex-wrap gap-2">
            <StatusBadge :label="roleName" color="#1F4E79" size="sm" />
            <StatusBadge :label="departmentName" color="#6366f1" size="sm" />
            <StatusBadge
              :label="isActive ? 'Active' : 'Inactive'"
              :color="isActive ? '#16a34a' : '#94a3b8'"
              size="sm"
            />
          </div>
        </div>
      </div>

      <div class="mb-4 flex flex-wrap gap-1 rounded-lg border bg-slate-100 p-1">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          type="button"
          class="rounded-md px-4 py-2 text-sm font-medium transition-colors"
          :class="activeTab === tab.id ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
          @click="activeTab = tab.id"
        >
          {{ tab.label }}
        </button>
      </div>

      <div v-if="activeTab === 'profile'" class="rounded-lg border bg-white p-6 shadow-card">
        <dl class="grid gap-4 sm:grid-cols-2">
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">First Name</dt>
            <dd class="mt-1 text-sm text-slate-900">{{ user.first_name }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Last Name</dt>
            <dd class="mt-1 text-sm text-slate-900">{{ user.last_name }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email</dt>
            <dd class="mt-1 text-sm text-slate-900">{{ user.email }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contact Number</dt>
            <dd class="mt-1 text-sm text-slate-900">{{ user.contact_number || '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Department</dt>
            <dd class="mt-1 text-sm text-slate-900">{{ departmentName }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Role</dt>
            <dd class="mt-1 text-sm text-slate-900">{{ roleName }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Created</dt>
            <dd class="mt-1 text-sm text-slate-900">{{ formatDateTime(user.created_at) }}</dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Last Updated</dt>
            <dd class="mt-1 text-sm text-slate-900">{{ formatDateTime(user.updated_at) }}</dd>
          </div>
        </dl>
      </div>

      <div v-else-if="activeTab === 'addresses'" class="space-y-4">
        <article
          v-for="(address, index) in user.addresses ?? []"
          :key="address.id ?? index"
          class="rounded-lg border bg-white p-6 shadow-card"
        >
          <h3 class="mb-3 text-sm font-semibold text-slate-900">
            {{ address.address_type_name || `Address ${index + 1}` }}
          </h3>
          <p class="text-sm text-slate-700">{{ address.address_line_1 }}</p>
          <p v-if="address.address_line_2" class="text-sm text-slate-700">{{ address.address_line_2 }}</p>
          <p class="mt-1 text-sm text-slate-500">
            {{ [address.city, address.state_name, address.country_name].filter(Boolean).join(', ') }}
            <span v-if="address.postal_code"> · {{ address.postal_code }}</span>
          </p>
        </article>
        <p v-if="!(user.addresses?.length)" class="rounded-lg border bg-white p-8 text-center text-sm text-slate-500">
          No addresses on file.
        </p>
      </div>

      <div v-else-if="activeTab === 'permissions'" class="overflow-hidden rounded-lg border bg-white shadow-card">
        <div v-if="permissionsQuery.isLoading.value" class="p-8 text-center text-sm text-slate-500">
          Loading permissions…
        </div>
        <table v-else class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Module</th>
              <th v-for="action in ['view', 'create', 'edit', 'delete']" :key="action" class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">
                {{ action }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="row in permissionsMatrix" :key="row.module" :class="permissionRowClass(row)">
              <td class="px-4 py-3 text-sm font-medium">{{ formatModuleLabel(row.module) }}</td>
              <td v-for="action in ['view', 'create', 'edit', 'delete']" :key="action" class="px-4 py-3 text-center text-sm">
                {{ row[action as keyof ModulePermissionMatrix] ? '✓' : '—' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else-if="activeTab === 'password'" class="max-w-md rounded-lg border bg-white p-6 shadow-card">
        <Form
          :validation-schema="passwordSchema"
          class="space-y-4"
          @submit="changePassword"
          v-slot="{ isSubmitting, values }"
        >
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Current Password</label>
            <Field name="current_password" type="password" class="w-full rounded-md border px-3 py-2" />
            <ErrorMessage name="current_password" class="mt-1 block text-xs text-red-600" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">New Password</label>
            <Field name="new_password" type="password" class="w-full rounded-md border px-3 py-2" />
            <PasswordStrength class="mt-2" :password="String(values.new_password ?? '')" />
            <ErrorMessage name="new_password" class="mt-1 block text-xs text-red-600" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Confirm New Password</label>
            <Field name="new_password_confirmation" type="password" class="w-full rounded-md border px-3 py-2" />
            <ErrorMessage name="new_password_confirmation" class="mt-1 block text-xs text-red-600" />
          </div>
          <p v-if="passwordError" class="text-sm text-red-600">{{ passwordError }}</p>
          <button
            type="submit"
            class="rounded-md bg-brand-blue px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
            :disabled="isSubmitting"
          >
            {{ isSubmitting ? 'Updating…' : 'Change Password' }}
          </button>
        </Form>
      </div>
    </template>

    <ConfirmDialog
      :open="deleteOpen"
      title="Delete user?"
      :description="user ? `Deactivate ${user.first_name} ${user.last_name}? They will lose access immediately.` : ''"
      confirm-label="Delete"
      confirm-variant="destructive"
      require-type="DELETE"
      @cancel="deleteOpen = false"
      @confirm="deleteMutation.mutate()"
    />
  </section>
</template>
