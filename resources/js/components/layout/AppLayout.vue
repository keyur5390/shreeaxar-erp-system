<script setup lang="ts">
import Sidebar from './Sidebar.vue'
import TopBar from './TopBar.vue'
import SessionTimeoutModal from '@/components/ui/SessionTimeoutModal.vue'
import ToastHost from '@/components/ui/ToastHost.vue'

defineOptions({ name: 'AppLayout' })
</script>

<template>
  <div class="min-h-screen bg-gray-50 lg:flex">
    <Sidebar />
    <div class="flex min-h-screen flex-1 flex-col lg:pl-[260px]">
      <TopBar />
      <main class="flex-1 overflow-y-auto bg-gray-50 p-6">
        <slot />
      </main>
    </div>
    <SessionTimeoutModal />
    <ToastHost />
  </div>
</template>
