<script setup lang="ts">
import { Bar } from 'vue-chartjs'
import { BarElement, CategoryScale, Chart as ChartJS, LinearScale, Tooltip } from 'chart.js'
ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip)
const chartData = { labels: ['Draft', 'Sent', 'Approved'], datasets: [{ label: 'Quotations', backgroundColor: '#7c3aed', data: [12, 8, 5] }] }
const chartOptions = { responsive: true, maintainAspectRatio: false }
</script>
<template>
  <section class="space-y-6">
    <div><h2 class="text-3xl font-semibold">Dashboard</h2><p class="text-slate-500">Track quotation pipeline, revenue, and customer activity.</p></div>
    <div class="grid gap-4 md:grid-cols-3"><div class="rounded-xl bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Open Quotations</p><strong class="text-3xl">25</strong></div><div class="rounded-xl bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Monthly Value</p><strong class="text-3xl">₹8.4L</strong></div><div class="rounded-xl bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">Customers</p><strong class="text-3xl">142</strong></div></div>
    <div class="h-80 rounded-xl bg-white p-5 shadow-sm"><Bar :data="chartData" :options="chartOptions" /></div>
  </section>
</template>
