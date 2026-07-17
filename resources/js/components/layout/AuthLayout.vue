<script setup lang="ts">

import BrandLogo from '@/components/common/BrandLogo.vue'



defineOptions({ name: 'AuthLayout' })

</script>



<template>

  <main class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-10">

    <section class="w-full max-w-[440px] rounded-lg border border-slate-200 bg-white p-8 shadow-sm">

      <header class="mb-8 border-b border-slate-100 pb-6 text-center">

        <div class="mx-auto flex justify-center">
          <BrandLogo size="lg" />
        </div>

        <p class="mt-3 text-sm text-slate-500">ERP Management System</p>

      </header>



      <slot />



      <footer class="mt-8 border-t border-slate-100 pt-5 text-center text-xs text-slate-400">

        Powered by Shree Axar ERP v1.0

      </footer>

    </section>

  </main>

</template>

