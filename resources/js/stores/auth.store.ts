import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import type { PermissionMap, User } from '@/types'

const TOKEN_KEY = 'auth_token'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem(TOKEN_KEY))
  const isAuthenticated = computed(() => Boolean(token.value))
  const permissions = ref<PermissionMap>({})

  function buildPermissions(nextUser: User | null): PermissionMap {
    const map: PermissionMap = {}
    nextUser?.permissions?.forEach((permission) => { map[permission.name] = true })
    nextUser?.roles?.forEach((role) => {
      role.permissions?.forEach((permission) => { map[permission.name] = true })
    })
    return map
  }

  function setUser(nextUser: User | null) {
    user.value = nextUser
    permissions.value = buildPermissions(nextUser)
  }

  function setToken(nextToken: string | null) {
    token.value = nextToken
    if (nextToken) localStorage.setItem(TOKEN_KEY, nextToken)
    else localStorage.removeItem(TOKEN_KEY)
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

  function logout() {
    setToken(null)
    setUser(null)
  }

  return { user, token, isAuthenticated, permissions, fullName, roleName, initials, isSuperAdmin, setUser, setToken, canView, logout }
})
