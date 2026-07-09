<script setup lang="ts">
import { computed } from 'vue'
import { usePermission } from '@/composables/usePermissions'
import type { PermissionActions } from '@/types'

const props = defineProps<{ module: string; action: keyof PermissionActions }>()
const permission = usePermission(props.module)
const allowed = computed(() => permission.value[props.action])
</script>

<template>
  <slot v-if="allowed" />
</template>
