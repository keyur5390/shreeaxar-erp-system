<script setup lang="ts">
defineProps<{ icon?: string; title: string; description?: string }>()
</script>
<template>
  <div class="flex flex-col items-center justify-center rounded-lg border border-dashed bg-white p-10 text-center">
    <div v-if="icon" class="mb-3 text-4xl">{{ icon }}</div>
    <h3 class="text-lg font-semibold text-slate-900">{{ title }}</h3>
    <p v-if="description" class="mt-1 max-w-md text-sm text-slate-500">{{ description }}</p>
    <div v-if="$slots.action" class="mt-5"><slot name="action" /></div>
  </div>
</template>
