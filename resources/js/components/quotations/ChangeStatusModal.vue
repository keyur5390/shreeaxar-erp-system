<script setup lang="ts">
import { ref, watch } from 'vue'
import type { QuotationStatusMaster } from '@/types'

const props = defineProps<{
  open: boolean
  status: QuotationStatusMaster | null
  isSubmitting?: boolean
  error?: string | null
}>()

const emit = defineEmits<{
  close: []
  submit: [note: string | null]
}>()

const note = ref('')

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) note.value = ''
  },
)

function submit(): void {
  if (!props.status) return
  emit('submit', note.value.trim() || null)
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
        aria-labelledby="change-status-title"
        class="flex max-h-[100dvh] w-full flex-col overflow-hidden rounded-t-2xl bg-white shadow-card sm:max-h-[90vh] sm:max-w-md sm:rounded-lg"
      >
        <div class="overflow-y-auto p-6">
        <h2 id="change-status-title" class="text-lg font-semibold text-slate-900">
          Change Status
        </h2>
        <p v-if="status" class="mt-1 text-sm text-slate-500">
          Update quotation status to <strong>{{ status.name }}</strong>.
        </p>

        <form class="mt-5 space-y-4" @submit.prevent="submit">
          <label class="block text-sm">
            <span class="mb-1 block font-medium text-slate-700">Note</span>
            <textarea
              v-model="note"
              rows="4"
              maxlength="1000"
              class="w-full rounded-md border px-3 py-2"
              placeholder="Optional note for this status change"
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
              :disabled="isSubmitting"
              @click="emit('close')"
            >
              Cancel
            </button>
            <button
              type="button"
              class="rounded-md bg-brand-blue px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
              :disabled="isSubmitting || !status"
              @click="submit"
            >
              {{ isSubmitting ? 'Updating…' : 'Update Status' }}
            </button>
          </div>
      </section>
    </div>
  </Teleport>
</template>
