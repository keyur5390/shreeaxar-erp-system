<script setup lang="ts">
import { computed, inject } from 'vue'
import { cn } from '@/lib/utils'
import { tabsKey } from './tabs-context'

const props = defineProps<{
  value: string
  class?: string
}>()

const tabs = inject(tabsKey)
if (!tabs) throw new Error('TabsContent must be used within Tabs')

const isActive = computed(() => tabs.activeTab.value === props.value)
</script>

<template>
  <div
    v-show="isActive"
    :class="cn('mt-4 ring-offset-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue focus-visible:ring-offset-2', props.class)"
  >
    <slot />
  </div>
</template>
