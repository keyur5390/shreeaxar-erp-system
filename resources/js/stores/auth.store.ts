import { ref } from 'vue'
import { defineStore } from 'pinia'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const isAuthenticated = ref(false)
  function setUser(nextUser: User | null) { user.value = nextUser; isAuthenticated.value = Boolean(nextUser) }
  return { user, isAuthenticated, setUser }
})
