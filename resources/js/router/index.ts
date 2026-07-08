import { defineAsyncComponent } from 'vue'
import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth.store'
import AppLayout from '@/components/layout/AppLayout.vue'
import AsyncLoading from '@/components/common/AsyncLoading.vue'
import AsyncError from '@/components/common/AsyncError.vue'

const lazy = (loader: () => Promise<unknown>) => defineAsyncComponent({ loader: loader as never, loadingComponent: AsyncLoading, errorComponent: AsyncError, delay: 150, timeout: 30000 })
const Placeholder = lazy(() => import('@/pages/PlaceholderPage.vue'))
const titleProps = (title: string) => ({ title })

const protectedChildren: RouteRecordRaw[] = [
  { path: '', redirect: '/dashboard' },
  { path: 'dashboard', name: 'Dashboard', component: lazy(() => import('@/pages/Dashboard.vue')), meta: { requiresAuth: true, breadcrumb: 'Dashboard' } },
  { path: 'users', name: 'Users', component: Placeholder, props: titleProps('Users'), meta: { requiresAuth: true, module: 'users', breadcrumb: 'Users' } },
  { path: 'users/:pathMatch(.*)*', component: Placeholder, props: titleProps('Users'), meta: { requiresAuth: true, module: 'users', breadcrumb: 'Users' } },
  { path: 'customers/:pathMatch(.*)*', name: 'Customers', component: Placeholder, props: titleProps('Customers'), meta: { requiresAuth: true, module: 'customers', breadcrumb: 'Customers' } },
  { path: 'products/:pathMatch(.*)*', name: 'Products', component: Placeholder, props: titleProps('Products'), meta: { requiresAuth: true, module: 'products', breadcrumb: 'Products' } },
  { path: 'quotations', name: 'Quotations', component: lazy(() => import('@/pages/Quotations.vue')), meta: { requiresAuth: true, module: 'quotations', breadcrumb: 'Quotations' } },
  { path: 'quotations/:pathMatch(.*)*', component: Placeholder, props: titleProps('Quotations'), meta: { requiresAuth: true, module: 'quotations', breadcrumb: 'Quotations' } },
  { path: 'masters/:pathMatch(.*)*', name: 'Masters', component: Placeholder, props: titleProps('Masters'), meta: { requiresAuth: true, module: 'masters', breadcrumb: 'Masters' } },
  { path: 'settings', name: 'Settings', component: Placeholder, props: titleProps('Settings'), meta: { requiresAuth: true, module: 'settings', breadcrumb: 'Settings' } },
  { path: 'account', name: 'My Account', component: Placeholder, props: titleProps('My Account'), meta: { requiresAuth: true, breadcrumb: 'My Account' } },
]

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'Login', component: lazy(() => import('@/pages/Login.vue')), meta: { publicOnly: true, breadcrumb: 'Login' } },
    { path: '/forgot-password', name: 'Forgot Password', component: lazy(() => import('@/pages/ForgotPassword.vue')), meta: { publicOnly: true, breadcrumb: 'Forgot Password' } },
    { path: '/', component: AppLayout, children: protectedChildren, meta: { requiresAuth: true } },
    { path: '/:pathMatch(.*)*', name: 'NotFound', component: lazy(() => import('@/components/common/NotFound.vue')), meta: { breadcrumb: 'Not Found' } },
  ],
})

function isSafeRelativeRedirect(value: unknown): value is string {
  return typeof value === 'string' && value.startsWith('/') && !value.startsWith('//') && !/^https?:\/\//i.test(value)
}

router.beforeEach((to) => {
  const authStore = useAuthStore()
  const hasToken = Boolean(authStore.token)
  if (to.meta.requiresAuth && !hasToken) return { path: '/login', query: { redirect: isSafeRelativeRedirect(to.fullPath) ? to.fullPath : '/dashboard' } }
  if (to.meta.publicOnly && hasToken) return { path: '/dashboard' }
  if (to.meta.requiresAuth && !authStore.canView(to.meta.module as string | undefined)) return { path: '/dashboard' }
  return true
})

export default router
