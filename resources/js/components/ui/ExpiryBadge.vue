<script setup lang="ts">
import { computed } from 'vue'
import StatusBadge from './StatusBadge.vue'
const props = defineProps<{ expiryDate?: string | Date | null; expiryStatus?: string }>()
const status = computed(() => { if (props.expiryStatus) return props.expiryStatus; if (!props.expiryDate) return 'No expiry'; const days = Math.ceil((new Date(props.expiryDate).getTime() - Date.now()) / 86400000); return days < 0 ? 'Expired' : days <= 30 ? 'Expiring soon' : 'Active' })
const color = computed(() => status.value === 'Expired' ? '#dc2626' : status.value === 'Expiring soon' ? '#d97706' : '#16a34a')
</script>
<template><StatusBadge :label="status" :color="color" size="sm" /></template>
