import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { queryClient } from '@/lib/queryClient'
import type { PermissionMap, User } from '@/types'

export const AUTH_STORAGE_KEY = 'shreeaxar_auth'
export type PermissionAction = 'view' | 'create' | 'edit' | 'delete'
export type PermissionInput = PermissionMap | string[] | Array<{ name?: string; module?: string; action?: string }>

const emptyActions = () => ({ view: false, create: false, edit: false, delete: false })
const normaliseAction = (action?: string): PermissionAction | null => {
  const value = action?.toLowerCase().replace(/^can/, '')
  if (value === 'view' || value === 'create' || value === 'edit' || value === 'delete') return value
  if (value === 'update') return 'edit'
  if (value === 'destroy' || value === 'remove') return 'delete'
  return null
}

function permissionNameToEntry(name: string): { module: string; action: PermissionAction } | null {
  if (name.includes(':')) {
    const [module, action] = name.split(':')
    const normalised = normaliseAction(action)
    return module && normalised ? { module, action: normalised } : null
  }
  if (name.includes('.')) {
    const [module, action] = name.split('.')
    const normalised = normaliseAction(action)
    return module && normalised ? { module, action: normalised } : null
  }
  const spaceIndex = name.indexOf(' ')
  if (spaceIndex > 0) {
    const action = name.slice(0, spaceIndex)
    const module = name.slice(spaceIndex + 1)
    const normalised = normaliseAction(action)
    return module && normalised ? { module, action: normalised } : null
  }
  return null
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(null)
  const expiresAt = ref<string | null>(null)
  const permissions = ref<PermissionMap>({})
  const isLoading = ref(false)
  const isAuthenticated = computed(() => Boolean(token.value))

  function parsePermissions(data?: PermissionInput | null): PermissionMap {
    const map: PermissionMap = {}
    const allow = (module: string, action: PermissionAction) => {
      map[module] = { ...(map[module] ?? emptyActions()), [action]: true }
    }

    if (Array.isArray(data)) {
      data.forEach((permission) => {
        if (typeof permission === 'string') {
          const entry = permissionNameToEntry(permission)
          if (entry) allow(entry.module, entry.action)
          return
        }
        const action = normaliseAction(permission.action)
        if (permission.module && action) allow(permission.module, action)
        else if (permission.name) {
          const entry = permissionNameToEntry(permission.name)
          if (entry) allow(entry.module, entry.action)
        }
      })
      return map
    }

    Object.entries(data ?? {}).forEach(([module, value]) => {
      if (typeof value === 'boolean') {
        if (value) {
          const entry = permissionNameToEntry(module)
          if (entry) allow(entry.module, entry.action)
          else allow(module, 'view')
        }
        return
      }
      map[module] = { ...emptyActions(), ...value }
    })
    return map
  }

  function permissionsFromUser(nextUser: User | null): PermissionInput {
    return [
      ...(nextUser?.permissions ?? []),
      ...(nextUser?.roles?.flatMap((role) => role.permissions ?? []) ?? []),
    ]
  }

  function setAuth(payload: { user: User | null; token: string | null; expiresAt?: string | null }) {
    user.value = payload.user
    token.value = payload.token
    expiresAt.value = payload.expiresAt ?? expiresAt.value
  }

  function setPermissions(data: PermissionInput | null | undefined) { permissions.value = parsePermissions(data) }
  function setUser(nextUser: User | null) { user.value = nextUser; setPermissions(permissionsFromUser(nextUser)) }
  function setToken(nextToken: string | null, nextExpiresAt?: string | null) { token.value = nextToken; if (nextExpiresAt !== undefined) expiresAt.value = nextExpiresAt }
  function setLoading(loading: boolean) { isLoading.value = loading }
  function updateUser(partial: Partial<User>) { if (user.value) user.value = { ...user.value, ...partial } }

  function clearAuth() {
    user.value = null
    token.value = null
    expiresAt.value = null
    permissions.value = {}
    isLoading.value = false
    localStorage.removeItem(AUTH_STORAGE_KEY)
    queryClient.clear()
  }

  function canView(module?: string) {
    if (!module || isSuperAdmin.value) return true
    return Boolean(permissions.value[module]?.view)
  }

  const userFullName = computed(() => user.value?.name || [user.value?.first_name, user.value?.last_name].filter(Boolean).join(' ') || 'ERP User')
  const userRoleName = computed(() => {
    const current = user.value
    if (!current) return 'User'
    return current.roles?.[0]?.name ?? (current as User & { role?: { name?: string } }).role?.name ?? 'User'
  })
  const userInitials = computed(() => userFullName.value.split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase() || 'EU')
  const isSuperAdmin = computed(() => userRoleName.value === 'Super Admin')
  const fullName = userFullName
  const roleName = userRoleName
  const initials = userInitials

  function logout() { clearAuth() }

  return { user, token, expiresAt, permissions, isAuthenticated, isLoading, userFullName, userInitials, userRoleName, fullName, roleName, initials, isSuperAdmin, setAuth, setPermissions, clearAuth, updateUser, setLoading, setUser, setToken, canView, logout }
}, {
  persist: {
    key: AUTH_STORAGE_KEY,
    storage: localStorage,
    pick: ['token', 'user', 'expiresAt'],
  },
})
