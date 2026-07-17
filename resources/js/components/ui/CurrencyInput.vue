<script setup lang="ts">
import { computed } from 'vue'
import type { CurrencyLike } from '@/utils/currency'

const props = defineProps<{
  modelValue: number | null
  readOnly?: boolean
  currency?: CurrencyLike | null
}>()

const emit = defineEmits<{ 'update:modelValue': [value: number | null] }>()

const prefix = computed(() => props.currency?.symbol || props.currency?.code || 'RWF')
const decimals = computed(() => props.currency?.decimal_places ?? 0)

const display = computed(() => {
  if (props.modelValue === null || Number.isNaN(props.modelValue)) return ''
  return `${prefix.value} ${props.modelValue.toLocaleString('en-US', {
    minimumFractionDigits: decimals.value,
    maximumFractionDigits: decimals.value,
  })}`
})

function parse(value: string) {
  const normalized = value.replace(/[^0-9.-]/g, '')
  if (!normalized) {
    emit('update:modelValue', null)
    return
  }

  const parsed = decimals.value > 0 ? Number.parseFloat(normalized) : Number.parseInt(normalized, 10)
  emit('update:modelValue', Number.isNaN(parsed) ? null : parsed)
}
</script>

<template>
  <input
    :value="display"
    :readonly="readOnly"
    class="w-full rounded-md border px-3 py-2 focus:border-brand-blue focus:outline-none focus:ring-2 focus:ring-brand-accent read-only:bg-slate-100"
    inputmode="decimal"
    @input="parse(($event.target as HTMLInputElement).value)"
    @paste="parse(($event.clipboardData?.getData('text') ?? ''))"
  />
</template>
