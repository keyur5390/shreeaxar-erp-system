<script setup lang="ts" generic="T extends object">
import { computed } from 'vue'
import { useBreakpoint } from '@/composables/useBreakpoint'
import SkeletonTable from './SkeletonTable.vue'

type Column<T> = { label: string; key: keyof T | string; render?: (row: T) => unknown }
const props = withDefaults(defineProps<{ columns: Column<T>[]; data: T[]; isLoading?: boolean; skeletonRows?: number; pagination?: { page: number; limit: number; total: number; totalPages: number } }>(), { isLoading: false, skeletonRows: 5 })
const emit = defineEmits<{ 'page-change': [page: number]; 'limit-change': [limit: number]; 'row-click': [row: T] }>()
const { isMobile } = useBreakpoint()
const hasRows = computed(() => props.data.length > 0)
const cellValue = (row: T, column: Column<T>) => column.render ? column.render(row) : row[column.key as keyof T]
</script>
<template>
  <div class="space-y-4">
    <slot name="topBar" />
    <SkeletonTable v-if="isLoading" :cols="columns.length" :rows="skeletonRows" />
    <slot v-else-if="!hasRows" name="emptyState"><div class="rounded-lg border bg-white p-8 text-center text-sm text-slate-500">No records found.</div></slot>
    <div v-else-if="isMobile" class="space-y-3">
      <article v-for="(row, rowIndex) in data" :key="rowIndex" class="cursor-pointer rounded-lg border bg-white p-4 shadow-card" @click="emit('row-click', row)">
        <dl class="space-y-3">
          <div v-for="column in columns" :key="String(column.key)" class="flex justify-between gap-4">
            <dt class="shrink-0 text-sm font-medium text-slate-500">{{ column.label }}</dt>
            <dd class="min-w-0 truncate text-right text-sm text-slate-900">
              <slot :name="`cell-${String(column.key)}`" :row="row" :value="cellValue(row, column)">{{ cellValue(row, column) }}</slot>
            </dd>
          </div>
        </dl>
      </article>
    </div>
    <div v-else class="overflow-hidden rounded-lg border bg-white shadow-card"><table class="min-w-full divide-y divide-slate-200"><thead class="bg-slate-50"><tr><th v-for="column in columns" :key="String(column.key)" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ column.label }}</th></tr></thead><tbody class="divide-y divide-slate-100"><tr v-for="(row, rowIndex) in data" :key="rowIndex" class="cursor-pointer hover:bg-slate-50" @click="emit('row-click', row)"><td v-for="column in columns" :key="String(column.key)" class="max-w-xs truncate px-4 py-3 text-sm text-slate-700" @click.stop="column.key === 'actions' ? undefined : emit('row-click', row)"><slot :name="`cell-${String(column.key)}`" :row="row" :value="cellValue(row, column)">{{ cellValue(row, column) }}</slot></td></tr></tbody></table></div>
    <div v-if="pagination" class="flex flex-col gap-3 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
      <span class="text-center sm:text-left">Page {{ pagination.page }} of {{ pagination.totalPages }} · {{ pagination.total }} total</span>
      <div class="flex items-center justify-center gap-2">
        <select v-if="!isMobile" class="rounded-md border px-2 py-1" :value="pagination.limit" @change="emit('limit-change', Number(($event.target as HTMLSelectElement).value))"><option v-for="limit in [10,25,50,100]" :key="limit" :value="limit">{{ limit }}</option></select>
        <button class="rounded-md border px-3 py-1.5 disabled:opacity-50" :disabled="pagination.page <= 1" @click="emit('page-change', pagination.page - 1)">Prev</button>
        <button class="rounded-md border px-3 py-1.5 disabled:opacity-50" :disabled="pagination.page >= pagination.totalPages" @click="emit('page-change', pagination.page + 1)">Next</button>
      </div>
    </div>
  </div>
</template>
