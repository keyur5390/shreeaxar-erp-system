<script setup lang="ts">

import { computed, ref, watch } from 'vue'

import { useQuery } from '@tanstack/vue-query'

import { useRouter, useRoute, RouterLink } from 'vue-router'

import { Bell, ChevronDown, LogOut, Menu, PanelLeftClose, PanelLeftOpen, Search, UserCircle } from 'lucide-vue-next'

import Popover from '@/components/ui/Popover.vue'

import PopoverTrigger from '@/components/ui/PopoverTrigger.vue'

import PopoverContent from '@/components/ui/PopoverContent.vue'

import ExpiryBadge from '@/components/ui/ExpiryBadge.vue'

import GlobalSearch from '@/components/layout/GlobalSearch.vue'

import { useAuthStore } from '@/stores/auth.store'

import { useUiStore } from '@/stores/ui.store'

import { useNotifications } from '@/composables/useNotifications'
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts'
import BrandLogo from '@/components/common/BrandLogo.vue'

import { quotationsService } from '@/services/quotations.service'

import { formatDate } from '@/utils/formatters'

import type { QuotationListItem } from '@/types'



defineOptions({ name: 'TopBar' })



const route = useRoute()

const router = useRouter()

const authStore = useAuthStore()

const uiStore = useUiStore()

const { markAsRead, unreadCount, loadReadState } = useNotifications()



const notificationsOpen = ref(false)

const userMenuOpen = ref(false)

const mobileSearchOpen = ref(false)

const globalSearchRef = ref<InstanceType<typeof GlobalSearch> | null>(null)



const title = computed(() => String(route.meta.breadcrumb || route.name || 'Dashboard'))



const expiryAlertsQuery = useQuery({

  queryKey: ['quotations', 'expiry-alerts'],

  queryFn: () => quotationsService.expiryAlerts(7),

  refetchInterval: 5 * 60 * 1000,

})



const expirySummaryQuery = useQuery({

  queryKey: ['quotations', 'expiry-summary'],

  queryFn: () => quotationsService.expirySummary(),

  refetchInterval: 5 * 60 * 1000,

})



const expiryAlerts = computed(() => expiryAlertsQuery.data.value ?? [])



const expiringToday = computed(() =>

  expiryAlerts.value.filter((alert) => alert.days_until_expiry === 0),

)



const expiringSoon = computed(() =>

  expiryAlerts.value.filter((alert) => (alert.days_until_expiry ?? 0) > 0),

)



const notificationCount = computed(() => unreadCount(expirySummaryQuery.data.value))



watch(notificationCount, (count) => {

  uiStore.setNotificationCount(count)

}, { immediate: true })



watch(notificationsOpen, (open) => {

  if (open && expirySummaryQuery.data.value) {

    markAsRead(expirySummaryQuery.data.value)

  }

})



loadReadState()

useKeyboardShortcuts({
  'ctrl+k': () => {
    if (window.innerWidth < 1024) {
      openMobileSearch()
    } else {
      globalSearchRef.value?.openSearch()
    }
  },
})

function openMobileSearch(): void {

  mobileSearchOpen.value = true

}



function closeMobileSearch(): void {

  mobileSearchOpen.value = false

}



function logout(): void {

  userMenuOpen.value = false

  authStore.logout()

  router.push('/login')

}



function alertLink(alert: QuotationListItem): void {

  notificationsOpen.value = false

  router.push(`/quotations/${alert.id}`)

}

</script>



<template>

  <header class="sticky top-0 z-30 flex h-16 items-center gap-2 border-b border-slate-200 bg-white px-3 sm:gap-4 sm:px-4 lg:px-6">

    <button

      class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden"

      type="button"

      aria-label="Open sidebar"

      @click="uiStore.setSidebarOpen(true)"

    >

      <Menu class="h-5 w-5" />

    </button>



    <button

      class="hidden rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:inline-flex"

      type="button"

      :aria-label="uiStore.sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"

      @click="uiStore.toggleSidebarCollapse()"

    >

      <PanelLeftOpen v-if="uiStore.sidebarCollapsed" class="h-5 w-5" />

      <PanelLeftClose v-else class="h-5 w-5" />

    </button>



    <RouterLink to="/dashboard" class="flex shrink-0 items-center sm:hidden">
      <BrandLogo size="sm" />
    </RouterLink>

    <div class="min-w-0 flex-1 sm:hidden">
      <h1 class="truncate text-sm font-bold text-slate-950">{{ title }}</h1>
    </div>



    <div class="hidden min-w-0 flex-1 sm:block lg:w-64">

      <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Shree Axar ERP</p>

      <h1 class="truncate text-lg font-bold text-slate-950">{{ title }}</h1>

    </div>



    <div class="hidden flex-1 justify-center lg:flex">

      <GlobalSearch ref="globalSearchRef" />

    </div>



    <div class="ml-auto flex items-center gap-1 sm:gap-2">

      <button

        class="rounded-xl p-2 text-slate-600 hover:bg-slate-100 lg:hidden"

        type="button"

        aria-label="Search"

        @click="openMobileSearch"

      >

        <Search class="h-5 w-5" />

      </button>



      <Popover v-model:open="notificationsOpen" side="bottom">

        <PopoverTrigger>

          <span class="relative rounded-xl p-2 text-slate-600 hover:bg-slate-100">

            <Bell class="h-5 w-5" />

            <span

              v-if="notificationCount > 0"

              class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white"

            >

              {{ notificationCount > 9 ? '9+' : notificationCount }}

            </span>

          </span>

        </PopoverTrigger>

        <PopoverContent class="w-80 p-0 sm:w-96" align="end">

          <div class="border-b px-4 py-3">

            <h3 class="text-sm font-semibold text-slate-900">Notifications</h3>

            <p class="text-xs text-slate-500">Quotation expiry alerts</p>

          </div>

          <div v-if="expiryAlertsQuery.isLoading.value" class="px-4 py-6 text-center text-sm text-slate-500">

            Loading alerts…

          </div>

          <div v-else-if="!expiryAlerts.length" class="px-4 py-6 text-center text-sm text-slate-500">

            No upcoming expiry alerts.

          </div>

          <div v-else class="max-h-80 overflow-y-auto">

            <section v-if="expiringToday.length">

              <p class="bg-rose-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-rose-700">

                Expiring Today

              </p>

              <ul>

                <li v-for="alert in expiringToday" :key="alert.id" class="border-b last:border-b-0">

                  <button

                    type="button"

                    class="block w-full px-4 py-3 text-left hover:bg-slate-50"

                    @click="alertLink(alert)"

                  >

                    <div class="flex items-start justify-between gap-2">

                      <div class="min-w-0">

                        <p class="truncate text-sm font-medium text-slate-900">{{ alert.quotation_number }}</p>

                        <p class="truncate text-xs text-slate-500">{{ alert.customer?.company_name || 'Unknown customer' }}</p>

                      </div>

                      <ExpiryBadge :expiry-date="alert.expiry_date" :expiry-status="alert.expiry_status" />

                    </div>

                    <p class="mt-1 text-xs text-slate-500">Expires {{ formatDate(alert.expiry_date) }}</p>

                  </button>

                </li>

              </ul>

            </section>

            <section v-if="expiringSoon.length">

              <p class="bg-amber-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-amber-700">

                Expiring Soon

              </p>

              <ul>

                <li v-for="alert in expiringSoon" :key="alert.id" class="border-b last:border-b-0">

                  <button

                    type="button"

                    class="block w-full px-4 py-3 text-left hover:bg-slate-50"

                    @click="alertLink(alert)"

                  >

                    <div class="flex items-start justify-between gap-2">

                      <div class="min-w-0">

                        <p class="truncate text-sm font-medium text-slate-900">{{ alert.quotation_number }}</p>

                        <p class="truncate text-xs text-slate-500">{{ alert.customer?.company_name || 'Unknown customer' }}</p>

                      </div>

                      <ExpiryBadge :expiry-date="alert.expiry_date" :expiry-status="alert.expiry_status" />

                    </div>

                    <p class="mt-1 text-xs text-slate-500">Expires {{ formatDate(alert.expiry_date) }}</p>

                  </button>

                </li>

              </ul>

            </section>

          </div>

          <div class="border-t px-4 py-2">

            <router-link

              to="/quotations?expiring_within=7"

              class="text-xs font-medium text-brand-blue hover:underline"

              @click="notificationsOpen = false"

            >

              View All

            </router-link>

          </div>

        </PopoverContent>

      </Popover>



      <Popover v-model:open="userMenuOpen" side="bottom">

        <PopoverTrigger>

          <button class="flex items-center gap-2 rounded-xl border border-slate-200 px-2 py-1.5 hover:bg-slate-50" type="button">

            <span class="grid h-8 w-8 place-items-center rounded-full bg-brand-teal text-xs font-bold text-white">{{ authStore.initials }}</span>

            <span class="hidden text-left md:block">

              <span class="block max-w-[120px] truncate text-sm font-semibold text-slate-800">{{ authStore.fullName }}</span>

              <span class="block max-w-[120px] truncate text-xs text-slate-500">{{ authStore.roleName }}</span>

            </span>

            <ChevronDown class="hidden h-4 w-4 text-slate-400 md:block" />

          </button>

        </PopoverTrigger>

        <PopoverContent class="w-48 p-1" align="end">

          <router-link

            to="/account"

            class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"

            @click="userMenuOpen = false"

          >

            <UserCircle class="h-4 w-4" />

            My Account

          </router-link>

          <button

            type="button"

            class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-red-600 hover:bg-red-50"

            @click="logout"

          >

            <LogOut class="h-4 w-4" />

            Logout

          </button>

        </PopoverContent>

      </Popover>

    </div>

  </header>



  <Teleport to="body">

    <div v-if="mobileSearchOpen" class="fixed inset-0 z-50 bg-white lg:hidden">

      <GlobalSearch overlay @close="closeMobileSearch" />

    </div>

  </Teleport>

</template>

