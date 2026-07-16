<script setup lang="ts">
import { computed, defineComponent, h, ref, type PropType } from 'vue'
import { RouterLink } from 'vue-router'
import { useQuery } from '@tanstack/vue-query'
import { useTimeAgo } from '@vueuse/core'
import { Bar, Doughnut } from 'vue-chartjs'
import {
  Chart as ChartJS,
  ArcElement,
  BarElement,
  CategoryScale,
  Legend,
  LinearScale,
  LineElement,
  PointElement,
  Tooltip,
  type ChartOptions,
  type Plugin,
} from 'chart.js'
import {
  AlertTriangle,
  Award,
  Package,
  Receipt,
  TrendingUp,
  Users,
} from 'lucide-vue-next'
import PageHeader from '@/components/ui/PageHeader.vue'
import { dashboardService } from '@/services/dashboard.service'
import { STALE_TIME } from '@/lib/queryTimes'
import { abbreviateCurrency, formatCurrency } from '@/utils/formatters'
import type { DashboardByStatus, DashboardTrendMonth, RecentActivityItem } from '@/types'

ChartJS.register(ArcElement, BarElement, CategoryScale, Legend, LinearScale, LineElement, PointElement, Tooltip)

const BRAND_BLUE = '#1F4E79'
const GRAY_LINE = '#94a3b8'
const currentYear = new Date().getFullYear()
const selectedYear = ref(currentYear)

const dashboardQuery = useQuery({
  queryKey: ['dashboard'],
  queryFn: () => dashboardService.getAll(),
  staleTime: STALE_TIME.dashboard,
  refetchInterval: 60_000,
  refetchIntervalInBackground: false,
})

const trendQuery = useQuery({
  queryKey: computed(() => ['dashboard', 'trend', selectedYear.value]),
  queryFn: () => dashboardService.getQuotationTrend(selectedYear.value),
  staleTime: STALE_TIME.dashboard,
  enabled: computed(() => selectedYear.value !== currentYear),
})

const kpis = computed(() => dashboardQuery.data.value?.kpis)
const byStatus = computed(() => dashboardQuery.data.value?.by_status ?? [])
const topProducts = computed(() => dashboardQuery.data.value?.top_products ?? [])
const recentActivity = computed(() => dashboardQuery.data.value?.recent_activity ?? [])

const trendData = computed<DashboardTrendMonth[]>(() => {
  if (selectedYear.value === currentYear) {
    return dashboardQuery.data.value?.trend ?? []
  }
  return trendQuery.data.value ?? []
})

const showExpiryBanner = computed(() => {
  const k = kpis.value
  return (k?.expiring_today ?? 0) > 0 || (k?.expiring_soon_7d ?? 0) > 0
})

const doughnutTotal = computed(() => byStatus.value.reduce((sum, row) => sum + row.count, 0))

const centerTextPlugin: Plugin<'doughnut'> = {
  id: 'centerText',
  beforeDraw(chart) {
    const { ctx, chartArea } = chart
    if (!chartArea) return

    const { top, bottom, left, right } = chartArea
    const centerX = (left + right) / 2
    const centerY = (top + bottom) / 2

    ctx.save()
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.font = 'bold 24px Inter, sans-serif'
    ctx.fillStyle = '#0f172a'
    ctx.fillText(String(doughnutTotal.value), centerX, centerY - 8)
    ctx.font = '12px Inter, sans-serif'
    ctx.fillStyle = '#64748b'
    ctx.fillText('Total', centerX, centerY + 14)
    ctx.restore()
  },
}

const doughnutChartData = computed(() => ({
  labels: byStatus.value.filter((row) => row.count > 0).map((row) => row.name),
  datasets: [{
    data: byStatus.value.filter((row) => row.count > 0).map((row) => row.count),
    backgroundColor: byStatus.value.filter((row) => row.count > 0).map((row) => row.color ?? '#6B7280'),
    borderWidth: 0,
  }],
}))

const doughnutOptions = computed<ChartOptions<'doughnut'>>(() => ({
  responsive: true,
  maintainAspectRatio: false,
  cutout: '65%',
  plugins: {
    legend: {
      position: 'bottom',
      labels: { boxWidth: 12, padding: 16, font: { size: 12 } },
    },
    tooltip: {
      callbacks: {
        label(context) {
          const row = byStatus.value.find((item) => item.name === context.label) as DashboardByStatus | undefined
          if (!row) return ''
          return `${row.name}: ${row.count} (${abbreviateCurrency(row.total_value)}, ${row.percentage}%)`
        },
      },
    },
  },
}))

const barChartData = computed(() => ({
  labels: trendData.value.map((row) => row.month_name),
  datasets: [
    {
      type: 'bar' as const,
      label: 'Quotations',
      data: trendData.value.map((row) => row.count),
      backgroundColor: BRAND_BLUE,
      borderRadius: 4,
      yAxisID: 'y',
    },
    {
      type: 'line' as const,
      label: 'Revenue',
      data: trendData.value.map((row) => row.total_value),
      borderColor: GRAY_LINE,
      backgroundColor: GRAY_LINE,
      pointRadius: 3,
      tension: 0.3,
      yAxisID: 'y1',
    },
  ],
}))

const barChartOptions: ChartOptions<'bar'> = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: { mode: 'index', intersect: false },
  plugins: {
    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 16 } },
    tooltip: {
      callbacks: {
        afterTitle(items) {
          const index = items[0]?.dataIndex ?? 0
          const row = trendData.value[index]
          if (!row) return []
          return [`Month: ${row.month_name}`, `Count: ${row.count}`, `Value: ${formatCurrency(row.total_value)}`]
        },
        label: () => '',
      },
    },
  },
  scales: {
    y: {
      type: 'linear',
      position: 'left',
      beginAtZero: true,
      ticks: { precision: 0 },
      title: { display: true, text: 'Count' },
    },
    y1: {
      type: 'linear',
      position: 'right',
      beginAtZero: true,
      grid: { drawOnChartArea: false },
      title: { display: true, text: 'Revenue' },
      ticks: {
        callback(value) {
          return abbreviateCurrency(Number(value))
        },
      },
    },
  },
}

const yearOptions = computed(() => {
  const years: number[] = []
  for (let year = currentYear; year >= 2020; year -= 1) {
    years.push(year)
  }
  return years
})

function medalClass(rank: number): string {
  if (rank === 1) return 'text-amber-500'
  if (rank === 2) return 'text-slate-400'
  if (rank === 3) return 'text-orange-600'
  return 'text-slate-300'
}

const ActivityItem = defineComponent({
  name: 'ActivityItem',
  props: {
    item: { type: Object as PropType<RecentActivityItem>, required: true },
  },
  setup(props) {
    const timeAgo = useTimeAgo(() => props.item.created_at)
    return () => h('div', { class: 'flex items-start gap-3 border-b border-slate-100 py-3 last:border-b-0' }, [
      h('div', { class: 'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold uppercase text-slate-600' },
        (props.item.user_email?.[0] ?? '?').toUpperCase()),
      h('div', { class: 'min-w-0 flex-1' }, [
        h('p', { class: 'text-sm text-slate-800' }, [
          h('span', { class: 'font-medium' }, props.item.action),
          ' in ',
          h('span', { class: 'font-medium' }, props.item.module),
        ]),
        h('p', { class: 'mt-0.5 truncate text-xs text-slate-500' }, props.item.user_email ?? 'System'),
      ]),
      h('span', { class: 'shrink-0 text-xs text-slate-400' }, timeAgo.value),
    ])
  },
})
</script>

<template>
  <section class="space-y-6">
    <PageHeader
      title="Dashboard"
      subtitle="Track quotation pipeline, revenue, and customer activity."
    />

    <div v-if="dashboardQuery.isLoading.value" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div v-for="n in 4" :key="n" class="h-28 animate-pulse rounded-xl bg-white shadow-sm" />
    </div>

    <div
      v-else-if="dashboardQuery.isError.value"
      class="rounded-xl border border-red-200 bg-red-50 p-5 text-sm text-red-700"
    >
      Unable to load dashboard data. Make sure the API server is running and you are logged in.
    </div>

    <template v-else>
      <div
        v-if="showExpiryBanner"
        class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
      >
        <AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
        <div>
          <p class="font-medium">Quotation expiry alerts</p>
          <p class="mt-1 text-amber-800">
            <router-link to="/quotations?expiring_within=1" class="font-semibold underline hover:no-underline">
              {{ kpis?.expiring_today ?? 0 }} expiring today
            </router-link>
            <span v-if="(kpis?.expiring_soon_7d ?? 0) > 0">
              ·
              <router-link to="/quotations?expiring_within=7" class="font-semibold underline hover:no-underline">
                {{ kpis?.expiring_soon_7d ?? 0 }} expiring within 7 days
              </router-link>
            </span>
          </p>
        </div>
      </div>

      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl bg-white p-5 shadow-sm">
          <div class="mb-3 flex items-center justify-between">
            <p class="text-sm text-slate-500">Total Quotations</p>
            <Receipt class="h-4 w-4 text-slate-400" />
          </div>
          <strong class="text-3xl text-slate-950">{{ kpis?.total_quotations ?? 0 }}</strong>
          <p class="mt-1 text-xs text-emerald-600">+{{ kpis?.new_this_month ?? 0 }} this month</p>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm">
          <div class="mb-3 flex items-center justify-between">
            <p class="text-sm text-slate-500">Total Revenue</p>
            <TrendingUp class="h-4 w-4 text-slate-400" />
          </div>
          <strong class="text-3xl text-slate-950">{{ abbreviateCurrency(kpis?.total_revenue ?? 0) }}</strong>
          <p class="mt-1 text-xs text-slate-400">Accepted &amp; approved</p>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm">
          <div class="mb-3 flex items-center justify-between">
            <p class="text-sm text-slate-500">Total Customers</p>
            <Users class="h-4 w-4 text-slate-400" />
          </div>
          <strong class="text-3xl text-slate-950">{{ kpis?.total_customers ?? 0 }}</strong>
          <p class="mt-1 text-xs text-slate-400">{{ kpis?.active_customers ?? 0 }} active</p>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm">
          <div class="mb-3 flex items-center justify-between">
            <p class="text-sm text-slate-500">Total Products</p>
            <Package class="h-4 w-4 text-slate-400" />
          </div>
          <strong class="text-3xl text-slate-950">{{ kpis?.total_products ?? 0 }}</strong>
          <p class="mt-1 text-xs text-slate-400">In catalog</p>
        </div>
      </div>

      <div class="flex flex-wrap gap-2">
        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
          Total Users: {{ kpis?.total_users ?? 0 }}
        </span>
        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
          Active Customers: {{ kpis?.active_customers ?? 0 }}
        </span>
        <router-link
          to="/quotations?expiring_within=1"
          class="inline-flex items-center rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-red-700 hover:bg-red-100"
        >
          Expiring Today: {{ kpis?.expiring_today ?? 0 }}
        </router-link>
        <router-link
          to="/quotations?expiring_within=7"
          class="inline-flex items-center rounded-full bg-orange-50 px-3 py-1 text-xs font-medium text-orange-700 hover:bg-orange-100"
        >
          Expiring Soon: {{ kpis?.expiring_soon_7d ?? 0 }}
        </router-link>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl bg-white p-5 shadow-sm">
          <h3 class="mb-4 text-lg font-semibold text-slate-800">Quotations by Status</h3>
          <div class="h-72">
            <Doughnut
              v-if="doughnutTotal > 0"
              :data="doughnutChartData"
              :options="doughnutOptions"
              :plugins="[centerTextPlugin]"
            />
            <p v-else class="grid h-full place-items-center text-sm text-slate-500">No quotation data yet.</p>
          </div>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm">
          <div class="mb-4 flex items-center justify-between gap-3">
            <h3 class="text-lg font-semibold text-slate-800">Quotation Trend</h3>
            <select
              v-model.number="selectedYear"
              class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-brand-blue focus:outline-none focus:ring-1 focus:ring-brand-blue"
            >
              <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
            </select>
          </div>
          <div class="h-72">
            <Bar
              v-if="trendData.some((row) => row.count > 0 || row.total_value > 0)"
              :data="barChartData"
              :options="barChartOptions"
            />
            <p v-else class="grid h-full place-items-center text-sm text-slate-500">No trend data for {{ selectedYear }}.</p>
          </div>
        </div>
      </div>

      <div class="grid gap-4 xl:grid-cols-3">
        <div class="rounded-xl bg-white p-5 shadow-sm xl:col-span-2">
          <h3 class="mb-4 text-lg font-semibold text-slate-800">Top Products</h3>
          <div v-if="topProducts.length === 0" class="py-8 text-center text-sm text-slate-500">
            No product sales data yet.
          </div>
          <div v-else class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-left text-sm">
              <thead>
                <tr class="border-b border-slate-100 text-xs uppercase tracking-wide text-slate-500">
                  <th class="pb-3 pr-3 font-medium">Rank</th>
                  <th class="pb-3 pr-3 font-medium">Product</th>
                  <th class="pb-3 pr-3 font-medium">Model</th>
                  <th class="pb-3 pr-3 font-medium text-right">Units Sold</th>
                  <th class="pb-3 font-medium text-right">Total Value</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(product, index) in topProducts"
                  :key="product.product_id"
                  class="border-b border-slate-50 last:border-b-0"
                >
                  <td class="py-3 pr-3">
                    <Award v-if="index < 3" class="h-5 w-5" :class="medalClass(index + 1)" />
                    <span v-else class="pl-1 text-slate-500">{{ index + 1 }}</span>
                  </td>
                  <td class="py-3 pr-3">
                    <div class="flex items-center gap-3">
                      <img
                        v-if="product.primary_image_url"
                        :src="product.primary_image_url"
                        :alt="product.title"
                        class="h-10 w-10 rounded-lg border border-slate-100 object-cover"
                      >
                      <div
                        v-else
                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-400"
                      >
                        <Package class="h-4 w-4" />
                      </div>
                      <RouterLink
                        :to="`/products/${product.product_id}`"
                        class="font-medium text-brand-blue hover:underline"
                      >
                        {{ product.title }}
                      </RouterLink>
                    </div>
                  </td>
                  <td class="py-3 pr-3 text-slate-600">{{ product.model_number || '—' }}</td>
                  <td class="py-3 pr-3 text-right font-medium">{{ product.total_qty }}</td>
                  <td class="py-3 text-right font-medium">{{ formatCurrency(product.total_value) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm">
          <h3 class="mb-2 text-lg font-semibold text-slate-800">Recent Activity</h3>
          <div v-if="recentActivity.length === 0" class="py-8 text-center text-sm text-slate-500">
            No recent activity.
          </div>
          <div v-else>
            <ActivityItem
              v-for="item in recentActivity"
              :key="item.id"
              :item="item"
            />
          </div>
        </div>
      </div>
    </template>
  </section>
</template>
