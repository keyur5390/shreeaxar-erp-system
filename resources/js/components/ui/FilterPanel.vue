<script setup lang="ts">
import { ref, watch } from 'vue'
import { ChevronDown, Filter } from 'lucide-vue-next'

defineOptions({ name: 'FilterPanel' })

const props = withDefaults(defineProps<{
  hasActiveFilters?: boolean
  title?: string
}>(), {
  hasActiveFilters: false,
  title: 'Filters',
})

const open = ref(props.hasActiveFilters)

watch(() => props.hasActiveFilters, (active) => {
  if (active) open.value = true
})
</script>

<template>
  <div class="rounded-lg border bg-white shadow-card">
    <button
      type="button"
      class="flex w-full items-center justify-between gap-2 p-4 text-left lg:hidden"
      :aria-expanded="open"
      @click="open = !open"
    >
      <span class="flex min-w-0 items-center gap-2 text-sm font-semibold text-slate-800">
        <Filter class="h-4 w-4 shrink-0" />
        <span class="truncate">{{ title }}</span>
        <span
          v-if="hasActiveFilters"
          class="shrink-0 rounded-full bg-brand-blue/10 px-2 py-0.5 text-xs font-medium text-brand-blue"
        >
          Active
        </span>
      </span>
      <ChevronDown
        class="h-4 w-4 shrink-0 text-slate-500 transition-transform"
        :class="{ 'rotate-180': open }"
      />
    </button>

    <div class="p-4 pt-0 lg:block lg:p-4" :class="open ? 'block' : 'hidden'">
      <slot />
    </div>
  </div>
</template>
