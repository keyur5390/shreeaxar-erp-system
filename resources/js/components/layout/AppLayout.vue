<script setup lang="ts">
import { computed } from 'vue'
import { RouterView } from 'vue-router'
import Sidebar from './Sidebar.vue'
import TopBar from './TopBar.vue'
import SessionTimeoutModal from '@/components/ui/SessionTimeoutModal.vue'
import ToastHost from '@/components/ui/ToastHost.vue'
import { useUiStore } from '@/stores/ui.store'

defineOptions({ name: 'AppLayout' })

const uiStore = useUiStore()

const mainOffsetClass = computed(() =>
  uiStore.sidebarCollapsed ? 'lg:pl-[72px]' : 'lg:pl-[260px]',
)
</script>

<template>
  <div class="min-h-screen bg-gray-50 lg:flex">
    <Sidebar />
    <div
      class="flex min-h-screen min-w-0 flex-1 flex-col transition-[padding] duration-200"
      :class="mainOffsetClass"
    >
      <TopBar />
      <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-4 sm:p-6">
        <RouterView />
      </main>
    </div>
    <SessionTimeoutModal />
    <ToastHost />
  </div>
</template>
