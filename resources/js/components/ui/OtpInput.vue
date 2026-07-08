<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
const props = withDefaults(defineProps<{ length?: number; modelValue?: string; disabled?: boolean }>(), { length: 6, modelValue: '', disabled: false })
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const inputs = ref<HTMLInputElement[]>([])
const digits = computed(() => Array.from({ length: props.length }, (_, i) => props.modelValue[i] ?? ''))
const update = (i: number, value: string) => { const next = digits.value; next[i] = value.replace(/\D/g, '').slice(-1); emit('update:modelValue', next.join('')); if (next[i] && i < props.length - 1) nextTick(() => inputs.value[i + 1]?.focus()) }
const onKeydown = (e: KeyboardEvent, i: number) => { if (e.key === 'Backspace' && !digits.value[i] && i > 0) nextTick(() => inputs.value[i - 1]?.focus()) }
const onPaste = (e: ClipboardEvent) => { const text = e.clipboardData?.getData('text').replace(/\D/g, '').slice(0, props.length); if (text) { e.preventDefault(); emit('update:modelValue', text) } }
watch(() => props.length, () => emit('update:modelValue', props.modelValue.slice(0, props.length)))
</script>
<template><div class="flex gap-2" @paste="onPaste"><input v-for="(_, i) in length" :key="i" :ref="el => { if (el) inputs[i] = el as HTMLInputElement }" :value="digits[i]" :disabled="disabled" inputmode="numeric" maxlength="1" class="h-11 w-10 rounded-md border text-center text-lg font-semibold focus:border-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-accent disabled:bg-slate-100" @input="update(i, ($event.target as HTMLInputElement).value)" @keydown="onKeydown($event, i)" /></div></template>
