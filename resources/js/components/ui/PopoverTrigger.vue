<script setup lang="ts">
import { inject, onMounted, onUnmounted, ref } from 'vue'
import { useRequiredInject } from '@/lib/inject-context'
import { popoverKey } from './popover-context'

const popover = useRequiredInject(popoverKey, 'PopoverTrigger must be used within Popover')

const triggerEl = ref<HTMLElement | null>(null)

onMounted(() => {
  popover.setTriggerRef(triggerEl.value)
})

onUnmounted(() => {
  popover.setTriggerRef(null)
})

function onClick() {
  popover.toggle()
}
</script>

<template>
  <button
    ref="triggerEl"
    type="button"
    class="inline-flex"
    @click.stop="onClick"
  >
    <slot />
  </button>
</template>
