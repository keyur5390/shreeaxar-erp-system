<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth.store'
import { authService } from '@/services/auth.service'
import { useIdleTimer } from '@/composables/useIdleTimer'

const props = withDefaults(defineProps<{ idleMinutes?: number }>(), { idleMinutes: 15 })
const router = useRouter()
const authStore = useAuthStore()
const { isIdle, resetTimer } = useIdleTimer(props.idleMinutes)
const countdown = ref(60)
const isRefreshing = ref(false)
let interval: ReturnType<typeof window.setInterval> | null = null
const isOpen = computed(() => isIdle.value && authStore.isAuthenticated)

function clearCountdown() {
  if (interval) window.clearInterval(interval)
  interval = null
}

function toast(message: string, type: 'success' | 'error' | 'info' = 'info') {
  window.dispatchEvent(new CustomEvent('shreeaxar:toast', { detail: { message, type } }))
}

async function logout(auto = false) {
  clearCountdown()
  authStore.clearAuth()
  if (auto) toast('Your session timed out. Please sign in again.', 'error')
  await router.push('/login')
}

async function stayLoggedIn() {
  isRefreshing.value = true
  try {
    const auth = await authService.refresh()
    authStore.setAuth({ user: auth.user ?? authStore.user, token: auth.token ?? authStore.token, expiresAt: auth.expires_at ?? auth.expiresAt ?? authStore.expiresAt })
    if (auth.permissions) authStore.setPermissions(auth.permissions)
    resetTimer()
    clearCountdown()
  } catch {
    await logout()
  } finally {
    isRefreshing.value = false
  }
}

watch(isOpen, (open) => {
  clearCountdown()
  if (!open) return
  countdown.value = 60
  interval = window.setInterval(() => {
    countdown.value -= 1
    if (countdown.value <= 0) logout(true)
  }, 1000)
})
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-[100] grid place-items-center bg-slate-950/50 p-4">
    <section class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
      <h2 class="text-lg font-bold text-slate-900">Session timeout</h2>
      <p class="mt-2 text-sm text-slate-600">You have been inactive. You will be logged out in {{ countdown }} seconds.</p>
      <div class="mt-6 flex justify-end gap-3">
        <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700" @click="logout(false)">Log Out</button>
        <button type="button" class="rounded-xl bg-brand-teal px-4 py-2 text-sm font-semibold text-white hover:bg-brand-teal-dark disabled:opacity-70" :disabled="isRefreshing" @click="stayLoggedIn">{{ isRefreshing ? 'Refreshing...' : 'Stay Logged In' }}</button>
      </div>
    </section>
  </div>
</template>
