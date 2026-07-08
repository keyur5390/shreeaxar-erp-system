<script setup lang="ts">
withDefaults(defineProps<{ rows?: number; cols?: number }>(), { rows: 5, cols: 4 })
</script>
<template>
  <div class="overflow-hidden rounded-lg border bg-white shadow-card">
    <div v-for="row in rows" :key="row" class="grid gap-4 border-b p-4 last:border-0" :style="{ gridTemplateColumns: `repeat(${cols}, minmax(0, 1fr))` }">
      <div v-for="col in cols" :key="col" class="h-4 animate-pulse rounded bg-slate-200" />
    </div>
  </div>
</template>
