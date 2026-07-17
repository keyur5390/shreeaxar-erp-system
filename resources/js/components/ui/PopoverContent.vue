<script setup lang="ts">
import { computed, inject, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { Teleport } from 'vue'
import { cn } from '@/lib/utils'
import { useRequiredInject } from '@/lib/inject-context'
import { popoverKey } from './popover-context'

const props = withDefaults(defineProps<{
  class?: string
  align?: 'start' | 'center' | 'end'
}>(), {
  align: 'start',
})

const popover = useRequiredInject(popoverKey, 'PopoverContent must be used within Popover')

const contentRef = ref<HTMLElement | null>(null)
const position = ref({ top: 0, left: 0 })

const isOpen = computed(() => popover.open.value)

function updatePosition() {
  const trigger = popover.triggerRef.value
  const content = contentRef.value
  if (!trigger || !content) return

  const rect = trigger.getBoundingClientRect()
  const contentRect = content.getBoundingClientRect()
  const gap = 8
  let top = 0
  let left = 0

  switch (popover.side.value) {
    case 'top':
      top = rect.top - contentRect.height - gap
      break
    case 'left':
      top = rect.top
      left = rect.left - contentRect.width - gap
      break
    case 'right':
      top = rect.top
      left = rect.right + gap
      break
    default:
      top = rect.bottom + gap
  }

  if (popover.side.value === 'top' || popover.side.value === 'bottom') {
    if (props.align === 'end') {
      left = rect.right - contentRect.width
    } else if (props.align === 'center') {
      left = rect.left + (rect.width - contentRect.width) / 2
    } else {
      left = rect.left
    }
  }

  const padding = 8
  left = Math.max(padding, Math.min(left, window.innerWidth - contentRect.width - padding))
  top = Math.max(padding, Math.min(top, window.innerHeight - contentRect.height - padding))

  position.value = { top, left }
}

function onClickOutside(event: MouseEvent) {
  const target = event.target as Node
  if (contentRef.value?.contains(target) || popover.triggerRef.value?.contains(target)) return
  popover.close()
}

function onEscape(event: KeyboardEvent) {
  if (event.key === 'Escape') popover.close()
}

watch(isOpen, async (open) => {
  if (open) {
    await nextTick()
    updatePosition()
    document.addEventListener('mousedown', onClickOutside)
    document.addEventListener('keydown', onEscape)
    window.addEventListener('resize', updatePosition)
    window.addEventListener('scroll', updatePosition, true)
  } else {
    document.removeEventListener('mousedown', onClickOutside)
    document.removeEventListener('keydown', onEscape)
    window.removeEventListener('resize', updatePosition)
    window.removeEventListener('scroll', updatePosition, true)
  }
})

onMounted(() => {
  if (isOpen.value) updatePosition()
})

onUnmounted(() => {
  document.removeEventListener('mousedown', onClickOutside)
  document.removeEventListener('keydown', onEscape)
  window.removeEventListener('resize', updatePosition)
  window.removeEventListener('scroll', updatePosition, true)
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      ref="contentRef"
      :class="cn(
        'fixed z-50 w-72 rounded-md border bg-white p-4 shadow-lg outline-none',
        props.class,
      )"
      :style="{ top: `${position.top}px`, left: `${position.left}px` }"
      @click.stop
    >
      <slot />
    </div>
  </Teleport>
</template>
