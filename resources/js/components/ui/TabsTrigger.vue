<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '@/lib/utils'
import { useRequiredInject } from '@/lib/inject-context'
import { tabsKey } from './tabs-context'

const props = defineProps<{
  value: string
  class?: string
}>()

const tabs = useRequiredInject(tabsKey, 'TabsTrigger must be used within Tabs')

const isActive = computed(() => tabs.activeTab.value === props.value)

function onClick() {
  tabs.setActiveTab(props.value)
}
</script>

<template>
  <button
    type="button"
    :class="cn(
      'inline-flex items-center justify-center whitespace-nowrap rounded-sm px-3 py-1.5 text-sm font-medium ring-offset-white transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
      isActive ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900',
      props.class,
    )"
    @click="onClick"
  >
    <slot />
  </button>
</template>
