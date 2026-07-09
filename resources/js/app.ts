import '@fontsource/inter'
import '../css/app.css'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'
import { VueQueryPlugin } from '@tanstack/vue-query'
import App from './App.vue'
import router from './router'
import { getCsrfToken } from './services/api'
import { queryClient } from './lib/queryClient'
import { useAuthStore, AUTH_STORAGE_KEY } from './stores/auth.store'
import { authService } from './services/auth.service'

const app = createApp(App)
const pinia = createPinia()
pinia.use(piniaPluginPersistedstate)
app.use(pinia).use(VueQueryPlugin, { queryClient }).use(router)

function notify(message: string, type: 'success' | 'error' | 'info' = 'info') {
  window.dispatchEvent(new CustomEvent('shreeaxar:toast', { detail: { message, type } }))
}

function shouldRefreshSoon(expiresAt: string | null) {
  if (!expiresAt) return false
  const expiry = new Date(expiresAt).getTime()
  return Number.isFinite(expiry) && expiry - Date.now() < 5 * 60 * 1000
}

async function refreshSessionIfNeeded() {
  const authStore = useAuthStore()
  if (!authStore.token || !shouldRefreshSoon(authStore.expiresAt)) return
  try {
    const auth = await authService.refresh()
    authStore.setToken(auth.token ?? authStore.token, auth.expires_at ?? auth.expiresAt ?? authStore.expiresAt)
    if (auth.user) authStore.updateUser(auth.user)
    if (auth.permissions) authStore.setPermissions(auth.permissions)
  } catch {
    authStore.clearAuth()
    router.push('/login')
  }
}

window.addEventListener('focus', refreshSessionIfNeeded)
window.addEventListener('storage', (event) => {
  if (event.key !== AUTH_STORAGE_KEY) return
  const authStore = useAuthStore()
  if (!event.newValue) {
    authStore.clearAuth()
    router.push('/login')
    return
  }

  try {
    const persisted = JSON.parse(event.newValue)
    const state = persisted?.state ?? persisted
    authStore.$patch({ token: state.token ?? null, user: state.user ?? null, expiresAt: state.expiresAt ?? null })
    if (state.token) {
      authService.me()
        .then((auth) => {
          authStore.setAuth({ user: auth.user ?? state.user, token: auth.token ?? state.token, expiresAt: auth.expires_at ?? auth.expiresAt ?? state.expiresAt })
          authStore.setPermissions(auth.permissions)
        })
        .catch(() => authStore.clearAuth())
    }
  } catch {
    notify('Unable to sync authentication state across tabs.', 'error')
  }
})

getCsrfToken()
  .catch((error) => console.error('Unable to initialize CSRF cookie.', error))
  .finally(() => app.mount('#app'))
