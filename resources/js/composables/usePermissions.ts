import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth.store'
import type { PermissionActions } from '@/types'

const allFalse: PermissionActions = { view: false, create: false, edit: false, delete: false }

export function usePermission(module: string) {
  const authStore = useAuthStore()
  return computed<PermissionActions>(() => authStore.isSuperAdmin ? { view: true, create: true, edit: true, delete: true } : authStore.permissions[module] ?? allFalse)
}

export function useCanCreate(module: string) { const permission = usePermission(module); return computed(() => permission.value.create) }
export function useCanEdit(module: string) { const permission = usePermission(module); return computed(() => permission.value.edit) }
export function useCanDelete(module: string) { const permission = usePermission(module); return computed(() => permission.value.delete) }
export function useIsAdmin() { const authStore = useAuthStore(); return computed(() => authStore.userRoleName === 'Super Admin') }

export function usePermissions() {
  const authStore = useAuthStore()
  return {
    can: (permission: string) => {
      const [module, action = 'view'] = permission.includes(':') ? permission.split(':') : permission.split('.')
      const permissionSet = authStore.permissions[module]
      return authStore.isSuperAdmin || Boolean(permissionSet?.[action as keyof PermissionActions])
    },
  }
}
