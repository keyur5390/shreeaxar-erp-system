<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ password: string }>()
const score = computed(() => [props.password.length >= 8, /[A-Z]/.test(props.password), /[0-9]/.test(props.password), /[^A-Za-z0-9]/.test(props.password)].filter(Boolean).length)
const strength = computed(() => score.value <= 1 ? 'weak' : score.value <= 3 ? 'fair' : 'strong')
const barClass = computed(() => strength.value === 'strong' ? 'bg-emerald-500 w-full' : strength.value === 'fair' ? 'bg-amber-500 w-2/3' : 'bg-red-500 w-1/3')
</script>
<template><div><div class="h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full transition-all" :class="barClass" /></div><p class="mt-1 text-xs capitalize text-slate-500">{{ strength }}</p></div></template>
