<script setup lang="ts">
import { computed, provide, ref } from 'vue'
import { popoverKey, type PopoverSide } from './popover-context'

const props = withDefaults(defineProps<{
  open?: boolean
  side?: PopoverSide
}>(), {
  side: 'bottom',
})

const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const open = computed({
  get: () => props.open ?? false,
  set: (value: boolean) => emit('update:open', value),
})

const triggerRef = ref<HTMLElement | null>(null)
const side = computed(() => props.side)

function toggle() {
  open.value = !open.value
}

function close() {
  open.value = false
}

function setTriggerRef(el: HTMLElement | null) {
  triggerRef.value = el
}

provide(popoverKey, { open, side, triggerRef, setTriggerRef, toggle, close })
</script>

<template>
  <div class="relative inline-block">
    <slot />
  </div>
</template>
