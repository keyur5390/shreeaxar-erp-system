<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import type { ToastType } from '@/composables/useToast'

type ToastItem = { id: number; message: string; type: ToastType }

const toasts = ref<ToastItem[]>([])
let nextId = 0

function pushToast(message: string, type: ToastType) {
  const id = ++nextId
  toasts.value = [...toasts.value, { id, message, type }]
  window.setTimeout(() => {
    toasts.value = toasts.value.filter((toast) => toast.id !== id)
  }, 4000)
}

function onToast(event: Event) {
  const detail = (event as CustomEvent<{ message: string; type?: ToastType }>).detail
  if (detail?.message) pushToast(detail.message, detail.type ?? 'info')
}

onMounted(() => window.addEventListener('shreeaxar:toast', onToast))
onUnmounted(() => window.removeEventListener('shreeaxar:toast', onToast))

const toneClass = (type: ToastType) => ({
  success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
  error: 'border-red-200 bg-red-50 text-red-900',
  info: 'border-slate-200 bg-white text-slate-900',
}[type])
</script>

<template>
  <Teleport to="body">
    <div class="pointer-events-none fixed right-4 top-4 z-[100] flex w-full max-w-sm flex-col gap-2">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="pointer-events-auto rounded-lg border px-4 py-3 text-sm shadow-card"
        :class="toneClass(toast.type)"
      >
        {{ toast.message }}
      </div>
    </div>
  </Teleport>
</template>
