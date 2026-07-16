<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import * as yup from 'yup'
import TagInput from '@/components/ui/TagInput.vue'

const props = defineProps<{
  open: boolean
  quotationNumber: string
  defaultTo?: string | null
  isSending?: boolean
  error?: string | null
}>()

const emit = defineEmits<{
  send: [payload: { to: string; cc: string[]; subject: string; body: string }]
  close: []
}>()

const to = ref('')
const cc = ref<string[]>([])
const subject = ref('')
const body = ref('')
const tagInputRef = ref<InstanceType<typeof TagInput> | null>(null)

const emailSchema = yup.string().email()

function validateEmail(email: string): boolean {
  try {
    emailSchema.validateSync(email)
    return true
  } catch {
    return false
  }
}

const hasInvalidCc = computed(() => tagInputRef.value?.getHasInvalidTags() ?? false)

const canSend = computed(() =>
  Boolean(to.value.trim())
  && validateEmail(to.value.trim())
  && Boolean(subject.value.trim())
  && Boolean(body.value.trim())
  && !hasInvalidCc.value,
)

function resetForm(): void {
  to.value = props.defaultTo?.trim() ?? ''
  cc.value = []
  subject.value = `Quotation ${props.quotationNumber}`
  body.value = `Please find attached quotation ${props.quotationNumber}.`
}

watch(
  () => [props.open, props.quotationNumber, props.defaultTo] as const,
  ([isOpen]) => {
    if (isOpen) resetForm()
  },
  { immediate: true },
)

function submit(): void {
  if (!canSend.value) return
  emit('send', {
    to: to.value.trim(),
    cc: cc.value,
    subject: subject.value.trim(),
    body: body.value.trim(),
  })
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/50 sm:items-center sm:p-4"
      @click.self="emit('close')"
    >
      <section
        role="dialog"
        aria-labelledby="quotation-email-title"
        class="flex max-h-[100dvh] w-full flex-col overflow-hidden rounded-t-2xl bg-white shadow-card sm:max-h-[90vh] sm:max-w-lg sm:rounded-lg"
      >
        <div class="overflow-y-auto p-6">
        <h2 id="quotation-email-title" class="text-lg font-semibold text-slate-900">
          Email Quotation
        </h2>
        <p class="mt-1 text-sm text-slate-500">
          Send {{ quotationNumber }} to the customer.
        </p>

        <span class="mt-3 inline-flex rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-brand-blue">
          PDF will be attached
        </span>

        <form class="mt-5 space-y-4" @submit.prevent="submit">
          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">To <span class="text-red-500">*</span></span>
            <input
              v-model="to"
              type="email"
              required
              class="w-full rounded-md border px-3 py-2"
              placeholder="customer@example.com"
            />
          </label>

          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">CC</span>
            <TagInput
              ref="tagInputRef"
              v-model="cc"
              placeholder="Add email and press Enter"
              :validate-tag="validateEmail"
            />
          </label>

          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">Subject</span>
            <input
              v-model="subject"
              type="text"
              required
              maxlength="255"
              class="w-full rounded-md border px-3 py-2"
            />
          </label>

          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">Body</span>
            <textarea
              v-model="body"
              required
              rows="5"
              maxlength="5000"
              class="w-full rounded-md border px-3 py-2"
            />
          </label>

          <p v-if="error" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ error }}
          </p>
        </form>
        </div>

          <div class="flex justify-end gap-2 border-t p-4" style="padding-bottom: calc(1rem + env(safe-area-inset-bottom))">
            <button
              type="button"
              class="rounded-md border px-4 py-2 text-sm"
              :disabled="isSending"
              @click="emit('close')"
            >
              Cancel
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-md bg-brand-blue px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
              :disabled="!canSend || isSending"
              @click="submit"
            >
              <span
                v-if="isSending"
                class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"
              />
              {{ isSending ? 'Sending…' : 'Send Email' }}
            </button>
          </div>
      </section>
    </div>
  </Teleport>
</template>
