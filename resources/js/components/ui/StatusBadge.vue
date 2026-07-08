<script setup lang="ts">
import { computed } from 'vue'
const props = withDefaults(defineProps<{ label: string; color: string; size?: 'sm' | 'md' }>(), { size: 'md' })
const badgeStyle = computed(() => ({ backgroundColor: `${props.color}26`, color: props.color }))
</script>
<template><span :style="badgeStyle" :class="['inline-flex items-center rounded-full font-medium', size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-3 py-1 text-sm']">{{ label }}</span></template>
