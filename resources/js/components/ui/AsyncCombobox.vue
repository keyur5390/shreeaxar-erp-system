<script setup lang="ts" generic="T">
import { onBeforeUnmount, ref, watch, nextTick, type Ref } from 'vue'
type LoadOptions<T> = (query: string, signal: AbortSignal) => Promise<T[]>
const props = withDefaults(defineProps<{
  loadOptions: LoadOptions<T>
  modelValue?: T | null
  placeholder?: string
  renderOption?: (option: T) => string
  minChars?: number
}>(), { placeholder: 'Search...', minChars: 2 })
const emit = defineEmits<{ 'update:modelValue': [value: T | null] }>()
const query = ref('')
const options = ref([]) as Ref<T[]>
const loading = ref(false)
const loadError = ref('')
const open = ref(false)
const controller = ref<AbortController | null>(null)
let timer: number | undefined
const label = (option: T) => props.renderOption ? props.renderOption(option) : String(option)
const search = () => {
  window.clearTimeout(timer)
  timer = window.setTimeout(async () => {
    if (syncingFromModel) return
    if (query.value.trim().length < props.minChars) {
      options.value = []
      loadError.value = ''
      loading.value = false
      return
    }
    controller.value?.abort()
    controller.value = new AbortController()
    loading.value = true
    loadError.value = ''
    try {
      options.value = await props.loadOptions(query.value, controller.value.signal)
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return
      options.value = []
      loadError.value = e instanceof Error ? e.message : 'Search failed.'
    } finally {
      loading.value = false
    }
  }, 300)
}
watch(query, search)
let syncingFromModel = false
watch(() => props.modelValue, (value) => {
  syncingFromModel = true
  query.value = value ? label(value) : ''
  nextTick(() => {
    syncingFromModel = false
  })
}, { immediate: true, deep: true })
onBeforeUnmount(() => controller.value?.abort())
const choose = (option: T) => { emit('update:modelValue', option); query.value = label(option); open.value = false }
function closeDropdownLater() {
  window.setTimeout(() => { open.value = false }, 150)
}
</script>
<template>
  <div class="relative">
    <input
      v-model="query"
      class="w-full rounded-md border px-3 py-2 focus:border-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-accent"
      :placeholder="placeholder"
      @focus="open = true; search()"
      @blur="closeDropdownLater"
    />
    <div v-if="open" class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-md border bg-white shadow-card">
      <div v-if="loading" class="px-3 py-2 text-sm text-slate-500">Loading...</div>
      <div v-else-if="loadError" class="px-3 py-2 text-sm text-red-600">{{ loadError }}</div>
      <div v-else-if="query.trim().length < minChars" class="px-3 py-2 text-sm text-slate-500">
        Type at least {{ minChars }} characters to search
      </div>
      <div v-else-if="!options.length" class="px-3 py-2 text-sm text-slate-500">No results found.</div>
      <button
        v-for="(option, index) in options"
        :key="index"
        type="button"
        class="block w-full px-3 py-2 text-left text-sm hover:bg-brand-accent"
        @mousedown.prevent="choose(option)"
      >
        <slot name="option" :option="option">
          {{ label(option) }}
        </slot>
      </button>
      <div v-if="$slots.footer" class="border-t p-2">
        <slot name="footer" />
      </div>
    </div>
  </div>
</template>
