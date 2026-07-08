<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router'
import { useSessionStore } from '@/stores/session'
const session = useSessionStore()
</script>
<template>
  <div class="min-h-screen">
    <aside class="fixed inset-y-0 left-0 w-64 border-r bg-white p-6">
      <h1 class="text-2xl font-bold text-brand">{{ session.appName }}</h1>
      <p class="mt-2 text-sm text-slate-500">Furniture quotation management</p>
      <nav class="mt-8 grid gap-2">
        <RouterLink class="rounded-lg px-3 py-2 hover:bg-slate-100" to="/">Dashboard</RouterLink>
        <RouterLink class="rounded-lg px-3 py-2 hover:bg-slate-100" to="/quotations">Quotations</RouterLink>
      </nav>
    </aside>
    <main class="ml-64 p-8"><RouterView /></main>
  </div>
</template>
