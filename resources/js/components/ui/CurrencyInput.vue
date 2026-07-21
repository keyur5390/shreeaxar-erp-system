<script setup lang="ts">
import { computed, ref } from 'vue'
import { formatAmount } from '@/utils/formatters'
import type { CurrencyLike } from '@/utils/currency'

const props = defineProps<{
  modelValue: number | null
  readOnly?: boolean
  currency?: CurrencyLike | null
}>()

const emit = defineEmits<{ 'update:modelValue': [value: number | null] }>()

const isFocused = ref(false)

const prefix = computed(() => props.currency?.symbol || props.currency?.code || 'RWF')
const currencyCode = computed(() => props.currency?.code ?? prefix.value)
const decimals = computed(() => props.currency?.decimal_places ?? 0)

const display = computed(() => {
  if (props.modelValue === null || Number.isNaN(props.modelValue)) return ''

  if (isFocused.value) {
    return decimals.value > 0
      ? String(props.modelValue)
      : String(Math.round(props.modelValue))
  }

  return formatAmount(props.modelValue, props.currency)
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

function onFocus() {
  isFocused.value = true
}

function onBlur() {
  isFocused.value = false
}
</script>

<template>
  <div
    class="flex w-full overflow-hidden rounded-md border bg-white transition focus-within:border-brand-blue focus-within:ring-2 focus-within:ring-brand-accent"
    :class="readOnly ? 'bg-slate-100' : ''"
  >
    <span
      class="flex shrink-0 items-center border-r border-slate-200 bg-slate-50 px-2.5 text-xs font-semibold text-slate-600"
      :title="currencyCode"
    >
      {{ prefix }}
    </span>
    <input
      :value="display"
      :readonly="readOnly"
      class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-right tabular-nums focus:outline-none read-only:cursor-default"
      inputmode="decimal"
      @focus="onFocus"
      @blur="onBlur"
      @input="parse(($event.target as HTMLInputElement).value)"
      @paste="parse(($event.clipboardData?.getData('text') ?? ''))"
    />
  </div>
</template>
