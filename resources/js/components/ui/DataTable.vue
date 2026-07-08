<script setup lang="ts" generic="T extends Record<string, unknown>">
import { computed } from 'vue'
import { useBreakpoint } from '@/composables/useBreakpoint'
import SkeletonTable from './SkeletonTable.vue'

type Column<T> = { label: string; key: keyof T | string; render?: (row: T) => unknown }
const props = withDefaults(defineProps<{ columns: Column<T>[]; data: T[]; isLoading?: boolean; pagination?: { page: number; limit: number; total: number; totalPages: number } }>(), { isLoading: false })
const emit = defineEmits<{ 'page-change': [page: number]; 'limit-change': [limit: number] }>()
const { isMobile } = useBreakpoint()
const hasRows = computed(() => props.data.length > 0)
const cellValue = (row: T, column: Column<T>) => column.render ? column.render(row) : row[column.key as keyof T]
</script>
<template>
  <div class="space-y-4">
    <slot name="topBar" />
    <SkeletonTable v-if="isLoading" :cols="columns.length" />
    <slot v-else-if="!hasRows" name="emptyState"><div class="rounded-lg border bg-white p-8 text-center text-sm text-slate-500">No records found.</div></slot>
    <div v-else-if="isMobile" class="space-y-3">
      <article v-for="(row, rowIndex) in data" :key="rowIndex" class="rounded-lg border bg-white p-4 shadow-card">
        <dl class="space-y-3"><div v-for="column in columns" :key="String(column.key)" class="flex justify-between gap-4"><dt class="text-sm font-medium text-slate-500">{{ column.label }}</dt><dd class="text-right text-sm text-slate-900">{{ cellValue(row, column) }}</dd></div></dl>
      </article>
    </div>
    <div v-else class="overflow-hidden rounded-lg border bg-white shadow-card"><table class="min-w-full divide-y divide-slate-200"><thead class="bg-slate-50"><tr><th v-for="column in columns" :key="String(column.key)" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ column.label }}</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="(row, rowIndex) in data" :key="rowIndex" class="hover:bg-slate-50"><td v-for="column in columns" :key="String(column.key)" class="px-4 py-3 text-sm text-slate-700">{{ cellValue(row, column) }}</td></tr></tbody></table></div>
    <div v-if="pagination" class="flex flex-col gap-3 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
      <span>Page {{ pagination.page }} of {{ pagination.totalPages }} · {{ pagination.total }} total</span>
      <div class="flex items-center gap-2"><select class="rounded-md border px-2 py-1" :value="pagination.limit" @change="emit('limit-change', Number(($event.target as HTMLSelectElement).value))"><option v-for="limit in [10,25,50,100]" :key="limit" :value="limit">{{ limit }}</option></select><button class="rounded-md border px-3 py-1 disabled:opacity-50" :disabled="pagination.page <= 1" @click="emit('page-change', pagination.page - 1)">Previous</button><button class="rounded-md border px-3 py-1 disabled:opacity-50" :disabled="pagination.page >= pagination.totalPages" @click="emit('page-change', pagination.page + 1)">Next</button></div>
    </div>
  </div>
</template>
