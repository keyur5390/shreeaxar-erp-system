<script setup lang="ts">
import { computed } from 'vue'
import { Bell, ChevronDown, Menu, Search } from 'lucide-vue-next'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth.store'
import { useUiStore } from '@/stores/ui.store'

defineOptions({ name: 'TopBar' })

const route = useRoute()
const authStore = useAuthStore()
const uiStore = useUiStore()
const title = computed(() => String(route.meta.breadcrumb || route.name || 'Dashboard'))
</script>

<template>
  <header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-slate-200 bg-white px-4 lg:px-6">
    <button class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" type="button" aria-label="Open sidebar" @click="uiStore.setSidebarOpen(true)">
      <Menu class="h-5 w-5" />
    </button>

    <div class="min-w-0 flex-1 lg:flex-none lg:w-64">
      <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Shree Axar ERP</p>
      <h1 class="truncate text-lg font-bold text-slate-950">{{ title }}</h1>
    </div>

    <div class="hidden flex-1 justify-center md:flex">
      <label class="flex w-full max-w-xl items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500 focus-within:border-[#1F4E79] focus-within:bg-white">
        <Search class="h-4 w-4" />
        <input class="flex-1 bg-transparent outline-none" type="search" placeholder="Search ERP records..." />
        <kbd class="rounded border border-slate-200 bg-white px-2 py-0.5 text-[11px] font-semibold text-slate-400">Ctrl K</kbd>
      </label>
    </div>

    <div class="ml-auto flex items-center gap-3">
      <button class="relative rounded-xl p-2 text-slate-600 hover:bg-slate-100" type="button" aria-label="Notifications">
        <Bell class="h-5 w-5" />
        <span class="absolute right-2 top-2 h-2 w-2 rounded-full bg-rose-500" />
      </button>
      <button class="flex items-center gap-2 rounded-xl border border-slate-200 px-2 py-1.5 hover:bg-slate-50" type="button">
        <span class="grid h-8 w-8 place-items-center rounded-full bg-[#1F4E79] text-xs font-bold text-white">{{ authStore.initials }}</span>
        <span class="hidden text-left sm:block"><span class="block text-sm font-semibold text-slate-800">{{ authStore.fullName }}</span><span class="block text-xs text-slate-500">{{ authStore.roleName }}</span></span>
        <ChevronDown class="hidden h-4 w-4 text-slate-400 sm:block" />
      </button>
    </div>
  </header>
</template>
