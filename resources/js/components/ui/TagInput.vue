<script setup lang="ts">
import { ref } from 'vue'
const props = withDefaults(defineProps<{ modelValue: string[]; placeholder?: string; validateTag?: (tag: string) => boolean }>(), { placeholder: 'Add tag' })
const emit = defineEmits<{ 'update:modelValue': [value: string[]] }>()
const input = ref('')
const invalid = ref<string[]>([])
const add = () => { const tag = input.value.trim().replace(/,$/, ''); if (!tag) return; if (props.validateTag && !props.validateTag(tag)) { invalid.value = [...new Set([...invalid.value, tag])]; input.value = ''; return } if (!props.modelValue.includes(tag)) emit('update:modelValue', [...props.modelValue, tag]); input.value = '' }
const remove = (tag: string) => emit('update:modelValue', props.modelValue.filter(t => t !== tag))
</script>
<template><div class="rounded-md border bg-white p-2"><div class="flex flex-wrap gap-2"><span v-for="tag in modelValue" :key="tag" class="inline-flex items-center gap-1 rounded-full bg-brand-accent px-2 py-1 text-sm text-brand-blue">{{ tag }}<button type="button" @click="remove(tag)">×</button></span><span v-for="tag in invalid" :key="tag" class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-1 text-sm text-red-700">{{ tag }}<button type="button" @click="invalid = invalid.filter(t => t !== tag)">×</button></span><input v-model="input" class="min-w-32 flex-1 outline-none" :placeholder="placeholder" @keydown.enter.prevent="add" @keydown.comma.prevent="add" /></div></div></template>
