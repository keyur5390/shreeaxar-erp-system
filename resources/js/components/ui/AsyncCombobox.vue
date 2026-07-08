<script setup lang="ts" generic="T">
import { onBeforeUnmount, ref, watch, type Ref } from 'vue'
type LoadOptions<T> = (query: string, signal: AbortSignal) => Promise<T[]>
const props = withDefaults(defineProps<{ loadOptions: LoadOptions<T>; modelValue?: T | null; placeholder?: string; renderOption?: (option: T) => string }>(), { placeholder: 'Search...' })
const emit = defineEmits<{ 'update:modelValue': [value: T | null] }>()
const query = ref('')
const options = ref([]) as Ref<T[]>
const loading = ref(false)
const open = ref(false)
const controller = ref<AbortController | null>(null)
let timer: number | undefined
const label = (option: T) => props.renderOption ? props.renderOption(option) : String(option)
const search = () => { window.clearTimeout(timer); timer = window.setTimeout(async () => { controller.value?.abort(); controller.value = new AbortController(); loading.value = true; try { options.value = await props.loadOptions(query.value, controller.value.signal) } catch (e) { if (!(e instanceof DOMException && e.name === 'AbortError')) throw e } finally { loading.value = false } }, 300) }
watch(query, search)
onBeforeUnmount(() => controller.value?.abort())
const choose = (option: T) => { emit('update:modelValue', option); query.value = label(option); open.value = false }
</script>
<template><div class="relative"><input v-model="query" class="w-full rounded-md border px-3 py-2 focus:border-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-accent" :placeholder="placeholder" @focus="open = true; search()" /><div v-if="open" class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-md border bg-white shadow-card"><div v-if="loading" class="px-3 py-2 text-sm text-slate-500">Loading...</div><button v-for="(option, index) in options" :key="index" type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-brand-accent" @click="choose(option)">{{ label(option) }}</button><div v-if="$slots.footer" class="border-t p-2"><slot name="footer" /></div></div></div></template>
