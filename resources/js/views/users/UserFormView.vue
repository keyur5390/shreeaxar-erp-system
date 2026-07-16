<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useForm, useFieldArray, ErrorMessage, Field } from 'vee-validate'
import { useQuery, useMutation } from '@tanstack/vue-query'
import * as yup from 'yup'
import PageHeader from '@/components/ui/PageHeader.vue'
import ImageUpload from '@/components/ui/ImageUpload.vue'
import PasswordStrength from '@/components/ui/PasswordStrength.vue'
import { usersService } from '@/services/users.service'
import { rolesService } from '@/services/roles.service'
import { mastersService } from '@/services/masters.service'
import { countriesService } from '@/services/countries.service'
import { STALE_TIME } from '@/lib/queryTimes'
import { useToast } from '@/composables/useToast'
import { useFormDirtyGuard } from '@/composables/useFormDirtyGuard'
import { useAuthStore } from '@/stores/auth.store'
import { ValidationError } from '@/services/api'
import type { UserFormAddress } from '@/types'

const route = useRoute()
const router = useRouter()
const { toast } = useToast()
const authStore = useAuthStore()

const userId = computed(() => {
  const id = route.params.id
  return typeof id === 'string' && id !== 'new' ? id : undefined
})
const isEdit = computed(() => Boolean(userId.value))
const isOwnProfile = computed(() => isEdit.value && authStore.user?.id === userId.value)

const avatarFile = ref<File | null>(null)
const removeAvatar = ref(false)
const submitError = ref('')

let emailDebounceTimer: ReturnType<typeof setTimeout> | null = null
let emailAbortController: AbortController | null = null

async function checkEmailAvailable(email: string): Promise<boolean> {
  emailAbortController?.abort()
  emailAbortController = new AbortController()

  return new Promise((resolve) => {
    if (emailDebounceTimer) clearTimeout(emailDebounceTimer)
    emailDebounceTimer = setTimeout(async () => {
      try {
        const result = await usersService.checkEmail(email, userId.value)
        resolve(result.available)
      } catch {
        resolve(true)
      }
    }, 400)
  })
}

const schema = computed(() => yup.object({
  first_name: yup.string().required('First name is required.').max(255),
  last_name: yup.string().required('Last name is required.').max(255),
  email: yup.string().email('Enter a valid email.').required('Email is required.')
    .test('unique-email', 'Email already in use.', async (value) => {
      if (!value) return true
      return checkEmailAvailable(value)
    }),
  password: isEdit.value
    ? yup.string().nullable().min(8, 'Password must be at least 8 characters.')
    : yup.string().min(8, 'Password must be at least 8 characters.').required('Password is required.'),
  password_confirmation: yup.string().when('password', {
    is: (value: string | null | undefined) => Boolean(value),
    then: (field) => field.oneOf([yup.ref('password')], 'Passwords must match.').required('Please confirm your password.'),
    otherwise: (field) => field.nullable(),
  }),
  contact_number: yup.string().max(50).nullable(),
  department_id: yup.string().nullable(),
  role_id: yup.string().required('Role is required.'),
  addresses: yup.array().of(
    yup.object({
      address_type_id: yup.string().required('Address type is required.'),
      address_line_1: yup.string().required('Address line 1 is required.').max(500),
      address_line_2: yup.string().max(500).nullable(),
      country_id: yup.string().required('Country is required.'),
      state_id: yup.string().required('State is required.'),
      city: yup.string().max(150).nullable(),
      postal_code: yup.string().max(20).nullable(),
    }),
  ).min(1, 'At least one address is required.').max(5, 'Maximum 5 addresses allowed.'),
}))

const emptyAddress = (): UserFormAddress => ({
  address_type_id: '',
  address_line_1: '',
  address_line_2: '',
  country_id: '',
  state_id: '',
  city: '',
  postal_code: '',
})

const { handleSubmit, resetForm, meta, values, setFieldValue } = useForm({
  validationSchema: schema,
  initialValues: {
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
    contact_number: '',
    department_id: '',
    role_id: '',
    addresses: [emptyAddress()],
  },
})

const { fields: addressFields, push: pushAddress, remove: removeAddress } = useFieldArray<UserFormAddress>('addresses')

const { allowNavigation } = useFormDirtyGuard(computed(() => meta.value.dirty))

const userQuery = useQuery({
  queryKey: computed(() => ['users', userId.value]),
  queryFn: () => usersService.get(userId.value!),
  enabled: computed(() => isEdit.value),
})

const rolesQuery = useQuery({
  queryKey: ['roles'],
  queryFn: () => rolesService.list(),
  staleTime: STALE_TIME.masters,
})

const departmentsQuery = useQuery({
  queryKey: ['departments'],
  queryFn: () => mastersService.getDepartments(),
  staleTime: STALE_TIME.masters,
})

const addressTypesQuery = useQuery({
  queryKey: ['address-types'],
  queryFn: () => mastersService.getAddressTypes(),
  staleTime: STALE_TIME.masters,
})

const countriesQuery = useQuery({
  queryKey: ['countries-dropdown'],
  queryFn: () => countriesService.list({ per_page: 200 }),
})

const roles = computed(() => rolesQuery.data.value ?? [])
const departments = computed(() => departmentsQuery.data.value ?? [])
const addressTypes = computed(() => addressTypesQuery.data.value ?? [])
const countries = computed(() => countriesQuery.data.value?.items ?? [])

const existingAvatarUrl = ref<string | null>(null)
const roleName = ref('')

watch(
  () => userQuery.data.value,
  (user) => {
    if (!user) return
    existingAvatarUrl.value = user.profile_image_url ?? null
    roleName.value = user.role?.name ?? user.roles[0]?.name ?? ''
    resetForm({
      values: {
        first_name: user.first_name,
        last_name: user.last_name,
        email: user.email,
        password: '',
        password_confirmation: '',
        contact_number: user.contact_number ?? '',
        department_id: user.department_id ?? '',
        role_id: user.role?.id ?? user.roles[0]?.id ?? '',
        addresses: user.addresses?.length
          ? user.addresses.map((address) => ({
            address_type_id: address.address_type_id,
            address_line_1: address.address_line_1,
            address_line_2: address.address_line_2 ?? '',
            country_id: address.country_id,
            state_id: address.state_id ?? '',
            city: address.city ?? '',
            postal_code: address.postal_code ?? '',
          }))
          : [emptyAddress()],
      },
    })
  },
  { immediate: true },
)

const statesCache = ref<Record<string, Awaited<ReturnType<typeof mastersService.getStates>>>>({})

async function loadStates(countryId: string) {
  if (!countryId) return []
  if (statesCache.value[countryId]) return statesCache.value[countryId]
  const states = await mastersService.getStates(countryId)
  statesCache.value[countryId] = states
  return states
}

watch(
  () => values.addresses?.map((address) => address.country_id),
  async (countryIds, prevIds) => {
    if (!countryIds) return
    countryIds.forEach(async (countryId, index) => {
      if (countryId && prevIds?.[index] !== countryId) {
        await loadStates(countryId)
        if (prevIds?.[index] && prevIds[index] !== countryId) {
          setFieldValue(`addresses.${index}.state_id`, '')
        }
      }
    })
  },
  { deep: true },
)

function getStatesForCountry(countryId: string) {
  return statesCache.value[countryId] ?? []
}

watch(
  () => values.addresses,
  (addresses) => {
    addresses?.forEach((address) => {
      if (address.country_id && !statesCache.value[address.country_id]) {
        loadStates(address.country_id)
      }
    })
  },
  { immediate: true, deep: true },
)

function onCountryChange(index: number, countryId: string) {
  setFieldValue(`addresses.${index}.state_id`, '')
  if (countryId) loadStates(countryId)
}

function onAvatarRemove() {
  removeAvatar.value = true
  existingAvatarUrl.value = null
}

const saveMutation = useMutation({
  mutationFn: async (formValues: typeof values) => {
    const payload: Record<string, unknown> = {
      first_name: formValues.first_name,
      last_name: formValues.last_name,
      email: formValues.email,
      contact_number: formValues.contact_number || null,
      department_id: formValues.department_id || null,
      role_id: formValues.role_id,
      addresses: formValues.addresses,
    }
    if (formValues.password) {
      payload.password = formValues.password
      payload.password_confirmation = formValues.password_confirmation
    }
    if (isEdit.value && userId.value) {
      return usersService.update(userId.value, payload, avatarFile.value, removeAvatar.value)
    }
    return usersService.create(payload, avatarFile.value)
  },
  onSuccess: () => {
    allowNavigation()
    toast(isEdit.value ? 'User updated successfully.' : 'User created successfully.', 'success')
    router.push('/users')
  },
  onError: (error: unknown) => {
    if (error instanceof ValidationError) {
      submitError.value = Object.values(error.errors).flat()[0] ?? error.message
      return
    }
    submitError.value = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to save user.')
  },
})

const onSubmit = handleSubmit((formValues) => {
  submitError.value = ''
  saveMutation.mutate(formValues)
})

const showPasswordFields = computed(() => !isEdit.value || Boolean(values.password))
const pageTitle = computed(() => isEdit.value ? 'Edit User' : 'Add New User')
</script>

<template>
  <section class="pb-24">
    <PageHeader
      :title="pageTitle"
      subtitle="Manage personal details, role, and addresses."
      :breadcrumb="[{ label: 'Users', href: '/users' }, { label: pageTitle }]"
    />

    <div v-if="isEdit && userQuery.isLoading.value" class="rounded-lg border bg-white p-8 text-center text-sm text-slate-500">
      Loading user…
    </div>

    <form v-else class="space-y-6" @submit="onSubmit">
      <div class="grid gap-6 lg:grid-cols-5">
        <div class="space-y-6 rounded-lg border bg-white p-6 shadow-card lg:col-span-3">
          <h2 class="text-base font-semibold text-slate-900">Personal Info</h2>

          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">First Name *</label>
              <Field name="first_name" class="w-full rounded-md border px-3 py-2" />
              <ErrorMessage name="first_name" class="mt-1 block text-xs text-red-600" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Last Name *</label>
              <Field name="last_name" class="w-full rounded-md border px-3 py-2" />
              <ErrorMessage name="last_name" class="mt-1 block text-xs text-red-600" />
            </div>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Email *</label>
            <Field name="email" type="email" class="w-full rounded-md border px-3 py-2" />
            <ErrorMessage name="email" class="mt-1 block text-xs text-red-600" />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">
              {{ isEdit ? 'New Password' : 'Password *' }}
            </label>
            <Field name="password" type="password" class="w-full rounded-md border px-3 py-2" :placeholder="isEdit ? 'Leave blank to keep current' : ''" />
            <PasswordStrength v-if="values.password" class="mt-2" :password="values.password" />
            <ErrorMessage name="password" class="mt-1 block text-xs text-red-600" />
          </div>

          <div v-if="showPasswordFields && values.password">
            <label class="mb-1 block text-sm font-medium text-slate-700">Password Confirmation *</label>
            <Field name="password_confirmation" type="password" class="w-full rounded-md border px-3 py-2" />
            <ErrorMessage name="password_confirmation" class="mt-1 block text-xs text-red-600" />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Contact Number</label>
            <Field name="contact_number" class="w-full rounded-md border px-3 py-2" />
            <ErrorMessage name="contact_number" class="mt-1 block text-xs text-red-600" />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Department</label>
            <Field name="department_id" as="select" class="w-full rounded-md border px-3 py-2">
              <option value="">No department</option>
              <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
            </Field>
            <ErrorMessage name="department_id" class="mt-1 block text-xs text-red-600" />
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Role *</label>
            <template v-if="isOwnProfile">
              <span
                class="inline-flex items-center rounded-md bg-slate-100 px-3 py-2 text-sm text-slate-700"
                title="You cannot change your own role."
              >
                {{ roleName }}
              </span>
            </template>
            <template v-else>
              <Field name="role_id" as="select" class="w-full rounded-md border px-3 py-2">
                <option value="">Select role</option>
                <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
              </Field>
              <ErrorMessage name="role_id" class="mt-1 block text-xs text-red-600" />
            </template>
          </div>
        </div>

        <div class="rounded-lg border bg-white p-6 shadow-card lg:col-span-2">
          <h2 class="mb-4 text-base font-semibold text-slate-900">Profile Photo</h2>
          <ImageUpload
            :model-value="avatarFile ?? existingAvatarUrl"
            shape="circle"
            :size="200"
            :on-remove="onAvatarRemove"
            @update:model-value="(file) => { avatarFile = file; if (file) removeAvatar = false }"
          />
        </div>
      </div>

      <div class="rounded-lg border bg-white p-6 shadow-card">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-base font-semibold text-slate-900">Addresses</h2>
          <button
            v-if="addressFields.length < 5"
            type="button"
            class="text-sm font-medium text-brand-blue hover:underline"
            @click="pushAddress(emptyAddress())"
          >
            + Add Address
          </button>
        </div>
        <ErrorMessage name="addresses" class="mb-3 block text-xs text-red-600" />

        <div class="space-y-4">
          <article
            v-for="(field, index) in addressFields"
            :key="field.key"
            class="rounded-lg border border-slate-200 p-4"
          >
            <div class="mb-3 flex items-center justify-between">
              <h3 class="text-sm font-semibold text-slate-800">Address {{ index + 1 }}</h3>
              <button
                type="button"
                class="text-sm text-red-600 disabled:opacity-40"
                :disabled="addressFields.length <= 1"
                @click="removeAddress(index)"
              >
                Remove
              </button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Address Type *</label>
                <Field :name="`addresses.${index}.address_type_id`" as="select" class="w-full rounded-md border px-3 py-2">
                  <option value="">Select type</option>
                  <option v-for="type in addressTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                </Field>
                <ErrorMessage :name="`addresses.${index}.address_type_id`" class="mt-1 block text-xs text-red-600" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Country *</label>
                <Field
                  :name="`addresses.${index}.country_id`"
                  as="select"
                  class="w-full rounded-md border px-3 py-2"
                  @change="onCountryChange(index, ($event.target as HTMLSelectElement).value)"
                >
                  <option value="">Select country</option>
                  <option v-for="country in countries" :key="country.id" :value="country.id">{{ country.name }}</option>
                </Field>
                <ErrorMessage :name="`addresses.${index}.country_id`" class="mt-1 block text-xs text-red-600" />
              </div>
              <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Address Line 1 *</label>
                <Field :name="`addresses.${index}.address_line_1`" class="w-full rounded-md border px-3 py-2" />
                <ErrorMessage :name="`addresses.${index}.address_line_1`" class="mt-1 block text-xs text-red-600" />
              </div>
              <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Address Line 2</label>
                <Field :name="`addresses.${index}.address_line_2`" class="w-full rounded-md border px-3 py-2" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">State *</label>
                <Field :name="`addresses.${index}.state_id`" as="select" class="w-full rounded-md border px-3 py-2">
                  <option value="">Select state</option>
                  <option
                    v-for="state in getStatesForCountry(values.addresses?.[index]?.country_id ?? '')"
                    :key="state.id"
                    :value="state.id"
                  >
                    {{ state.name }}
                  </option>
                </Field>
                <ErrorMessage :name="`addresses.${index}.state_id`" class="mt-1 block text-xs text-red-600" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">City</label>
                <Field :name="`addresses.${index}.city`" class="w-full rounded-md border px-3 py-2" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Postal Code</label>
                <Field :name="`addresses.${index}.postal_code`" class="w-full rounded-md border px-3 py-2" />
              </div>
            </div>
          </article>
        </div>
      </div>

      <p v-if="submitError" class="text-sm text-red-600">{{ submitError }}</p>

      <div class="sticky bottom-0 z-10 -mx-4 border-t bg-white/95 px-4 py-4 backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:px-0 sm:py-0" style="padding-bottom: calc(1rem + env(safe-area-inset-bottom))">
        <div class="flex justify-end gap-3">
          <button
            type="button"
            class="rounded-md border px-4 py-2 text-sm"
            @click="router.push('/users')"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="rounded-md bg-brand-blue px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
            :disabled="saveMutation.isPending.value"
          >
            {{ saveMutation.isPending.value ? 'Saving…' : 'Save User' }}
          </button>
        </div>
      </div>
    </form>
  </section>
</template>
