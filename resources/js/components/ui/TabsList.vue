<script setup lang="ts">
import { cn } from '@/lib/utils'
</script>

<template>
  <div
    :class="cn(
      'inline-flex h-10 items-center justify-center rounded-md bg-slate-100 p-1 text-slate-500',
      $attrs.class as string | undefined,
    )"
  >
    <slot />
  </div>
</template>
