import { createRouter, createWebHistory } from 'vue-router'
import Dashboard from '@/pages/Dashboard.vue'
import Quotations from '@/pages/Quotations.vue'
export default createRouter({ history: createWebHistory(), routes: [{ path: '/', name: 'dashboard', component: Dashboard }, { path: '/quotations', name: 'quotations', component: Quotations }] })
