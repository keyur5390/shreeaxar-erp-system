<script setup lang="ts">
import { computed, ref } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { Pencil, Plus, Star, Trash2 } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import PermissionGate from '@/components/ui/PermissionGate.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import { termsAndConditionsService, type TermsAndConditionPayload } from '@/services/terms-and-conditions.service'
import { STALE_TIME } from '@/lib/queryTimes'
import { useToast } from '@/composables/useToast'
import type { TermsAndCondition } from '@/types'
import { ValidationError } from '@/services/api'

const queryClient = useQueryClient()
const { toast } = useToast()

const modalOpen = ref(false)
const editingTemplate = ref<TermsAndCondition | null>(null)
const deleteTarget = ref<TermsAndCondition | null>(null)
const formError = ref('')

const form = ref<TermsAndConditionPayload>({
  name: '',
  content: '',
  is_default: false,
})

const templatesQuery = useQuery({
  queryKey: ['terms-and-conditions'],
  queryFn: () => termsAndConditionsService.list(),
  staleTime: STALE_TIME.masters,
})

const templates = computed(() => templatesQuery.data.value ?? [])

const saveMutation = useMutation({
  mutationFn: async () => {
    const payload: TermsAndConditionPayload = {
      name: form.value.name.trim(),
      content: form.value.content.trim(),
      is_default: form.value.is_default,
    }
    if (!payload.name || !payload.content) {
      throw new Error('Name and content are required.')
    }
    if (editingTemplate.value) return termsAndConditionsService.update(editingTemplate.value.id, payload)
    return termsAndConditionsService.create(payload)
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['terms-and-conditions'] })
    toast(editingTemplate.value ? 'Terms & conditions template updated successfully.' : 'Terms & conditions template created successfully.', 'success')
    closeModal()
  },
  onError: (error: unknown) => {
    formError.value = error instanceof ValidationError
      ? Object.values(error.errors).flat()[0] ?? error.message
      : error instanceof Error ? error.message : 'Unable to save template.'
  },
})

const setDefaultMutation = useMutation({
  mutationFn: (id: string) => termsAndConditionsService.setDefault(id),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['terms-and-conditions'] })
    toast('Default template updated.', 'success')
  },
  onError: (error: unknown) => {
    toast(error instanceof Error ? error.message : 'Unable to set default template.', 'error')
  },
})

const deleteMutation = useMutation({
  mutationFn: (template: TermsAndCondition) => termsAndConditionsService.remove(template.id),
  onSuccess: (response) => {
    queryClient.invalidateQueries({ queryKey: ['terms-and-conditions'] })
    toast(response?.message ?? 'Template deleted successfully.', response?.message ? 'info' : 'success')
    deleteTarget.value = null
  },
  onError: (error: unknown) => {
    const message = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
      ?? (error instanceof Error ? error.message : 'Unable to delete template.')
    toast(message, 'error')
    deleteTarget.value = null
  },
})

function previewContent(content: string, maxLength = 120): string {
  const normalized = content.replace(/\s+/g, ' ').trim()
  if (normalized.length <= maxLength) return normalized
  return `${normalized.slice(0, maxLength)}…`
}

function openCreateModal() {
  editingTemplate.value = null
  form.value = { name: '', content: '', is_default: false }
  formError.value = ''
  modalOpen.value = true
}

function openEditModal(template: TermsAndCondition) {
  editingTemplate.value = template
  form.value = {
    name: template.name,
    content: template.content,
    is_default: template.is_default,
  }
  formError.value = ''
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
  editingTemplate.value = null
  formError.value = ''
}
</script>

<template>
  <section>
    <PageHeader
      title="Terms & Conditions"
      subtitle="Manage reusable terms & conditions templates for quotations."
      :breadcrumb="[{ label: 'Masters' }, { label: 'Terms & Conditions' }]"
    >
      <template #action>
        <PermissionGate module="terms_and_conditions" action="create">
          <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-brand-blue px-4 py-2 text-sm font-semibold text-white hover:bg-brand-blue/90" @click="openCreateModal">
            <Plus class="h-4 w-4" />
            Add Template
          </button>
        </PermissionGate>
      </template>
    </PageHeader>

    <div v-if="templatesQuery.isLoading.value" class="rounded-lg border bg-white p-6 text-sm text-slate-500">Loading templates…</div>
    <div v-else-if="!templates.length" class="rounded-lg border bg-white p-8 text-center text-sm text-slate-500">No terms & conditions templates configured.</div>

    <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <article
        v-for="template in templates"
        :key="template.id"
        class="relative rounded-lg border bg-white p-5 shadow-card"
        :class="{ 'border-amber-400 ring-1 ring-amber-200': template.is_default }"
      >
        <Star v-if="template.is_default" class="absolute right-4 top-4 h-5 w-5 fill-amber-400 text-amber-400" />

        <h3 class="pr-8 text-lg font-semibold text-slate-900">{{ template.name }}</h3>
        <p class="mt-2 line-clamp-3 whitespace-pre-wrap text-sm text-slate-600">{{ previewContent(template.content, 200) }}</p>

        <div class="mt-4 flex flex-wrap gap-2">
          <PermissionGate v-if="!template.is_default" module="terms_and_conditions" action="edit">
            <button type="button" class="rounded-md border px-3 py-1.5 text-xs font-medium hover:bg-slate-50" :disabled="setDefaultMutation.isPending.value" @click="setDefaultMutation.mutate(template.id)">
              Set Default
            </button>
          </PermissionGate>
          <PermissionGate module="terms_and_conditions" action="edit">
            <button type="button" class="inline-flex items-center gap-1 rounded-md border px-3 py-1.5 text-xs font-medium hover:bg-slate-50" @click="openEditModal(template)">
              <Pencil class="h-3.5 w-3.5" /> Edit
            </button>
          </PermissionGate>
          <PermissionGate module="terms_and_conditions" action="delete">
            <button type="button" class="inline-flex items-center gap-1 rounded-md border border-red-200 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50" @click="deleteTarget = template">
              <Trash2 class="h-3.5 w-3.5" /> Delete
            </button>
          </PermissionGate>
        </div>
      </article>
    </div>

    <Teleport to="body">
      <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4">
        <section class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-6 shadow-card">
          <h2 class="text-lg font-semibold">{{ editingTemplate ? 'Edit Template' : 'Add Template' }}</h2>

          <label class="mt-4 block text-sm font-medium">
            Template Name
            <input v-model="form.name" type="text" class="mt-1 w-full rounded-md border px-3 py-2" placeholder="e.g. Standard Export Terms" />
          </label>

          <label class="mt-4 block text-sm font-medium">
            Content
            <textarea v-model="form.content" rows="10" class="mt-1 w-full rounded-md border px-3 py-2 font-mono text-sm" placeholder="Enter terms & conditions text…" />
          </label>

          <label v-if="!editingTemplate?.is_default" class="mt-4 flex items-center gap-2 text-sm">
            <input v-model="form.is_default" type="checkbox" class="rounded border" />
            Set as default template
          </label>

          <p v-if="formError" class="mt-2 text-sm text-red-600">{{ formError }}</p>

          <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="rounded-md border px-4 py-2" @click="closeModal">Cancel</button>
            <button type="button" class="rounded-md bg-brand-blue px-4 py-2 text-white disabled:opacity-50" :disabled="saveMutation.isPending.value" @click="saveMutation.mutate()">Save</button>
          </div>
        </section>
      </div>
    </Teleport>

    <ConfirmDialog
      :open="Boolean(deleteTarget)"
      title="Delete template?"
      :description="deleteTarget ? 'Delete ' + deleteTarget.name + '? This cannot be undone.' : ''"
      confirm-label="Delete"
      confirm-variant="destructive"
      @cancel="deleteTarget = null"
      @confirm="deleteTarget && deleteMutation.mutate(deleteTarget)"
    />
  </section>
</template>
