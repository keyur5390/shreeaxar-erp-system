import '@fontsource/inter'
import '../css/app.css'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate'
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import App from './App.vue'
import router from './router'
import { getCsrfToken } from './services/api'

const app = createApp(App)
const pinia = createPinia()
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      staleTime: 30000,
    },
  },
})

pinia.use(piniaPluginPersistedstate)
app.use(pinia).use(VueQueryPlugin, { queryClient }).use(router)

getCsrfToken()
  .catch((error) => console.error('Unable to initialize CSRF cookie.', error))
  .finally(() => app.mount('#app'))
