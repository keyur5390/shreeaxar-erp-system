<script setup lang="ts">
defineOptions({ name: 'BrandLogo' })

withDefaults(defineProps<{
  size?: 'sm' | 'md' | 'lg' | 'xl' | 'sidebar'
  showTagline?: boolean
  tone?: 'default' | 'on-dark'
}>(), {
  size: 'md',
  showTagline: false,
  tone: 'default',
})

const sizeClasses: Record<string, string> = {
  sm: 'h-8 max-w-[120px]',
  md: 'h-11 max-w-[180px]',
  lg: 'h-14 max-w-[220px]',
  xl: 'h-16 max-w-[260px]',
  sidebar: 'h-[3.25rem] max-w-[210px] lg:max-w-[220px]',
}

const toneClasses: Record<string, string> = {
  default: '',
  'on-dark': 'brightness-110 contrast-110',
}
</script>

<template>
  <div class="flex min-w-0 flex-col items-center">
    <img
      src="/images/shreeaxar-logo.png"
      alt="Shree Axar Ltd"
      width="300"
      height="153"
      loading="eager"
      decoding="async"
      :class="[sizeClasses[size], toneClasses[tone]]"
      class="w-auto object-contain"
    />
    <p v-if="showTagline" class="mt-2 text-xs font-medium text-slate-500">ERP Management System</p>
  </div>
</template>
