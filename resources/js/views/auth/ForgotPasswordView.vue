<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { ErrorMessage, Field, Form } from 'vee-validate'
import * as yup from 'yup'
import type { AxiosError } from 'axios'
import AuthLayout from '@/components/layout/AuthLayout.vue'
import OtpInput from '@/components/ui/OtpInput.vue'
import PasswordStrength from '@/components/ui/PasswordStrength.vue'
import { authService } from '@/services/auth.service'

const currentStep = ref(1)
const email = ref('')
const otp = ref('')
const resetToken = ref('')
const newPassword = ref('')
const alertMessage = ref('')
const countdown = ref(15 * 60)
const resendCooldown = ref(0)
let timer: ReturnType<typeof setInterval> | undefined

const emailSchema = yup.object({ email: yup.string().email('Enter a valid email address.').required('Email is required.') })
const passwordSchema = yup.object({
  newPassword: yup.string().min(8, 'Password must be at least 8 characters.').required('New password is required.'),
  newPasswordConfirmation: yup.string().oneOf([yup.ref('newPassword')], 'Passwords must match.').required('Please confirm your password.'),
})

const maskedEmail = computed(() => {
  const [local = '', domain = ''] = email.value.split('@')
  return local && domain ? `${local[0]}***@${domain}` : ''
})
const formattedCountdown = computed(() => `${String(Math.floor(countdown.value / 60)).padStart(2, '0')}:${String(countdown.value % 60).padStart(2, '0')}`)
const dots = computed(() => [1, 2, 3])

function messageFrom(error: unknown, fallback: string) {
  const axiosError = error as AxiosError<{ message?: string }>
  return axiosError.response?.data?.message ?? axiosError.message ?? fallback
}

function resetTimer() {
  countdown.value = 15 * 60
  resendCooldown.value = 60
}

function advance(step: number, form?: { resetForm: () => void }) {
  form?.resetForm()
  alertMessage.value = ''
  currentStep.value = step
}

async function sendCode(values: Record<string, unknown>, form: { resetForm: () => void }) {
  alertMessage.value = ''
  try {
    const nextEmail = String(values.email ?? '')
    await authService.forgotPassword(nextEmail)
    email.value = nextEmail
    otp.value = ''
    resetTimer()
    advance(2, form)
  } catch (error) {
    alertMessage.value = messageFrom(error, 'Unable to send reset code.')
  }
}

async function resendCode() {
  if (countdown.value > 0 || resendCooldown.value > 0) return
  try {
    await authService.forgotPassword(email.value)
    otp.value = ''
    resetTimer()
  } catch (error) {
    alertMessage.value = messageFrom(error, 'Unable to resend reset code.')
  }
}

async function verifyCode() {
  alertMessage.value = ''
  if (otp.value.length !== 6) {
    alertMessage.value = 'Enter the 6-digit verification code.'
    return
  }
  try {
    const response = await authService.verifyOtp(email.value, otp.value)
    resetToken.value = response.reset_token
    otp.value = ''
    advance(3)
  } catch (error) {
    alertMessage.value = messageFrom(error, 'Unable to verify OTP.')
  }
}

async function resetPassword(values: Record<string, unknown>) {
  alertMessage.value = ''
  if (!resetToken.value) {
    alertMessage.value = 'Session expired. Please restart.'
    return
  }
  try {
    await authService.resetPassword(resetToken.value, String(values.newPassword ?? ''), String(values.newPasswordConfirmation ?? ''))
    window.dispatchEvent(new CustomEvent('shreeaxar:toast', { detail: { message: 'Password reset successfully. Please sign in.', type: 'success' } }))
    setTimeout(() => { window.location.href = '/login' }, 2000)
  } catch (error) {
    alertMessage.value = messageFrom(error, 'Unable to reset password.')
  }
}

watch(currentStep, (step) => {
  if (step === 3 && !resetToken.value) alertMessage.value = 'Session expired. Please restart.'
})

onMounted(() => {
  document.title = 'Forgot Password - Shree Axar ERP'
  timer = setInterval(() => {
    if (countdown.value > 0) countdown.value--
    if (resendCooldown.value > 0) resendCooldown.value--
  }, 1000)
})
onUnmounted(() => { if (timer) clearInterval(timer) })
</script>

<template>
  <AuthLayout>
    <div class="mb-6 flex items-center justify-center gap-2" aria-label="Password reset progress">
      <span v-for="dot in dots" :key="dot" class="h-2.5 rounded-full transition-all" :class="dot === currentStep ? 'w-8 bg-brand-teal' : dot < currentStep ? 'w-2.5 bg-brand-light' : 'w-2.5 bg-slate-200'" />
      <span class="ml-2 text-xs font-semibold text-slate-500">{{ currentStep }}/3</span>
    </div>

    <Transition name="slide" mode="out-in">
      <Form v-if="currentStep === 1" key="step-1" :validation-schema="emailSchema" class="space-y-5" @submit="sendCode" v-slot="{ isSubmitting }">
        <div class="text-center"><h2 class="text-xl font-bold text-slate-900">Forgot password?</h2><p class="mt-1 text-sm text-slate-500">Enter your email and we will send a verification code.</p></div>
        <div><label for="reset-email" class="mb-1.5 block text-sm font-semibold text-slate-700">Email</label><Field id="reset-email" name="email" type="email" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-teal focus:ring-2 focus:ring-brand-accent" /><ErrorMessage name="email" class="mt-1 block text-xs font-medium text-red-600" /></div>
        <div v-if="alertMessage" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ alertMessage }}</div>
        <button type="submit" class="w-full rounded-xl bg-brand-teal px-4 py-3 font-semibold text-white hover:bg-brand-teal-dark disabled:opacity-70" :disabled="isSubmitting">{{ isSubmitting ? 'Sending...' : 'Send Code' }}</button>
        <RouterLink to="/login" class="block text-center text-sm font-semibold text-brand-teal hover:underline">Back to Login</RouterLink>
      </Form>

      <div v-else-if="currentStep === 2" key="step-2" class="space-y-5">
        <div class="text-center"><h2 class="text-xl font-bold text-slate-900">Verify code</h2><p class="mt-1 text-sm text-slate-500">We sent a 6-digit code to {{ maskedEmail }}.</p></div>
        <OtpInput v-model="otp" class="justify-center" />
        <p class="text-center text-sm font-medium text-slate-500">Code expires in {{ formattedCountdown }}</p>
        <div v-if="alertMessage" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ alertMessage }}</div>
        <button type="button" class="w-full rounded-xl bg-brand-teal px-4 py-3 font-semibold text-white hover:bg-brand-teal-dark" @click="verifyCode">Verify Code</button>
        <button type="button" class="w-full text-sm font-semibold text-brand-teal disabled:text-slate-400" :disabled="countdown > 0 || resendCooldown > 0" @click="resendCode">Resend code{{ resendCooldown > 0 ? ` in ${resendCooldown}s` : '' }}</button>
        <RouterLink to="/login" class="block text-center text-sm font-semibold text-brand-teal hover:underline">Back to Login</RouterLink>
      </div>

      <Form v-else key="step-3" :validation-schema="passwordSchema" class="space-y-5" @submit="resetPassword" v-slot="{ isSubmitting, values }">
        <div class="text-center"><h2 class="text-xl font-bold text-slate-900">Create new password</h2><p class="mt-1 text-sm text-slate-500">Choose a strong password for your ERP account.</p></div>
        <div v-if="alertMessage" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">{{ alertMessage }}</div>
        <div><label for="new-password" class="mb-1.5 block text-sm font-semibold text-slate-700">New password</label><Field id="new-password" v-model="newPassword" name="newPassword" type="password" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-teal focus:ring-2 focus:ring-brand-accent" /><PasswordStrength class="mt-2" :password="String(values.newPassword ?? newPassword)" /><ErrorMessage name="newPassword" class="mt-1 block text-xs font-medium text-red-600" /></div>
        <div><label for="confirm-password" class="mb-1.5 block text-sm font-semibold text-slate-700">Confirm password</label><Field id="confirm-password" name="newPasswordConfirmation" type="password" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-brand-teal focus:ring-2 focus:ring-brand-accent" /><ErrorMessage name="newPasswordConfirmation" class="mt-1 block text-xs font-medium text-red-600" /></div>
        <button type="submit" class="w-full rounded-xl bg-brand-teal px-4 py-3 font-semibold text-white hover:bg-brand-teal-dark disabled:opacity-70" :disabled="isSubmitting || !resetToken">{{ isSubmitting ? 'Resetting...' : 'Reset Password' }}</button>
        <RouterLink to="/login" class="block text-center text-sm font-semibold text-brand-teal hover:underline">Back to Login</RouterLink>
      </Form>
    </Transition>
  </AuthLayout>
</template>

<style scoped>
.slide-enter-active, .slide-leave-active { transition: all 0.25s ease; }
.slide-enter-from { opacity: 0; transform: translateX(24px); }
.slide-leave-to { opacity: 0; transform: translateX(-24px); }
</style>
