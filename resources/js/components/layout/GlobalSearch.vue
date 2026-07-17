<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { useDebounceFn } from '@vueuse/core'
import { Building2, FileText, Package, Search, X } from 'lucide-vue-next'
import { searchService, type GlobalSearchItem } from '@/services/search.service'
import { useAuthStore } from '@/stores/auth.store'

defineOptions({ name: 'GlobalSearch' })

const props = withDefaults(defineProps<{
  overlay?: boolean
}>(), {
  overlay: false,
})

const emit = defineEmits<{
  close: []
}>()

const router = useRouter()
const authStore = useAuthStore()

const query = ref('')
const isOpen = ref(false)
const isLoading = ref(false)
const activeIndex = ref(-1)
const results = ref<{ customers: GlobalSearchItem[]; products: GlobalSearchItem[]; quotations: GlobalSearchItem[] }>({
  customers: [],
  products: [],
  quotations: [],
})

const inputRef = ref<HTMLInputElement | null>(null)
const containerRef = ref<HTMLElement | null>(null)
let cancelSource: ReturnType<typeof axios.CancelToken.source> | null = null

type FlatItem = GlobalSearchItem & { group: string; icon: typeof Building2 }

const groups = computed(() => {
  const list: Array<{ key: string; label: string; icon: typeof Building2; items: GlobalSearchItem[] }> = []

  if (authStore.canView('customers') && results.value.customers.length) {
    list.push({ key: 'customers', label: 'Customers', icon: Building2, items: results.value.customers })
  }
  if (authStore.canView('products') && results.value.products.length) {
    list.push({ key: 'products', label: 'Products', icon: Package, items: results.value.products })
  }
  if (authStore.canView('quotations') && results.value.quotations.length) {
    list.push({ key: 'quotations', label: 'Quotations', icon: FileText, items: results.value.quotations })
  }

  return list
})

const flatItems = computed<FlatItem[]>(() =>
  groups.value.flatMap((group) =>
    group.items.map((item) => ({ ...item, group: group.label, icon: group.icon })),
  ),
)

const showDropdown = computed(() => isOpen.value && query.value.trim().length >= 2)
const hasResults = computed(() => flatItems.value.length > 0)

function resetResults(): void {
  results.value = { customers: [], products: [], quotations: [] }
  activeIndex.value = -1
}

function cancelPending(): void {
  cancelSource?.cancel()
  cancelSource = null
}

async function runSearch(value: string): Promise<void> {
  const trimmed = value.trim()
  if (trimmed.length < 2) {
    cancelPending()
    resetResults()
    isLoading.value = false
    return
  }

  cancelPending()
  cancelSource = axios.CancelToken.source()
  isLoading.value = true

  try {
    const data = await searchService.search(trimmed, cancelSource.token)
    results.value = {
      customers: data.customers,
      products: data.products,
      quotations: data.quotations,
    }
    activeIndex.value = flatItems.value.length ? 0 : -1
  } catch (error) {
    if (axios.isCancel(error)) return
    resetResults()
  } finally {
    isLoading.value = false
  }
}

const debouncedSearch = useDebounceFn((value: string) => runSearch(value), 300)

watch(query, (value) => {
  if (!isOpen.value) isOpen.value = true
  debouncedSearch(value)
})

function openSearch(): void {
  isOpen.value = true
  nextTick(() => inputRef.value?.focus())
}

function closeSearch(): void {
  isOpen.value = false
  query.value = ''
  resetResults()
  cancelPending()
  emit('close')
}

function navigateTo(item: GlobalSearchItem): void {
  closeSearch()
  router.push(item.url)
}

function onKeydown(event: KeyboardEvent): void {
  if (!showDropdown.value) {
    if (event.key === 'Escape') closeSearch()
    return
  }

  if (event.key === 'ArrowDown') {
    event.preventDefault()
    if (!flatItems.value.length) return
    activeIndex.value = (activeIndex.value + 1) % flatItems.value.length
  } else if (event.key === 'ArrowUp') {
    event.preventDefault()
    if (!flatItems.value.length) return
    activeIndex.value = activeIndex.value <= 0 ? flatItems.value.length - 1 : activeIndex.value - 1
  } else if (event.key === 'Enter') {
    event.preventDefault()
    const item = flatItems.value[activeIndex.value]
    if (item) navigateTo(item)
  } else if (event.key === 'Escape') {
    event.preventDefault()
    closeSearch()
  }
}

function onClickOutside(event: MouseEvent): void {
  if (!containerRef.value?.contains(event.target as Node)) {
    closeSearch()
  }
}

onMounted(() => document.addEventListener('mousedown', onClickOutside))

watch(() => props.overlay, (isOverlay) => {
  if (isOverlay) {
    isOpen.value = true
    nextTick(() => inputRef.value?.focus())
  }
}, { immediate: true })
onUnmounted(() => {
  document.removeEventListener('mousedown', onClickOutside)
  cancelPending()
})

defineExpose({ openSearch, closeSearch })
</script>

<template>
  <div
    ref="containerRef"
    class="relative w-full"
    :class="overlay ? 'fixed inset-0 z-50 flex flex-col bg-white p-4' : ''"
  >
    <div v-if="overlay" class="mb-3 flex items-center justify-between">
      <p class="text-sm font-semibold text-slate-900">Search</p>
      <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Close search" @click="closeSearch">
        <X class="h-5 w-5" />
      </button>
    </div>

    <label
      class="flex w-full items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500 focus-within:border-brand-teal focus-within:bg-white"
      :class="overlay ? 'max-w-none' : 'max-w-xl'"
    >
      <Search class="h-4 w-4 shrink-0" />
      <input
        ref="inputRef"
        v-model="query"
        class="min-w-0 flex-1 bg-transparent outline-none"
        type="search"
        placeholder="Search customers, products, quotations…"
        autocomplete="off"
        @keydown="onKeydown"
      />
      <kbd v-if="!overlay" class="hidden rounded border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-400 sm:inline">Ctrl K</kbd>
    </label>

    <div
      v-if="showDropdown"
      class="absolute left-0 right-0 top-full z-50 mt-2 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg"
      :class="overlay ? 'static mt-3 flex-1 overflow-y-auto shadow-none' : 'max-h-96'"
    >
      <div v-if="isLoading" class="px-4 py-6 text-center text-sm text-slate-500">
        Searching…
      </div>
      <div v-else-if="!hasResults" class="px-4 py-6 text-center text-sm text-slate-500">
        No results for "{{ query.trim() }}"
      </div>
      <template v-else>
        <div v-for="group in groups" :key="group.key" class="border-b last:border-b-0">
          <p class="bg-slate-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
            {{ group.label }}
          </p>
          <ul>
            <li
              v-for="item in group.items"
              :key="`${group.key}-${item.id}`"
              class="cursor-pointer px-4 py-3 hover:bg-slate-50"
              :class="{ 'bg-slate-100': flatItems[activeIndex]?.id === item.id && flatItems[activeIndex]?.group === group.label }"
              @mouseenter="activeIndex = flatItems.findIndex((entry) => entry.id === item.id && entry.group === group.label)"
              @click="navigateTo(item)"
            >
              <div class="flex items-center gap-3">
                <component :is="group.icon" class="h-4 w-4 shrink-0 text-slate-400" />
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium text-slate-900">{{ item.label }}</p>
                  <p v-if="item.sub" class="truncate text-xs text-slate-500">{{ item.sub }}</p>
                </div>
              </div>
            </li>
          </ul>
        </div>
      </template>
    </div>
  </div>
</template>
