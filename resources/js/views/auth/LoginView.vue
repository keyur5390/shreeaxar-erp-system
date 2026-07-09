<script setup lang="ts">
import { computed, onMounted, ref, watchEffect } from 'vue'
import { ErrorMessage, Field, Form } from 'vee-validate'
import { useRoute, useRouter } from 'vue-router'
import { Eye, EyeOff, Lock, Mail } from 'lucide-vue-next'
import * as yup from 'yup'
import type { AxiosError } from 'axios'
import AuthLayout from '@/components/layout/AuthLayout.vue'
import { authService } from '@/services/auth.service'
import { useAuthStore } from '@/stores/auth.store'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const showPassword = ref(false)
const alertMessage = ref('')
const shouldShake = ref(false)

const schema = yup.object({
  email: yup.string().email('Enter a valid email address.').required('Email is required.'),
  password: yup.string().required('Password is required.'),
  rememberMe: yup.boolean().default(false),
})

const redirectTo = computed(() => typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/') && !route.query.redirect.startsWith('//') ? route.query.redirect : '/dashboard')

function redirectAuthenticated() {
  if (authStore.isAuthenticated) router.replace(redirectTo.value)
}

function showAlert(message: string) {
  alertMessage.value = message
  shouldShake.value = false
  window.requestAnimationFrame(() => { shouldShake.value = true })
}

function retryAfterMinutes(error: AxiosError<{ retry_after?: number | string }>) {
  const retryAfter = error.response?.data?.retry_after ?? error.response?.headers['retry-after']
  const seconds = Number(retryAfter)
  return Number.isFinite(seconds) && seconds > 0 ? Math.max(1, Math.ceil(seconds / 60)) : 1
}

async function onSubmit(values: Record<string, unknown>) {
  alertMessage.value = ''
  try {
    const auth = await authService.login({ email: String(values.email ?? ''), password: String(values.password ?? ''), remember: Boolean(values.rememberMe) })
    authStore.setAuth({ token: auth.token ?? null, user: auth.user, expiresAt: auth.expires_at ?? auth.expiresAt ?? null })
    const currentAuth = await authService.me()
    authStore.setAuth({ token: currentAuth.token ?? auth.token ?? null, user: currentAuth.user, expiresAt: currentAuth.expires_at ?? currentAuth.expiresAt ?? auth.expires_at ?? auth.expiresAt ?? null })
    authStore.setPermissions(currentAuth.permissions ?? auth.permissions ?? {})
    await router.push(redirectTo.value)
  } catch (error) {
    const axiosError = error as AxiosError<{ message?: string; retry_after?: number | string }>
    if (axiosError.response?.status === 401) showAlert(axiosError.response.data?.message ?? 'Invalid email or password.')
    else if (axiosError.response?.status === 429) showAlert(`Too many attempts. Try again in ${retryAfterMinutes(axiosError)} minutes.`)
    else showAlert(axiosError.response?.data?.message ?? axiosError.message ?? 'Unable to sign in. Please try again.')
  }
}

onMounted(() => {
  document.title = 'Sign In - Shree Axar ERP'
  redirectAuthenticated()
})
watchEffect(redirectAuthenticated)
</script>

<template>
  <AuthLayout>
    <Form :validation-schema="schema" :initial-values="{ email: '', password: '', rememberMe: false }" class="space-y-5" @submit="onSubmit" v-slot="{ isSubmitting }">
      <div>
        <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-700">Email</label>
        <div class="relative">
          <Mail class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
          <Field id="email" name="email" type="email" autocomplete="email" class="w-full rounded-xl border border-slate-200 py-3 pl-11 pr-4 text-sm outline-none transition focus:border-[#1F4E79] focus:ring-4 focus:ring-blue-100" placeholder="you@company.com" />
        </div>
        <ErrorMessage name="email" class="mt-1 block text-xs font-medium text-red-600" />
      </div>

      <div>
        <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700">Password</label>
        <div class="relative">
          <Lock class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
          <Field id="password" name="password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" class="w-full rounded-xl border border-slate-200 py-3 pl-11 pr-11 text-sm outline-none transition focus:border-[#1F4E79] focus:ring-4 focus:ring-blue-100" placeholder="Enter your password" />
          <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700" :aria-label="showPassword ? 'Hide password' : 'Show password'" @click="showPassword = !showPassword">
            <EyeOff v-if="showPassword" class="h-5 w-5" /><Eye v-else class="h-5 w-5" />
          </button>
        </div>
        <ErrorMessage name="password" class="mt-1 block text-xs font-medium text-red-600" />
      </div>

      <div class="flex items-center justify-between text-sm">
        <label class="inline-flex items-center gap-2 font-medium text-slate-600"><Field name="rememberMe" type="checkbox" :value="true" class="h-4 w-4 rounded border-slate-300 text-[#1F4E79] focus:ring-[#1F4E79]" />Remember Me</label>
        <RouterLink to="/forgot-password" class="font-semibold text-[#1F4E79] hover:underline">Forgot password?</RouterLink>
      </div>

      <div v-if="alertMessage" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" :class="{ 'animate-shake': shouldShake }">{{ alertMessage }}</div>

      <button type="submit" class="flex w-full items-center justify-center rounded-xl bg-[#1F4E79] px-4 py-3 font-semibold text-white shadow-lg shadow-blue-900/20 transition hover:bg-[#173d61] disabled:cursor-not-allowed disabled:opacity-70" :disabled="isSubmitting">
        <span v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" />
        Sign In
      </button>
    </Form>
  </AuthLayout>
</template>

<style scoped>
@keyframes shake { 10%, 90% { transform: translateX(-1px); } 20%, 80% { transform: translateX(2px); } 30%, 50%, 70% { transform: translateX(-4px); } 40%, 60% { transform: translateX(4px); } }
.animate-shake { animation: shake 0.45s ease-in-out; }
</style>
