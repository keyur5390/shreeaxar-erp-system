<script setup lang="ts">
import { ref, watch } from 'vue'
const props = withDefaults(defineProps<{ open: boolean; title: string; description?: string; confirmLabel?: string; confirmVariant?: 'default' | 'destructive'; requireType?: string }>(), { confirmLabel: 'Confirm', confirmVariant: 'default' })
const emit = defineEmits<{ confirm: []; cancel: [] }>()
const typed = ref('')
watch(() => props.open, () => { typed.value = '' })
</script>
<template><Teleport to="body"><div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"><section role="alertdialog" class="w-full max-w-md rounded-lg bg-white p-6 shadow-card"><h2 class="text-lg font-semibold">{{ title }}</h2><p v-if="description" class="mt-2 text-sm text-slate-600">{{ description }}</p><input v-if="requireType" v-model="typed" class="mt-4 w-full rounded-md border px-3 py-2" :placeholder="`Type ${requireType} to continue`" /><div class="mt-6 flex justify-end gap-2"><button class="rounded-md border px-4 py-2" @click="emit('cancel')">Cancel</button><button :disabled="!!requireType && typed !== requireType" :class="['rounded-md px-4 py-2 text-white disabled:opacity-50', confirmVariant === 'destructive' ? 'bg-red-600' : 'bg-brand-blue']" @click="emit('confirm')">{{ confirmLabel }}</button></div></section></div></Teleport></template>
