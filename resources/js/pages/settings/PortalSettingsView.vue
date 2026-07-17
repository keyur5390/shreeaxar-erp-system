<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { Building2, CreditCard, Percent, Save } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import { settingsService } from '@/services/settings.service'
import { useToast } from '@/composables/useToast'
import { ValidationError } from '@/services/api'

defineOptions({ name: 'PortalSettingsView' })

const { toast } = useToast()
const queryClient = useQueryClient()

const expiryDays = ref<number | null>(null)
const submitError = ref('')

const expiryQuery = useQuery({
  queryKey: ['settings', 'quotation_default_expiry_days'],
  queryFn: () => settingsService.get('quotation_default_expiry_days'),
})

watch(
  () => expiryQuery.data.value,
  (setting) => {
    if (!setting?.value) {
      expiryDays.value = 30
      return
    }
    const parsed = Number.parseInt(setting.value, 10)
    expiryDays.value = Number.isNaN(parsed) ? 30 : parsed
  },
  { immediate: true },
)

const loadError = computed(() => expiryQuery.isError.value
  ? (expiryQuery.error.value instanceof Error ? expiryQuery.error.value.message : 'Unable to load portal settings.')
  : '')

const saveMutation = useMutation({
  mutationFn: async () => {
    const days = expiryDays.value
    if (days === null || days < 1 || days > 365) {
      throw new Error('Expiry days must be between 1 and 365.')
    }
    return settingsService.update('quotation_default_expiry_days', String(days))
  },
  onSuccess: () => {
    submitError.value = ''
    queryClient.invalidateQueries({ queryKey: ['settings', 'quotation_default_expiry_days'] })
    toast('Portal settings saved successfully.', 'success')
  },
  onError: (error: unknown) => {
    submitError.value = error instanceof ValidationError
      ? Object.values(error.errors).flat()[0] ?? error.message
      : error instanceof Error ? error.message : 'Unable to save settings.'
  },
})

const quickLinks = [
  { label: 'Company Detail', to: '/masters/company-detail', icon: Building2, description: 'Business name, logo, and contact info' },
  { label: 'Tax (VAT)', to: '/masters/taxes', icon: Percent, description: 'Default VAT rate for quotations' },
  { label: 'Bank Details', to: '/masters/bank-details', icon: CreditCard, description: 'Bank accounts shown on quotations' },
]
</script>

<template>
  <section class="space-y-6">
    <PageHeader
      title="Portal Settings"
      subtitle="Configure system-wide defaults for the ERP portal."
      :breadcrumb="[{ label: 'Settings', href: '/settings' }, { label: 'Portal Settings' }]"
    />

    <div v-if="loadError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      {{ loadError }}
    </div>

    <form class="space-y-6" @submit.prevent="saveMutation.mutate()">
      <div v-if="submitError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ submitError }}
      </div>

      <div class="rounded-lg border bg-white p-6 shadow-card">
        <h2 class="text-base font-semibold text-slate-900">Quotation Defaults</h2>
        <p class="mt-1 text-sm text-slate-500">
          Applied when creating new quotations unless changed manually.
        </p>

        <div v-if="expiryQuery.isLoading.value" class="mt-4 text-sm text-slate-500">
          Loading settings…
        </div>

        <label v-else class="mt-4 block max-w-xs text-sm">
          <span class="mb-1 block font-medium text-slate-700">Default expiry (days)</span>
          <input
            v-model.number="expiryDays"
            type="number"
            min="1"
            max="365"
            class="w-full rounded-md border px-3 py-2"
            required
          />
          <span class="mt-1 block text-xs text-slate-500">Number of days after quotation date until expiry.</span>
        </label>
      </div>

      <div class="rounded-lg border bg-white p-6 shadow-card">
        <h2 class="text-base font-semibold text-slate-900">Related Configuration</h2>
        <p class="mt-1 text-sm text-slate-500">
          Other portal configuration is managed under Masters.
        </p>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <RouterLink
            v-for="link in quickLinks"
            :key="link.to"
            :to="link.to"
            class="flex items-start gap-3 rounded-lg border p-4 transition-colors hover:border-brand-blue/30 hover:bg-brand-blue/5"
          >
            <component :is="link.icon" class="mt-0.5 h-5 w-5 shrink-0 text-brand-blue" />
            <div class="min-w-0">
              <p class="font-medium text-slate-900">{{ link.label }}</p>
              <p class="mt-0.5 text-xs text-slate-500">{{ link.description }}</p>
            </div>
          </RouterLink>
        </div>
      </div>

      <div class="flex justify-end">
        <button
          type="submit"
          class="inline-flex items-center gap-2 rounded-lg bg-brand-blue px-4 py-2 text-sm font-semibold text-white hover:bg-brand-blue/90 disabled:opacity-60"
          :disabled="saveMutation.isPending.value || expiryQuery.isLoading.value"
        >
          <Save class="h-4 w-4" />
          Save Settings
        </button>
      </div>
    </form>
  </section>
</template>
