<script setup lang="ts">
import { onUnmounted, ref, watch } from 'vue'
const props = withDefaults(defineProps<{ modelValue?: File | string | null; maxSizeMB?: number; accept?: string; shape?: 'circle' | 'square'; size?: number; onRemove?: () => void }>(), { maxSizeMB: 2, accept: 'image/*', shape: 'square', size: 128 })
const emit = defineEmits<{ 'update:modelValue': [value: File | null] }>()
const preview = ref<string | null>(typeof props.modelValue === 'string' ? props.modelValue : null)
const error = ref('')
const revoke = () => { if (preview.value?.startsWith('blob:')) URL.revokeObjectURL(preview.value) }
watch(() => props.modelValue, value => { revoke(); preview.value = typeof value === 'string' ? value : value instanceof File ? URL.createObjectURL(value) : null })
onUnmounted(revoke)
const select = (event: Event) => { const file = (event.target as HTMLInputElement).files?.[0]; error.value = ''; if (!file) return; if (file.size > props.maxSizeMB * 1024 * 1024) { error.value = `Image must be ${props.maxSizeMB}MB or smaller`; return } emit('update:modelValue', file) }
const remove = () => { revoke(); preview.value = null; props.onRemove?.(); emit('update:modelValue', null) }
</script>
<template><div class="space-y-2"><label :class="['flex aspect-square cursor-pointer items-center justify-center overflow-hidden border border-dashed bg-white text-sm text-slate-500', shape === 'circle' ? 'rounded-full' : 'rounded-lg']" :style="{ width: `${size}px`, height: `${size}px` }"><img v-if="preview" :src="preview" class="h-full w-full object-cover" alt="Preview" /><span v-else>Upload</span><input type="file" class="sr-only" :accept="accept" @change="select" /></label><button v-if="preview" type="button" class="text-sm text-red-600" @click="remove">Remove Photo</button><p v-if="error" class="text-sm text-red-600">{{ error }}</p></div></template>
