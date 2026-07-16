<script setup lang="ts">
defineProps<{
  text: string
}>()
</script>

<template>
  <span class="group relative inline-flex">
    <slot />
    <span
      role="tooltip"
      class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded bg-slate-900 px-2 py-1 text-xs text-white shadow-lg group-hover:block"
    >
      {{ text }}
    </span>
  </span>
</template>
