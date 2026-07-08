<script setup lang="ts">
import { useForm } from 'vee-validate'
import * as yup from 'yup'
import draggable from 'vuedraggable'
import { ref } from 'vue'
const schema = yup.object({ customer: yup.string().required(), item: yup.string().required(), amount: yup.number().positive().required() })
const { defineField, errors } = useForm({ validationSchema: schema })
const [customer] = defineField('customer'); const [item] = defineField('item'); const [amount] = defineField('amount')
const sections = ref([{ id: 1, name: 'Wardrobe' }, { id: 2, name: 'Kitchen cabinet' }, { id: 3, name: 'TV unit' }])
</script>
<template>
  <section class="grid gap-6 lg:grid-cols-[1fr_360px]">
    <div class="rounded-xl bg-white p-6 shadow-sm"><h2 class="text-3xl font-semibold">Quotation Builder</h2><p class="mb-6 text-slate-500">Create sanitized, PDF-ready furniture quotations with draggable line groups.</p><draggable v-model="sections" item-key="id" class="grid gap-3"><template #item="{ element }"><div class="cursor-move rounded-lg border p-4">{{ element.name }}</div></template></draggable></div>
    <form class="rounded-xl bg-white p-6 shadow-sm"><h3 class="text-xl font-semibold">Quick Add</h3><label class="mt-4 block text-sm">Customer<input v-model="customer" class="mt-1 w-full rounded border p-2" /></label><p class="text-sm text-red-600">{{ errors.customer }}</p><label class="mt-4 block text-sm">Item<input v-model="item" class="mt-1 w-full rounded border p-2" /></label><p class="text-sm text-red-600">{{ errors.item }}</p><label class="mt-4 block text-sm">Amount<input v-model="amount" type="number" class="mt-1 w-full rounded border p-2" /></label><p class="text-sm text-red-600">{{ errors.amount }}</p><button class="mt-6 rounded bg-brand px-4 py-2 text-white" type="button">Save Draft</button></form>
  </section>
</template>
