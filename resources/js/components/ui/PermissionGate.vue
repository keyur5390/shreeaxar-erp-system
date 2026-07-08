<script setup lang="ts">
import { computed } from 'vue'
import { usePermissions } from '@/composables/usePermissions'
const props = defineProps<{ module: string; action: string }>()
const { can } = usePermissions()
const allowed = computed(() => can(`${props.module}.${props.action}`) || can(`${props.module}:${props.action}`))
</script>
<template><slot v-if="allowed" /></template>
