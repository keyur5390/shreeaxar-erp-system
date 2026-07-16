<script setup lang="ts">
import { ref } from 'vue'
import { useIntersectionObserver } from '@vueuse/core'

const props = withDefaults(defineProps<{
  src?: string | null
  alt?: string
  class?: string
}>(), {
  alt: '',
  class: '',
})

const root = ref<HTMLElement | null>(null)
const isVisible = ref(false)

useIntersectionObserver(
  root,
  ([entry]) => {
    if (entry?.isIntersecting) {
      isVisible.value = true
    }
  },
  { rootMargin: '100px' },
)
</script>

<template>
  <div ref="root" :class="props.class">
    <img
      v-if="src && isVisible"
      :src="src"
      :alt="alt"
      loading="lazy"
      class="h-full w-full object-cover"
    />
    <div v-else-if="src" class="h-full w-full animate-pulse bg-slate-100" />
    <slot v-else name="placeholder" />
  </div>
</template>
