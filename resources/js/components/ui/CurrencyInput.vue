<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ modelValue: number | null; readOnly?: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [value: number | null] }>()
const display = computed(() => props.modelValue === null || Number.isNaN(props.modelValue) ? '' : `RWF ${props.modelValue.toLocaleString('en-US')}`)
const parse = (value: string) => { const clean = value.replace(/[^0-9-]/g, ''); emit('update:modelValue', clean ? Number.parseInt(clean, 10) : null) }
</script>
<template><input :value="display" :readonly="readOnly" class="w-full rounded-md border px-3 py-2 focus:border-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-accent read-only:bg-slate-100" inputmode="numeric" @input="parse(($event.target as HTMLInputElement).value)" @paste="parse(($event.clipboardData?.getData('text') ?? ''))" /></template>
