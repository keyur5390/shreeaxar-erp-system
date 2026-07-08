<script setup lang="ts">
defineProps<{ title: string; subtitle?: string; breadcrumb?: { label: string; href?: string }[] }>()
</script>
<template>
  <header class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <nav v-if="breadcrumb?.length" class="mb-2 flex flex-wrap items-center gap-2 text-sm text-slate-500">
        <template v-for="(item, index) in breadcrumb" :key="`${item.label}-${index}`">
          <a v-if="item.href" :href="item.href" class="hover:text-brand-blue">{{ item.label }}</a><span v-else>{{ item.label }}</span><span v-if="index < breadcrumb.length - 1">/</span>
        </template>
      </nav>
      <h1 class="text-2xl font-bold tracking-tight text-slate-950">{{ title }}</h1><p v-if="subtitle" class="mt-1 text-sm text-slate-500">{{ subtitle }}</p>
    </div>
    <div v-if="$slots.action"><slot name="action" /></div>
  </header>
</template>
