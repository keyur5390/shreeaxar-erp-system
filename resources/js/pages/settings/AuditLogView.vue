<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { ChevronDown, ChevronRight, Download, Trash2, X } from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import FilterPanel from '@/components/ui/FilterPanel.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import SkeletonTable from '@/components/ui/SkeletonTable.vue'
import { auditLogService, type AuditLogItem } from '@/services/audit-log.service'
import { useToast } from '@/composables/useToast'
import { formatDateTime } from '@/utils/formatters'
import { formatJsonLines, getChangedJsonKeys } from '@/utils/auditDiff'

const { toast } = useToast()
const queryClient = useQueryClient()

const moduleFilter = ref('')
const actionFilter = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const page = ref(1)
const limit = ref(25)
const expandedId = ref<string | null>(null)
const showCleanupDialog = ref(false)
const cleanupDays = ref(90)

const filters = computed(() => ({
  page: page.value,
  limit: limit.value,
  module: moduleFilter.value || undefined,
  action: actionFilter.value || undefined,
  date_from: dateFrom.value || undefined,
  date_to: dateTo.value || undefined,
}))

const auditLogsQuery = useQuery({
  queryKey: computed(() => ['audit-logs', filters.value]),
  queryFn: () => auditLogService.list(filters.value),
})

const logs = computed(() => auditLogsQuery.data.value?.items ?? [])
const pagination = computed(() => auditLogsQuery.data.value?.pagination)

const tablePagination = computed(() => {
  if (!pagination.value) return undefined
  return {
    page: pagination.value.current_page,
    limit: pagination.value.per_page,
    total: pagination.value.total,
    totalPages: pagination.value.last_page,
  }
})

const loadError = computed(() => auditLogsQuery.isError.value
  ? (auditLogsQuery.error.value instanceof Error ? auditLogsQuery.error.value.message : 'Unable to load audit logs.')
  : '')

const moduleOptions = computed(() => {
  const values = new Set(logs.value.map((log) => log.module))
  return Array.from(values).sort()
})

const actionOptions = computed(() => {
  const values = new Set(logs.value.map((log) => log.action))
  return Array.from(values).sort()
})

const hasActiveFilters = computed(() => Boolean(moduleFilter.value || actionFilter.value || dateFrom.value || dateTo.value))

watch([moduleFilter, actionFilter, dateFrom, dateTo], () => {
  page.value = 1
})

function clearFilters() {
  moduleFilter.value = ''
  actionFilter.value = ''
  dateFrom.value = ''
  dateTo.value = ''
  page.value = 1
}

function toggleRow(log: AuditLogItem) {
  expandedId.value = expandedId.value === log.id ? null : log.id
}

function diffForLog(log: AuditLogItem) {
  const changedKeys = getChangedJsonKeys(log.old_values, log.new_values)
  return {
    changedKeys,
    oldLines: formatJsonLines(log.old_values, changedKeys),
    newLines: formatJsonLines(log.new_values, changedKeys),
  }
}

const exportMutation = useMutation({
  mutationFn: () => auditLogService.exportCsv({
    module: moduleFilter.value || undefined,
    action: actionFilter.value || undefined,
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
  }),
  onSuccess: (blob) => {
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `audit-logs-${new Date().toISOString().slice(0, 10)}.csv`
    link.click()
    URL.revokeObjectURL(url)
    toast('Audit log export started.', 'success')
  },
  onError: (error: unknown) => {
    toast(error instanceof Error ? error.message : 'Export failed.', 'error')
  },
})

const cleanupMutation = useMutation({
  mutationFn: () => auditLogService.cleanup(cleanupDays.value),
  onSuccess: (data) => {
    queryClient.invalidateQueries({ queryKey: ['audit-logs'] })
    showCleanupDialog.value = false
    toast(`Deleted ${data.deleted_count} audit log(s).`, 'success')
  },
  onError: (error: unknown) => {
    toast(error instanceof Error ? error.message : 'Cleanup failed.', 'error')
  },
})
</script>

<template>
  <div class="space-y-6">
    <PageHeader
      title="Audit Log"
      subtitle="Review system activity and changes. Super Admin only."
      :breadcrumb="[{ label: 'Settings', href: '/settings' }, { label: 'Audit Log' }]"
    />

    <FilterPanel :has-active-filters="hasActiveFilters" title="Filter logs">
      <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end">
        <label class="flex min-w-[160px] flex-1 flex-col gap-1 text-sm">
          <span class="font-medium text-slate-600">Module</span>
          <input
            v-model="moduleFilter"
            list="audit-module-options"
            class="rounded-md border px-3 py-2"
            placeholder="All modules"
          />
          <datalist id="audit-module-options">
            <option v-for="option in moduleOptions" :key="option" :value="option" />
          </datalist>
        </label>

        <label class="flex min-w-[160px] flex-1 flex-col gap-1 text-sm">
          <span class="font-medium text-slate-600">Action</span>
          <input
            v-model="actionFilter"
            list="audit-action-options"
            class="rounded-md border px-3 py-2"
            placeholder="All actions"
          />
          <datalist id="audit-action-options">
            <option v-for="option in actionOptions" :key="option" :value="option" />
          </datalist>
        </label>

        <label class="flex min-w-[160px] flex-1 flex-col gap-1 text-sm">
          <span class="font-medium text-slate-600">Date from</span>
          <input v-model="dateFrom" type="date" class="rounded-md border px-3 py-2" />
        </label>

        <label class="flex min-w-[160px] flex-1 flex-col gap-1 text-sm">
          <span class="font-medium text-slate-600">Date to</span>
          <input v-model="dateTo" type="date" class="rounded-md border px-3 py-2" />
        </label>

        <div class="flex flex-wrap gap-2">
          <button
            v-if="hasActiveFilters"
            type="button"
            class="inline-flex items-center gap-1 rounded-md border px-3 py-2 text-sm"
            @click="clearFilters"
          >
            <X class="h-4 w-4" />
            Clear
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm hover:bg-slate-50"
            :disabled="exportMutation.isPending.value"
            @click="exportMutation.mutate()"
          >
            <Download class="h-4 w-4" />
            Export CSV
          </button>
        </div>
      </div>
    </FilterPanel>

    <div v-if="loadError" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
      {{ loadError }}
    </div>

    <SkeletonTable v-if="auditLogsQuery.isLoading.value" :cols="6" :rows="8" />

    <div v-else class="overflow-hidden rounded-lg border bg-white shadow-card">
      <div v-if="!logs.length" class="p-8 text-center text-sm text-slate-500">
        No audit logs found.
      </div>

      <!-- Mobile card list -->
      <div v-else class="divide-y lg:hidden">
        <div
          v-for="log in logs"
          :key="`mobile-${log.id}`"
          class="p-4"
        >
          <button
            type="button"
            class="flex w-full items-start justify-between gap-2 text-left"
            @click="toggleRow(log)"
          >
            <div class="min-w-0">
              <p class="text-sm font-semibold text-slate-900">{{ log.action }}</p>
              <p class="mt-0.5 text-xs text-slate-500">{{ log.module }}</p>
              <p class="mt-1 text-xs text-slate-600">{{ formatDateTime(log.created_at) }}</p>
              <p class="mt-1 truncate text-xs text-slate-500">{{ log.user_email || '—' }}</p>
            </div>
            <ChevronDown v-if="expandedId === log.id" class="mt-1 h-4 w-4 shrink-0 text-slate-400" />
            <ChevronRight v-else class="mt-1 h-4 w-4 shrink-0 text-slate-400" />
          </button>
          <div v-if="expandedId === log.id" class="mt-3 space-y-3 rounded-md border bg-slate-50 p-3">
            <p class="text-xs text-slate-600"><span class="font-medium">Record:</span> {{ log.record_id || '—' }}</p>
            <p class="text-xs text-slate-600"><span class="font-medium">IP:</span> {{ log.ip_address || '—' }}</p>
            <div class="grid gap-3">
              <div>
                <h4 class="mb-1 text-xs font-semibold text-slate-700">Old Values</h4>
                <pre v-if="!diffForLog(log).oldLines.length" class="rounded border bg-white p-2 text-xs text-slate-500">No old values.</pre>
                <div v-else class="space-y-1">
                  <div v-for="line in diffForLog(log).oldLines" :key="`m-old-${log.id}-${line.key}`" class="rounded border bg-white p-2 text-xs">
                    <p class="font-semibold text-slate-600">{{ line.key }}</p>
                    <pre class="mt-1 overflow-x-auto whitespace-pre-wrap text-slate-800">{{ line.value }}</pre>
                  </div>
                </div>
              </div>
              <div>
                <h4 class="mb-1 text-xs font-semibold text-slate-700">New Values</h4>
                <pre v-if="!diffForLog(log).newLines.length" class="rounded border bg-white p-2 text-xs text-slate-500">No new values.</pre>
                <div v-else class="space-y-1">
                  <div v-for="line in diffForLog(log).newLines" :key="`m-new-${log.id}-${line.key}`" class="rounded border bg-white p-2 text-xs">
                    <p class="font-semibold text-slate-600">{{ line.key }}</p>
                    <pre class="mt-1 overflow-x-auto whitespace-pre-wrap text-slate-800">{{ line.value }}</pre>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Desktop table -->
      <div v-if="logs.length" class="hidden overflow-x-auto lg:block">
        <table class="min-w-full divide-y divide-slate-200">
          <thead class="bg-slate-50">
            <tr>
              <th class="w-8 px-2 py-3" />
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Date/Time</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">User Email</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Module</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Record ID</th>
              <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">IP</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <template v-for="log in logs" :key="log.id">
              <tr
                class="cursor-pointer hover:bg-slate-50"
                @click="toggleRow(log)"
              >
                <td class="px-2 py-3 text-slate-400">
                  <ChevronDown v-if="expandedId === log.id" class="h-4 w-4" />
                  <ChevronRight v-else class="h-4 w-4" />
                </td>
                <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-700">{{ formatDateTime(log.created_at) }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ log.user_email || '—' }}</td>
                <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ log.action }}</td>
                <td class="px-4 py-3 text-sm text-slate-700">{{ log.module }}</td>
                <td class="max-w-[140px] truncate px-4 py-3 text-sm font-mono text-slate-600">{{ log.record_id || '—' }}</td>
                <td class="px-4 py-3 text-sm text-slate-600">{{ log.ip_address || '—' }}</td>
              </tr>
              <tr v-if="expandedId === log.id" class="bg-slate-50/80">
                <td colspan="7" class="px-4 py-4">
                  <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                      <h4 class="mb-2 text-sm font-semibold text-slate-700">Old Values</h4>
                      <pre v-if="!diffForLog(log).oldLines.length" class="rounded-md border bg-white p-3 text-xs text-slate-500">No old values recorded.</pre>
                      <div v-else class="space-y-2">
                        <div
                          v-for="line in diffForLog(log).oldLines"
                          :key="`old-${log.id}-${line.key}`"
                          :class="['rounded-md border p-3', line.changed ? 'border-yellow-300 bg-yellow-50' : 'bg-white']"
                        >
                          <p class="mb-1 text-xs font-semibold text-slate-600">{{ line.key }}</p>
                          <pre class="overflow-x-auto text-xs text-slate-800">{{ line.value }}</pre>
                        </div>
                      </div>
                    </div>
                    <div>
                      <h4 class="mb-2 text-sm font-semibold text-slate-700">New Values</h4>
                      <pre v-if="!diffForLog(log).newLines.length" class="rounded-md border bg-white p-3 text-xs text-slate-500">No new values recorded.</pre>
                      <div v-else class="space-y-2">
                        <div
                          v-for="line in diffForLog(log).newLines"
                          :key="`new-${log.id}-${line.key}`"
                          :class="['rounded-md border p-3', line.changed ? 'border-yellow-300 bg-yellow-50' : 'bg-white']"
                        >
                          <p class="mb-1 text-xs font-semibold text-slate-600">{{ line.key }}</p>
                          <pre class="overflow-x-auto text-xs text-slate-800">{{ line.value }}</pre>
                        </div>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <div
        v-if="tablePagination"
        class="flex flex-col gap-3 border-t px-4 py-3 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between"
      >
        <span>Page {{ tablePagination.page }} of {{ tablePagination.totalPages }} · {{ tablePagination.total }} total</span>
        <div class="flex items-center gap-2">
          <select
            class="rounded-md border px-2 py-1"
            :value="limit"
            @change="limit = Number(($event.target as HTMLSelectElement).value); page = 1"
          >
            <option v-for="option in [10, 25, 50, 100]" :key="option" :value="option">{{ option }}</option>
          </select>
          <button
            class="rounded-md border px-3 py-1.5 disabled:opacity-50"
            :disabled="tablePagination.page <= 1"
            @click="page = tablePagination.page - 1"
          >
            Prev
          </button>
          <button
            class="rounded-md border px-3 py-1.5 disabled:opacity-50"
            :disabled="tablePagination.page >= tablePagination.totalPages"
            @click="page = tablePagination.page + 1"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <section class="rounded-lg border border-red-200 bg-red-50/50 p-4 shadow-card">
      <h3 class="text-sm font-semibold text-red-800">Danger Zone</h3>
      <p class="mt-1 text-sm text-red-700">
        Permanently delete audit logs older than a specified number of days. This action cannot be undone.
      </p>
      <div class="mt-3 flex flex-wrap items-end gap-3">
        <label class="flex flex-col gap-1 text-sm">
          <span class="font-medium text-red-800">Older than (days)</span>
          <input v-model.number="cleanupDays" type="number" min="1" max="3650" class="w-32 rounded-md border px-3 py-2" />
        </label>
        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
          @click="showCleanupDialog = true"
        >
          <Trash2 class="h-4 w-4" />
          Clear Logs
        </button>
      </div>
    </section>

    <ConfirmDialog
      :open="showCleanupDialog"
      title="Clear audit logs?"
      :description="`This will permanently delete all audit logs older than ${cleanupDays} days.`"
      confirm-label="Clear Logs"
      confirm-variant="destructive"
      require-type="CONFIRM"
      @confirm="cleanupMutation.mutate()"
      @cancel="showCleanupDialog = false"
    />
  </div>
</template>
