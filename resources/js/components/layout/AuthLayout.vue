<script setup lang="ts">
defineOptions({ name: 'AuthLayout' })
</script>

<template>
  <main class="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-10">
    <section class="w-full max-w-[440px] rounded-2xl bg-white p-8 shadow-xl shadow-slate-200/70 ring-1 ring-slate-200">
      <header class="mb-8 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-[#1F4E79] text-white shadow-lg shadow-blue-900/20">
          <svg viewBox="0 0 48 48" class="h-8 w-8" fill="none" aria-hidden="true">
            <path d="M9 34V14l15-7 15 7v20L24 41 9 34Z" stroke="currentColor" stroke-width="3" stroke-linejoin="round" />
            <path d="M16 20h16M16 27h16M24 13v22" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
          </svg>
        </div>
        <h1 class="text-2xl font-bold text-slate-950">Shree Axar Furniture</h1>
        <p class="mt-1 text-sm font-medium text-slate-500">ERP Management System</p>
      </header>

      <slot />

      <footer class="mt-8 border-t border-slate-100 pt-5 text-center text-xs font-medium text-slate-400">
        Powered by Shree Axar ERP v1.0
      </footer>
    </section>
  </main>
</template>
