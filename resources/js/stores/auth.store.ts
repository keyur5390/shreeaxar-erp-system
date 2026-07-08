import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import type { PermissionMap, User } from '@/types'

export const AUTH_STORAGE_KEY = 'shreeaxar_auth'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(null)
  const permissions = ref<PermissionMap>({})
  const isLoading = ref(false)
  const isAuthenticated = computed(() => Boolean(token.value))

  function buildPermissions(nextUser: User | null): PermissionMap {
    const map: PermissionMap = {}
    nextUser?.permissions?.forEach((permission) => { map[permission.name] = true })
    nextUser?.roles?.forEach((role) => {
      role.permissions?.forEach((permission) => { map[permission.name] = true })
    })
    return map
  }

  function setAuth(payload: { user: User | null; token: string | null }) {
    user.value = payload.user
    token.value = payload.token
    permissions.value = buildPermissions(payload.user)
  }

  function setPermissions(map: PermissionMap) { permissions.value = map }
  function setUser(nextUser: User | null) { setAuth({ user: nextUser, token: token.value }) }
  function setToken(nextToken: string | null) { token.value = nextToken }
  function setLoading(loading: boolean) { isLoading.value = loading }
  function updateUser(partial: Partial<User>) { if (user.value) user.value = { ...user.value, ...partial } }

  function clearAuth() {
    user.value = null
    token.value = null
    permissions.value = {}
    isLoading.value = false
    localStorage.removeItem(AUTH_STORAGE_KEY)
  }

  function canView(module?: string) {
    if (!module) return true
    if (isSuperAdmin.value) return true
    return Boolean(permissions.value[`${module}.view`] || permissions.value[`${module}.canView`] || permissions.value[`${module}:view`] || permissions.value[module])
  }

  const fullName = computed(() => user.value?.name || [user.value?.first_name, user.value?.last_name].filter(Boolean).join(' ') || 'ERP User')
  const roleName = computed(() => user.value?.roles?.[0]?.name || 'User')
  const initials = computed(() => fullName.value.split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase() || 'EU')
  const isSuperAdmin = computed(() => roleName.value.toLowerCase() === 'super admin' || roleName.value.toLowerCase() === 'super-admin')

  function logout() { clearAuth() }

  return { user, token, permissions, isAuthenticated, isLoading, fullName, roleName, initials, isSuperAdmin, setAuth, setPermissions, clearAuth, updateUser, setLoading, setUser, setToken, canView, logout }
}, {
  persist: {
    key: AUTH_STORAGE_KEY,
    storage: localStorage,
    pick: ['token', 'user'],
  },
})
