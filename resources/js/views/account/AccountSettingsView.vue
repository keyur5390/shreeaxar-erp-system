<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { useForm, ErrorMessage, Field, Form } from 'vee-validate'
import * as yup from 'yup'
import PageHeader from '@/components/ui/PageHeader.vue'
import ImageUpload from '@/components/ui/ImageUpload.vue'
import PasswordStrength from '@/components/ui/PasswordStrength.vue'
import { usersService } from '@/services/users.service'
import { authService } from '@/services/auth.service'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth.store'
import { ValidationError } from '@/services/api'
import { getInitials } from '@/utils/formatters'

defineOptions({ name: 'AccountSettingsView' })

const router = useRouter()
const queryClient = useQueryClient()
const { toast } = useToast()
const authStore = useAuthStore()

const activeTab = ref<'profile' | 'password'>('profile')
const avatarFile = ref<File | null>(null)
const removeAvatar = ref(false)
const profileError = ref('')
const passwordError = ref('')

const userId = computed(() => authStore.user?.id ?? '')

const userQuery = useQuery({
  queryKey: computed(() => ['users', userId.value]),
  queryFn: () => usersService.get(userId.value),
  enabled: computed(() => Boolean(userId.value)),
})

const user = computed(() => userQuery.data.value)

const profileSchema = yup.object({
  first_name: yup.string().required('First name is required.').max(255),
  last_name: yup.string().required('Last name is required.').max(255),
  contact_number: yup.string().max(50).nullable(),
})

const { handleSubmit: handleProfileSubmit, meta: profileMeta, resetForm: resetProfileForm } = useForm({
  validationSchema: profileSchema,
  initialValues: {
    first_name: '',
    last_name: '',
    contact_number: '',
  },
})

watch(user, (nextUser) => {
  if (!nextUser) return
  resetProfileForm({
    values: {
      first_name: nextUser.first_name,
      last_name: nextUser.last_name,
      contact_number: nextUser.contact_number ?? '',
    },
  })
}, { immediate: true })

const canSaveProfile = computed(() => profileMeta.value.dirty || avatarFile.value !== null || removeAvatar.value)

const profileMutation = useMutation({
  mutationFn: (values: { first_name: string; last_name: string; contact_number: string }) =>
    usersService.update(userId.value, values, avatarFile.value, removeAvatar.value),
  onSuccess: (data) => {
    queryClient.invalidateQueries({ queryKey: ['users', userId.value] })
    authStore.updateUser({
      first_name: data.first_name,
      last_name: data.last_name,
      contact_number: data.contact_number,
      profile_image_url: data.profile_image_url,
    })
    avatarFile.value = null
    removeAvatar.value = false
    profileError.value = ''
    toast('Profile updated successfully.', 'success')
  },
  onError: (error: unknown) => {
    if (error instanceof ValidationError) {
      profileError.value = error.message
      return
    }
    profileError.value = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to update profile.')
  },
})

const onProfileSubmit = handleProfileSubmit((values) => {
  profileMutation.mutate({
    first_name: values.first_name,
    last_name: values.last_name,
    contact_number: values.contact_number ?? '',
  })
})

const passwordSchema = yup.object({
  current_password: yup.string().required('Current password is required.'),
  new_password: yup.string().min(8, 'Password must be at least 8 characters.').required('New password is required.'),
  new_password_confirmation: yup.string()
    .oneOf([yup.ref('new_password')], 'Passwords must match.')
    .required('Please confirm your password.'),
})

async function changePassword(values: Record<string, unknown>, form: { resetForm: () => void }) {
  passwordError.value = ''
  try {
    await authService.changePassword(
      String(values.current_password ?? ''),
      String(values.new_password ?? ''),
      String(values.new_password_confirmation ?? ''),
    )
    toast('Password changed. You will be logged out shortly.', 'success')
    form.resetForm()
    setTimeout(() => {
      authStore.clearAuth()
      router.push('/login')
    }, 2000)
  } catch (error: unknown) {
    passwordError.value = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to change password.')
  }
}
</script>

<template>
  <section>
    <PageHeader
      title="My Account"
      subtitle="Manage your profile and security settings."
      :breadcrumb="[{ label: 'My Account' }]"
    />

    <div v-if="userQuery.isLoading.value" class="rounded-lg border bg-white p-8 text-center text-sm text-slate-500">
      Loading account…
    </div>

    <div v-else-if="userQuery.isError.value" class="rounded-lg border border-red-200 bg-red-50 p-8 text-center text-sm text-red-700">
      {{ userQuery.error.value instanceof Error ? userQuery.error.value.message : 'Unable to load account settings.' }}
    </div>

    <template v-else-if="user">
      <div class="mb-6 flex flex-wrap items-center gap-4 rounded-lg border bg-white p-4 shadow-card sm:p-6">
        <div v-if="user.profile_image_url" class="h-16 w-16 overflow-hidden rounded-full bg-slate-100 sm:h-20 sm:w-20">
          <img :src="user.profile_image_url" :alt="`${user.first_name} ${user.last_name}`" class="h-full w-full object-cover" />
        </div>
        <div
          v-else
          class="flex h-16 w-16 items-center justify-center rounded-full bg-brand-blue/10 text-base font-semibold text-brand-blue sm:h-20 sm:w-20 sm:text-lg"
        >
          {{ getInitials(user.first_name, user.last_name) }}
        </div>
        <div class="min-w-0 flex-1">
          <h2 class="truncate text-lg font-semibold text-slate-900 sm:text-xl">{{ user.first_name }} {{ user.last_name }}</h2>
          <p class="truncate text-sm text-slate-500">{{ user.email }}</p>
        </div>
      </div>

      <div class="mb-4 flex flex-wrap gap-1 rounded-lg border bg-slate-100 p-1">
        <button
          type="button"
          class="rounded-md px-4 py-2 text-sm font-medium transition-colors"
          :class="activeTab === 'profile' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
          @click="activeTab = 'profile'"
        >
          Profile
        </button>
        <button
          type="button"
          class="rounded-md px-4 py-2 text-sm font-medium transition-colors"
          :class="activeTab === 'password' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
          @click="activeTab = 'password'"
        >
          Change Password
        </button>
      </div>

      <form
        v-if="activeTab === 'profile'"
        class="rounded-lg border bg-white p-4 shadow-card sm:p-6"
        @submit.prevent="onProfileSubmit"
      >
        <div class="mb-6">
          <ImageUpload
            :model-value="avatarFile ?? user.profile_image_url"
            shape="circle"
            :size="120"
            :on-remove="() => { avatarFile = null; removeAvatar = true }"
            @update:model-value="(file) => { avatarFile = file; if (file) removeAvatar = false }"
          />
        </div>

        <div class="grid gap-4 md:grid-cols-1">
          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">First Name *</span>
            <Field name="first_name" type="text" class="w-full rounded-md border px-3 py-2" />
            <ErrorMessage name="first_name" class="mt-1 block text-xs text-red-600" />
          </label>
          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">Last Name *</span>
            <Field name="last_name" type="text" class="w-full rounded-md border px-3 py-2" />
            <ErrorMessage name="last_name" class="mt-1 block text-xs text-red-600" />
          </label>
          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">Contact Number</span>
            <Field name="contact_number" type="text" class="w-full rounded-md border px-3 py-2" />
            <ErrorMessage name="contact_number" class="mt-1 block text-xs text-red-600" />
          </label>
          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">Email</span>
            <input :value="user.email" type="email" class="w-full rounded-md border bg-slate-50 px-3 py-2 text-slate-500" disabled />
            <p class="mt-1 text-xs text-slate-500">Contact an administrator to change your email.</p>
          </label>
        </div>

        <p v-if="profileError" class="mt-4 text-sm text-red-600">{{ profileError }}</p>

        <div class="sticky bottom-0 z-10 -mx-4 mt-6 border-t bg-white/95 px-4 py-4 backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:px-0 sm:py-0" style="padding-bottom: calc(1rem + env(safe-area-inset-bottom))">
          <button
            type="submit"
            class="w-full rounded-lg bg-brand-blue px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-blue/90 disabled:opacity-50 sm:w-auto"
            :disabled="profileMutation.isPending.value || !canSaveProfile"
          >
            {{ profileMutation.isPending.value ? 'Saving…' : 'Save Profile' }}
          </button>
        </div>
      </form>

      <div v-else class="max-w-md rounded-lg border bg-white p-4 shadow-card sm:p-6">
        <Form
          :validation-schema="passwordSchema"
          class="space-y-4"
          @submit="changePassword"
          v-slot="{ isSubmitting, values }"
        >
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Current Password</label>
            <Field name="current_password" type="password" class="w-full rounded-md border px-3 py-2" />
            <ErrorMessage name="current_password" class="mt-1 block text-xs text-red-600" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">New Password</label>
            <Field name="new_password" type="password" class="w-full rounded-md border px-3 py-2" />
            <PasswordStrength class="mt-2" :password="String(values.new_password ?? '')" />
            <ErrorMessage name="new_password" class="mt-1 block text-xs text-red-600" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Confirm New Password</label>
            <Field name="new_password_confirmation" type="password" class="w-full rounded-md border px-3 py-2" />
            <ErrorMessage name="new_password_confirmation" class="mt-1 block text-xs text-red-600" />
          </div>
          <p v-if="passwordError" class="text-sm text-red-600">{{ passwordError }}</p>
          <button
            type="submit"
            class="w-full rounded-md bg-brand-blue px-4 py-2 text-sm font-semibold text-white disabled:opacity-50 sm:w-auto"
            :disabled="isSubmitting"
          >
            {{ isSubmitting ? 'Updating…' : 'Change Password' }}
          </button>
        </Form>
      </div>
    </template>
  </section>
</template>
