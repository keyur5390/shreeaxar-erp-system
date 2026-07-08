<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import {
  Building2, ChevronDown, Cog, FileText, LayoutDashboard, LogOut,
  Package, Settings, UserCircle, Users, X,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth.store'
import { useUiStore } from '@/stores/ui.store'
import api from '@/services/api'

defineOptions({ name: 'Sidebar' })

type NavItem = { label: string; to?: string; icon?: unknown; module?: string; exact?: boolean; badgeKey?: string; children?: NavItem[]; superAdminOnly?: boolean; button?: boolean }

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const uiStore = useUiStore()
const quotationCounts = ref<Record<string, number>>({})

const navItems: NavItem[] = [
  { label: 'Dashboard', to: '/dashboard', icon: LayoutDashboard, exact: true },
  {
    label: 'MASTERS', icon: Settings, module: 'masters', children: [
      { label: 'Roles & Permissions', to: '/masters/roles-permissions', module: 'roles' },
      { label: 'Departments', to: '/masters/departments', module: 'departments' },
      { label: 'Units', to: '/masters/units', module: 'units' },
      { label: 'Tax', to: '/masters/tax', module: 'tax' },
      { label: 'Address Types', to: '/masters/address-types', module: 'address-types' },
      { label: 'Countries & States', to: '/masters/countries-states', module: 'countries-states' },
      { label: 'Quotation Statuses', to: '/masters/quotation-statuses', module: 'quotation-statuses' },
      { label: 'Bank Details', to: '/masters/bank-details', module: 'bank-details' },
      { label: 'Company Detail', to: '/masters/company-detail', module: 'company-detail' },
    ],
  },
  { label: 'Users', to: '/users', icon: Users, module: 'users' },
  { label: 'Customers', to: '/customers', icon: Building2, module: 'customers' },
  { label: 'Products', to: '/products', icon: Package, module: 'products' },
  {
    label: 'QUOTATIONS', icon: FileText, module: 'quotations', children: [
      { label: 'All', to: '/quotations', module: 'quotations', exact: true, badgeKey: 'all' },
      { label: 'Requested for Quotation', to: '/quotations/requested', module: 'quotations', badgeKey: 'requested' },
      { label: 'Sent', to: '/quotations/sent', module: 'quotations', badgeKey: 'sent' },
      { label: 'Awaiting Response', to: '/quotations/awaiting-response', module: 'quotations', badgeKey: 'awaiting_response' },
      { label: 'Under Negotiation', to: '/quotations/under-negotiation', module: 'quotations', badgeKey: 'under_negotiation' },
      { label: 'Approved', to: '/quotations/approved', module: 'quotations', badgeKey: 'approved' },
      { label: 'Accepted', to: '/quotations/accepted', module: 'quotations', badgeKey: 'accepted' },
      { label: 'Rejected', to: '/quotations/rejected', module: 'quotations', badgeKey: 'rejected' },
      { label: 'Create Quotation', to: '/quotations/new', module: 'quotations', button: true },
    ],
  },
  { label: 'Settings', to: '/settings', icon: Cog, module: 'settings', superAdminOnly: true },
  { label: 'My Account', to: '/account', icon: UserCircle },
]

function isVisible(item: NavItem): boolean {
  if (item.superAdminOnly && !authStore.isSuperAdmin) return false
  if (item.children) return item.children.some(isVisible)
  return authStore.canView(item.module)
}

const visibleNavItems = computed(() => navItems.filter(isVisible).map((item) => ({ ...item, children: item.children?.filter(isVisible) })))
const isActive = (item: NavItem) => item.to ? (item.exact ? route.path === item.to : route.path.startsWith(item.to)) : Boolean(item.children?.some(isActive))
const closeMobile = () => uiStore.setSidebarOpen(false)
const logout = () => { authStore.logout(); router.push('/login') }

onMounted(async () => {
  try {
    const { data } = await api.get('/quotations/status-counts')
    quotationCounts.value = data?.data ?? data ?? {}
  } catch {
    quotationCounts.value = {}
  }
})
</script>

<template>
  <div v-if="uiStore.sidebarOpen" class="fixed inset-0 z-40 bg-slate-950/40 lg:hidden" @click="closeMobile" />
  <aside
    class="fixed inset-y-0 left-0 z-50 flex w-[260px] -translate-x-full flex-col bg-[#1F4E79] text-white shadow-2xl transition-transform duration-200 lg:translate-x-0"
    :class="{ 'translate-x-0': uiStore.sidebarOpen }"
  >
    <div class="flex h-16 items-center justify-between px-5">
      <RouterLink to="/dashboard" class="flex items-center gap-3 font-bold" @click="closeMobile">
        <span class="grid h-10 w-10 place-items-center rounded-xl bg-white/15">SA</span>
        <span>Shree Axar ERP</span>
      </RouterLink>
      <button class="lg:hidden" type="button" @click="closeMobile"><X class="h-5 w-5" /></button>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
      <template v-for="item in visibleNavItems" :key="item.label">
        <div v-if="item.children" class="space-y-1">
          <button class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold hover:bg-white/10" :class="{ 'bg-white/15': isActive(item) }" @click="uiStore.toggleGroup(item.label)">
            <component :is="item.icon" class="h-5 w-5" />
            <span class="flex-1 text-left">{{ item.label }}</span>
            <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': uiStore.isGroupExpanded(item.label) }" />
          </button>
          <div v-show="uiStore.isGroupExpanded(item.label)" class="ml-3 space-y-1 border-l border-white/15 pl-3">
            <RouterLink v-for="child in item.children" :key="child.label" :to="child.to!" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-white/85 hover:bg-white/10" :class="[{ 'bg-white/15 text-white': isActive(child) }, child.button ? 'mt-2 bg-emerald-500 text-white hover:bg-emerald-400' : '']" @click="closeMobile">
              <span>{{ child.label }}</span>
              <span v-if="child.badgeKey && quotationCounts[child.badgeKey] !== undefined" class="rounded-full bg-white/15 px-2 py-0.5 text-xs">{{ quotationCounts[child.badgeKey] }}</span>
            </RouterLink>
          </div>
        </div>
        <RouterLink v-else :to="item.to!" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-white/90 hover:bg-white/10" :class="{ 'bg-white/15 text-white': isActive(item) }" @click="closeMobile">
          <component :is="item.icon" class="h-5 w-5" />
          <span>{{ item.label }}</span>
        </RouterLink>
      </template>
    </nav>

    <div class="border-t border-white/15 p-4">
      <div class="mb-3 flex items-center gap-3">
        <div class="grid h-10 w-10 place-items-center rounded-full bg-white/20 text-sm font-bold">{{ authStore.initials }}</div>
        <div class="min-w-0"><p class="truncate text-sm font-semibold">{{ authStore.fullName }}</p><p class="truncate text-xs text-white/70">{{ authStore.roleName }}</p></div>
      </div>
      <button class="flex w-full items-center justify-center gap-2 rounded-xl bg-white/10 px-3 py-2 text-sm font-semibold hover:bg-white/15" type="button" @click="logout"><LogOut class="h-4 w-4" />Logout</button>
    </div>
  </aside>
</template>
