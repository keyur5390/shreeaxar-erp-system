<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { Building2, Pencil, Upload } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import PermissionGate from '@/components/ui/PermissionGate.vue'
import { companyService, type CompanyDetailPayload } from '@/services/company.service'
import { useToast } from '@/composables/useToast'
import { ValidationError } from '@/services/api'

const queryClient = useQueryClient()
const { toast } = useToast()

const editMode = ref(false)
const logoFile = ref<File | null>(null)
const logoPreview = ref<string | null>(null)
const formError = ref('')

const form = ref<CompanyDetailPayload>({
  name: '',
  email: '',
  phone: '',
  address: '',
  tin_number: '',
  vat_number: '',
  website: '',
})

const companyQuery = useQuery({
  queryKey: ['company-detail'],
  queryFn: () => companyService.get(),
})

watch(
  () => companyQuery.data.value,
  (company) => {
    if (!company) return
    form.value = {
      name: company.name ?? '',
      email: company.email ?? '',
      phone: company.phone ?? '',
      address: company.address ?? '',
      tin_number: company.tin_number ?? '',
      vat_number: company.vat_number ?? '',
      website: company.website ?? '',
    }
  },
  { immediate: true },
)

const displayLogo = computed(() => logoPreview.value ?? companyQuery.data.value?.logo_url ?? null)

const saveMutation = useMutation({
  mutationFn: async () => {
    const updated = await companyService.update(form.value)

    if (!logoFile.value) {
      return { company: updated, logoError: null as string | null }
    }

    try {
      const withLogo = await companyService.uploadLogo(logoFile.value)
      return { company: withLogo, logoError: null as string | null }
    } catch (error: unknown) {
      const logoError = error instanceof ValidationError
        ? Object.values(error.errors).flat()[0] ?? error.message
        : error instanceof Error ? error.message : 'Logo upload failed.'
      return { company: updated, logoError }
    }
  },
  onSuccess: (result) => {
    queryClient.invalidateQueries({ queryKey: ['company-detail'] })
    editMode.value = false
    logoFile.value = null
    if (logoPreview.value) {
      URL.revokeObjectURL(logoPreview.value)
      logoPreview.value = null
    }

    if (result.logoError) {
      toast('Company details saved, but logo upload failed: ' + result.logoError, 'error')
    } else {
      toast('Company details saved successfully.', 'success')
    }
  },
  onError: (error: unknown) => {
    formError.value = error instanceof ValidationError
      ? Object.values(error.errors).flat()[0] ?? error.message
      : error instanceof Error ? error.message : 'Unable to save company details.'
  },
})

function onLogoSelect(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return
  if (logoPreview.value) URL.revokeObjectURL(logoPreview.value)
  logoFile.value = file
  logoPreview.value = URL.createObjectURL(file)
}

function cancelEdit() {
  editMode.value = false
  formError.value = ''
  logoFile.value = null
  if (logoPreview.value) {
    URL.revokeObjectURL(logoPreview.value)
    logoPreview.value = null
  }
  const company = companyQuery.data.value
  if (company) {
    form.value = {
      name: company.name ?? '',
      email: company.email ?? '',
      phone: company.phone ?? '',
      address: company.address ?? '',
      tin_number: company.tin_number ?? '',
      vat_number: company.vat_number ?? '',
      website: company.website ?? '',
    }
  }
}
</script>

<template>
  <section>
    <PageHeader
      title="Company Detail"
      subtitle="Manage your organisation profile used on quotations and documents."
      :breadcrumb="[{ label: 'Masters' }, { label: 'Company Detail' }]"
    >
      <template #action>
        <PermissionGate v-if="!editMode" module="company_detail" action="edit">
          <button type="button" class="inline-flex items-center gap-1 rounded-lg border px-4 py-2 text-sm font-semibold hover:bg-slate-50" @click="editMode = true">
            <Pencil class="h-4 w-4" /> Edit
          </button>
        </PermissionGate>
      </template>
    </PageHeader>

    <div v-if="companyQuery.isLoading.value" class="rounded-lg border bg-white p-6 text-sm text-slate-500">Loading company details…</div>

    <div v-else class="rounded-lg border bg-white p-6 shadow-card">
      <div class="flex flex-col gap-6 md:flex-row md:items-start">
        <div class="flex flex-col items-center gap-3">
          <div class="grid h-28 w-28 place-items-center overflow-hidden rounded-xl border bg-slate-50">
            <img v-if="displayLogo" :src="displayLogo" alt="Company logo" class="h-full w-full object-contain" />
            <Building2 v-else class="h-12 w-12 text-slate-300" />
          </div>
          <label v-if="editMode" class="inline-flex cursor-pointer items-center gap-1 text-sm text-brand-blue hover:underline">
            <Upload class="h-4 w-4" /> Upload logo
            <input type="file" accept="image/jpeg,image/png,image/webp,image/jpg" class="hidden" @change="onLogoSelect" />
          </label>
        </div>

        <div class="min-w-0 flex-1 space-y-4">
          <template v-if="editMode">
            <label class="block text-sm font-medium">Company Name<input v-model="form.name" type="text" class="mt-1 w-full rounded-md border px-3 py-2" /></label>
            <label class="block text-sm font-medium">Email<input v-model="form.email" type="email" class="mt-1 w-full rounded-md border px-3 py-2" /></label>
            <label class="block text-sm font-medium">Phone<input v-model="form.phone" type="text" class="mt-1 w-full rounded-md border px-3 py-2" /></label>
            <label class="block text-sm font-medium">Address<textarea v-model="form.address" rows="3" class="mt-1 w-full rounded-md border px-3 py-2" /></label>
            <div class="grid gap-4 sm:grid-cols-2">
              <label class="block text-sm font-medium">TIN Number<input v-model="form.tin_number" type="text" class="mt-1 w-full rounded-md border px-3 py-2" /></label>
              <label class="block text-sm font-medium">VAT Number<input v-model="form.vat_number" type="text" class="mt-1 w-full rounded-md border px-3 py-2" /></label>
            </div>
            <label class="block text-sm font-medium">Website<input v-model="form.website" type="url" class="mt-1 w-full rounded-md border px-3 py-2" placeholder="https://example.com" /></label>

            <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>

            <div class="flex gap-2">
              <button type="button" class="rounded-md bg-brand-blue px-4 py-2 text-sm text-white disabled:opacity-50" :disabled="saveMutation.isPending.value" @click="saveMutation.mutate()">
                {{ saveMutation.isPending.value ? 'Saving…' : 'Save' }}
              </button>
              <button type="button" class="rounded-md border px-4 py-2 text-sm" @click="cancelEdit">Cancel</button>
            </div>
          </template>

          <template v-else>
            <div>
              <h2 class="text-2xl font-semibold text-slate-900">{{ companyQuery.data.value?.name }}</h2>
              <p v-if="companyQuery.data.value?.email" class="mt-1 text-sm text-slate-600">{{ companyQuery.data.value.email }}</p>
              <p v-if="companyQuery.data.value?.phone" class="text-sm text-slate-600">{{ companyQuery.data.value.phone }}</p>
            </div>
            <p v-if="companyQuery.data.value?.address" class="whitespace-pre-line text-sm text-slate-700">{{ companyQuery.data.value.address }}</p>
            <div class="grid gap-2 text-sm sm:grid-cols-2">
              <p v-if="companyQuery.data.value?.tin_number"><span class="font-medium text-slate-500">TIN:</span> {{ companyQuery.data.value.tin_number }}</p>
              <p v-if="companyQuery.data.value?.vat_number"><span class="font-medium text-slate-500">VAT:</span> {{ companyQuery.data.value.vat_number }}</p>
            </div>
            <a v-if="companyQuery.data.value?.website" :href="companyQuery.data.value.website" target="_blank" rel="noopener" class="text-sm text-brand-blue hover:underline">{{ companyQuery.data.value.website }}</a>
          </template>
        </div>
      </div>
    </div>
  </section>
</template>
