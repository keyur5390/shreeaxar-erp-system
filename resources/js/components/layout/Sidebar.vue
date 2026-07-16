<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { onClickOutside } from '@vueuse/core'
import {
  Building2, ChevronDown, Cog, FileText, LayoutDashboard, LogOut,
  Package, PanelLeftClose, PanelLeftOpen, Settings, UserCircle, Users, X,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth.store'
import { useUiStore } from '@/stores/ui.store'
import { useQuotationSidebarCounts } from '@/composables/useQuotationSidebarCounts'

defineOptions({ name: 'Sidebar' })

type NavItem = {
  label: string
  to?: string
  icon?: unknown
  module?: string
  exact?: boolean
  badgeKey?: string
  badgeClass?: string
  children?: NavItem[]
  superAdminOnly?: boolean
  button?: boolean
}

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const uiStore = useUiStore()
const { counts: quotationCounts } = useQuotationSidebarCounts()

const flyoutGroup = ref<string | null>(null)
const flyoutTop = ref(0)
const sidebarRef = ref<HTMLElement | null>(null)
const flyoutPanelRef = ref<HTMLElement | null>(null)

const navItems: NavItem[] = [
  { label: 'Dashboard', to: '/dashboard', icon: LayoutDashboard, exact: true },
  {
    label: 'MASTERS', icon: Settings, module: 'masters', children: [
      { label: 'Roles & Permissions', to: '/masters/roles', module: 'roles' },
      { label: 'Departments', to: '/masters/departments', module: 'departments' },
      { label: 'Units', to: '/masters/units', module: 'units' },
      { label: 'Tax', to: '/masters/taxes', module: 'taxes' },
      { label: 'Address Types', to: '/masters/address-types', module: 'address_types' },
      { label: 'Countries & States', to: '/masters/countries-states', module: 'countries' },
      { label: 'Quotation Statuses', to: '/masters/quotation-statuses', module: 'quotation_statuses' },
      { label: 'Bank Details', to: '/masters/bank-details', module: 'bank_details' },
      { label: 'Company Detail', to: '/masters/company-detail', module: 'company_detail' },
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
      { label: 'Awaiting Response', to: '/quotations/awaiting', module: 'quotations', badgeKey: 'awaiting', badgeClass: 'bg-amber-400/90 text-amber-950' },
      { label: 'Under Negotiation', to: '/quotations/negotiation', module: 'quotations', badgeKey: 'negotiation' },
      { label: 'Approved', to: '/quotations/approved', module: 'quotations', badgeKey: 'approved' },
      { label: 'Accepted', to: '/quotations/accepted', module: 'quotations', badgeKey: 'accepted' },
      { label: 'Rejected', to: '/quotations/rejected', module: 'quotations', badgeKey: 'rejected' },
      { label: 'Create Quotation', to: '/quotations/new', module: 'quotations', button: true },
    ],
  },
  { label: 'Settings', to: '/settings/audit-log', icon: Cog, superAdminOnly: true },
  { label: 'My Account', to: '/account', icon: UserCircle },
]

function isVisible(item: NavItem): boolean {
  if (item.superAdminOnly && !authStore.isSuperAdmin) return false
  if (item.children) return item.children.some(isVisible)
  return authStore.canView(item.module)
}

const visibleNavItems = computed(() =>
  navItems.filter(isVisible).map((item) => ({ ...item, children: item.children?.filter(isVisible) })),
)

const activeFlyoutItem = computed(() =>
  visibleNavItems.value.find((item) => item.label === flyoutGroup.value) ?? null,
)

const groupPathPrefixes: Record<string, string> = {
  masters: '/masters',
  quotations: '/quotations',
}

function isChildRouteActive(item: NavItem): boolean {
  if (!item.to) return false
  if (item.exact) {
    if (route.path === item.to) return true
    if (item.to === '/quotations' && /^\/quotations\/\d+(\/edit)?$/.test(route.path)) return true
    return false
  }
  return route.path.startsWith(item.to)
}

function isActive(item: NavItem): boolean {
  if (item.children) {
    if (item.children.some(isChildRouteActive)) return true
    const prefix = item.module ? groupPathPrefixes[item.module] : undefined
    return Boolean(prefix && route.path.startsWith(prefix))
  }
  return isChildRouteActive(item)
}

function syncExpandedGroups() {
  const activeGroup = visibleNavItems.value.find((item) => item.children && isActive(item))
  for (const item of visibleNavItems.value) {
    if (item.children) {
      uiStore.setGroupExpanded(item.label, item.label === activeGroup?.label)
    }
  }
}

watch(() => route.path, () => {
  syncExpandedGroups()
  flyoutGroup.value = null
}, { immediate: true })

watch(() => uiStore.sidebarCollapsed, (collapsed) => {
  if (!collapsed) flyoutGroup.value = null
})

onClickOutside(sidebarRef, (event) => {
  if (!uiStore.sidebarCollapsed || !flyoutGroup.value) return
  if (flyoutPanelRef.value?.contains(event.target as Node)) return
  flyoutGroup.value = null
})

function toggleGroup(item: NavItem) {
  const wasExpanded = uiStore.isGroupExpanded(item.label)
  for (const navItem of visibleNavItems.value) {
    if (navItem.children) uiStore.setGroupExpanded(navItem.label, false)
  }
  if (!wasExpanded) uiStore.setGroupExpanded(item.label, true)
}

function onGroupClick(item: NavItem, event: MouseEvent) {
  if (uiStore.sidebarCollapsed) {
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect()
    flyoutTop.value = rect.top
    flyoutGroup.value = flyoutGroup.value === item.label ? null : item.label
    return
  }
  toggleGroup(item)
}

function onNavClick() {
  closeMobile()
  flyoutGroup.value = null
}

const closeMobile = () => uiStore.setSidebarOpen(false)
const logout = () => { authStore.logout(); router.push('/login') }

const sidebarWidthClass = computed(() =>
  uiStore.sidebarCollapsed ? 'w-[260px] lg:w-[72px]' : 'w-[260px]',
)

const navItemClass = (active: boolean) => [
  active ? 'bg-white/15 text-white' : 'text-white/90 hover:bg-white/10',
  uiStore.sidebarCollapsed ? 'lg:justify-center lg:px-2' : '',
]
</script>

<template>
  <div v-if="uiStore.sidebarOpen" class="fixed inset-0 z-40 bg-slate-950/40 lg:hidden" @click="closeMobile" />
  <aside
    ref="sidebarRef"
    class="fixed inset-y-0 left-0 z-50 flex -translate-x-full flex-col bg-[#1F4E79] text-white shadow-2xl transition-all duration-200 lg:translate-x-0"
    :class="[sidebarWidthClass, { 'translate-x-0': uiStore.sidebarOpen }]"
  >
    <div
      class="flex h-16 shrink-0 items-center justify-between px-5"
      :class="{ 'lg:justify-center lg:px-2': uiStore.sidebarCollapsed }"
    >
      <RouterLink
        to="/dashboard"
        class="flex min-w-0 items-center gap-3 font-bold"
        :class="{ 'lg:justify-center': uiStore.sidebarCollapsed }"
        @click="closeMobile"
      >
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white/15">SA</span>
        <span class="truncate" :class="{ 'lg:hidden': uiStore.sidebarCollapsed }">Shree Axar ERP</span>
      </RouterLink>
      <button class="rounded-lg p-1.5 hover:bg-white/10 lg:hidden" type="button" aria-label="Close sidebar" @click="closeMobile">
        <X class="h-5 w-5" />
      </button>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" :class="{ 'lg:px-2': uiStore.sidebarCollapsed }">
      <template v-for="item in visibleNavItems" :key="item.label">
        <div v-if="item.children" class="relative">
          <button
            type="button"
            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold"
            :class="navItemClass(isActive(item))"
            :title="uiStore.sidebarCollapsed ? item.label : undefined"
            @click="onGroupClick(item, $event)"
          >
            <component :is="item.icon" class="h-5 w-5 shrink-0" />
            <span class="flex-1 truncate text-left" :class="{ 'lg:hidden': uiStore.sidebarCollapsed }">{{ item.label }}</span>
            <ChevronDown
              class="h-4 w-4 shrink-0 transition-transform"
              :class="[
                { 'rotate-180': uiStore.isGroupExpanded(item.label) },
                { 'lg:hidden': uiStore.sidebarCollapsed },
              ]"
            />
          </button>

          <div
            v-show="!uiStore.sidebarCollapsed && uiStore.isGroupExpanded(item.label)"
            class="ml-3 space-y-1 border-l border-white/15 pl-3"
          >
            <RouterLink
              v-for="child in item.children"
              :key="child.label"
              :to="child.to!"
              class="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-white/85 hover:bg-white/10"
              :class="[{ 'bg-white/15 text-white': isChildRouteActive(child) }, child.button ? 'mt-2 bg-emerald-500 text-white hover:bg-emerald-400' : '']"
              @click="onNavClick"
            >
              <span>{{ child.label }}</span>
              <span
                v-if="child.badgeKey && quotationCounts[child.badgeKey] !== undefined"
                class="rounded-full px-2 py-0.5 text-xs"
                :class="child.badgeClass ?? 'bg-white/15'"
              >
                {{ quotationCounts[child.badgeKey] }}
              </span>
            </RouterLink>
          </div>
        </div>

        <RouterLink
          v-else
          :to="item.to!"
          class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold"
          :class="navItemClass(isActive(item))"
          :title="uiStore.sidebarCollapsed ? item.label : undefined"
          @click="onNavClick"
        >
          <component :is="item.icon" class="h-5 w-5 shrink-0" />
          <span class="truncate" :class="{ 'lg:hidden': uiStore.sidebarCollapsed }">{{ item.label }}</span>
        </RouterLink>
      </template>
    </nav>

    <div class="shrink-0 border-t border-white/15 p-4" :class="{ 'lg:px-2 lg:py-3': uiStore.sidebarCollapsed }">
      <button
        type="button"
        class="mb-3 hidden w-full items-center justify-center gap-2 rounded-xl bg-white/10 px-3 py-2 text-sm font-semibold hover:bg-white/15 lg:flex"
        :aria-label="uiStore.sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
        @click="uiStore.toggleSidebarCollapse()"
      >
        <PanelLeftClose v-if="!uiStore.sidebarCollapsed" class="h-4 w-4 shrink-0" />
        <PanelLeftOpen v-else class="h-4 w-4 shrink-0" />
        <span :class="{ 'lg:hidden': uiStore.sidebarCollapsed }">
          {{ uiStore.sidebarCollapsed ? 'Expand menu' : 'Collapse menu' }}
        </span>
      </button>

      <div
        class="mb-3 flex items-center gap-3"
        :class="{ 'lg:justify-center': uiStore.sidebarCollapsed }"
      >
        <div class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-white/20 text-sm font-bold">
          {{ authStore.initials }}
        </div>
        <div class="min-w-0" :class="{ 'lg:hidden': uiStore.sidebarCollapsed }">
          <p class="truncate text-sm font-semibold">{{ authStore.fullName }}</p>
          <p class="truncate text-xs text-white/70">{{ authStore.roleName }}</p>
        </div>
      </div>

      <button
        class="flex w-full items-center justify-center gap-2 rounded-xl bg-white/10 px-3 py-2 text-sm font-semibold hover:bg-white/15"
        type="button"
        :title="uiStore.sidebarCollapsed ? 'Logout' : undefined"
        @click="logout"
      >
        <LogOut class="h-4 w-4 shrink-0" />
        <span :class="{ 'lg:hidden': uiStore.sidebarCollapsed }">Logout</span>
      </button>
    </div>
  </aside>

  <Teleport to="body">
    <div
      v-if="uiStore.sidebarCollapsed && activeFlyoutItem?.children?.length"
      ref="flyoutPanelRef"
      class="fixed z-[60] hidden min-w-[240px] rounded-xl border border-white/10 bg-[#1F4E79] p-2 text-white shadow-2xl lg:block"
      :style="{ top: `${flyoutTop}px`, left: '80px' }"
    >
      <p class="px-3 py-2 text-xs font-bold uppercase tracking-wide text-white/60">{{ activeFlyoutItem.label }}</p>
      <RouterLink
        v-for="child in activeFlyoutItem.children"
        :key="child.label"
        :to="child.to!"
        class="flex items-center justify-between rounded-lg px-3 py-2 text-sm text-white/85 hover:bg-white/10"
        :class="[{ 'bg-white/15 text-white': isChildRouteActive(child) }, child.button ? 'mt-2 bg-emerald-500 text-white hover:bg-emerald-400' : '']"
        @click="onNavClick"
      >
        <span>{{ child.label }}</span>
        <span
          v-if="child.badgeKey && quotationCounts[child.badgeKey] !== undefined"
          class="rounded-full px-2 py-0.5 text-xs"
          :class="child.badgeClass ?? 'bg-white/15'"
        >
          {{ quotationCounts[child.badgeKey] }}
        </span>
      </RouterLink>
    </div>
  </Teleport>
</template>
