<script setup lang="ts">
import { provide, ref, watch } from 'vue'
import { tabsKey } from './tabs-context'

const props = withDefaults(defineProps<{
  defaultValue?: string
  modelValue?: string
}>(), {
  defaultValue: '',
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const activeTab = ref(props.modelValue || props.defaultValue)

watch(() => props.modelValue, (value) => {
  if (value !== undefined && value !== activeTab.value) {
    activeTab.value = value
  }
})

function setActiveTab(value: string) {
  activeTab.value = value
  emit('update:modelValue', value)
}

provide(tabsKey, { activeTab, setActiveTab })
</script>

<template>
  <div>
    <slot />
  </div>
</template>
