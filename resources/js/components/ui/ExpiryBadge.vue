<script setup lang="ts">
import { computed } from 'vue'
import type { ExpiryStatus } from '@/types'
import StatusBadge from './StatusBadge.vue'

const props = defineProps<{
  expiryDate?: string | Date | null
  expiryStatus?: ExpiryStatus
}>()

const status = computed(() => {
  if (props.expiryStatus === 'expired') return 'Expired'
  if (props.expiryStatus === 'expiring_soon') return 'Expiring soon'
  if (props.expiryStatus) return props.expiryStatus

  if (!props.expiryDate) return 'No expiry'

  const days = Math.ceil((new Date(props.expiryDate).getTime() - Date.now()) / 86400000)
  if (days < 0) return 'Expired'
  if (days <= 7) return 'Expiring soon'
  return 'Active'
})

const color = computed(() => {
  if (status.value === 'Expired') return '#dc2626'
  if (status.value === 'Expiring soon') return '#d97706'
  return '#16a34a'
})
</script>

<template>
  <StatusBadge :label="status" :color="color" size="sm" />
</template>
