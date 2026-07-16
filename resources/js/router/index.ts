import { defineAsyncComponent } from 'vue'
import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth.store'
import { authService } from '@/services/auth.service'
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
  { path: 'masters/roles/:id/permissions', name: 'RolePermissions', component: lazy(() => import('@/pages/masters/RolePermissions.vue')), meta: { requiresAuth: true, module: 'roles', breadcrumb: 'Permissions' } },
  { path: 'masters/roles', name: 'Roles', component: lazy(() => import('@/pages/masters/Roles.vue')), meta: { requiresAuth: true, module: 'roles', breadcrumb: 'Roles' } },
  { path: 'masters/departments', name: 'Departments', component: lazy(() => import('@/pages/masters/Departments.vue')), meta: { requiresAuth: true, module: 'departments', breadcrumb: 'Departments' } },
  { path: 'masters/units', name: 'Units', component: lazy(() => import('@/pages/masters/Units.vue')), meta: { requiresAuth: true, module: 'units', breadcrumb: 'Units' } },
  { path: 'masters/taxes', name: 'Taxes', component: lazy(() => import('@/pages/masters/Taxes.vue')), meta: { requiresAuth: true, module: 'taxes', breadcrumb: 'Tax' } },
  { path: 'masters/address-types', name: 'AddressTypes', component: lazy(() => import('@/pages/masters/AddressTypes.vue')), meta: { requiresAuth: true, module: 'address_types', breadcrumb: 'Address Types' } },
  { path: 'masters/tax', redirect: '/masters/taxes' },
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
    { path: '/403', name: 'AccessDenied', component: lazy(() => import('@/components/common/AccessDenied.vue')), meta: { requiresAuth: true, breadcrumb: 'Access Denied' } },
    { path: '/:pathMatch(.*)*', name: 'NotFound', component: lazy(() => import('@/components/common/NotFound.vue')), meta: { breadcrumb: 'Not Found' } },
  ],
})

function isSafeRelativeRedirect(value: unknown): value is string {
  return typeof value === 'string' && value.startsWith('/') && !value.startsWith('//') && !/^https?:\/\//i.test(value)
}

let hasLoadedInitialAuth = false

router.beforeEach(async (to) => {
  const authStore = useAuthStore()
  const hasToken = Boolean(authStore.token)

  if (!hasLoadedInitialAuth && hasToken) {
    hasLoadedInitialAuth = true
    try {
      authStore.setLoading(true)
      const auth = await authService.me()
      authStore.setAuth({ user: auth.user, token: auth.token ?? authStore.token, expiresAt: auth.expires_at ?? auth.expiresAt ?? authStore.expiresAt })
      authStore.setPermissions(auth.permissions)
    } catch {
      authStore.clearAuth()
    } finally {
      authStore.setLoading(false)
    }
  } else if (!hasLoadedInitialAuth) {
    hasLoadedInitialAuth = true
  }

  const isAuthenticated = Boolean(authStore.token)
  if (to.meta.requiresAuth && !isAuthenticated) return { path: '/login', query: { redirect: isSafeRelativeRedirect(to.fullPath) ? to.fullPath : '/dashboard' } }
  if (to.meta.publicOnly && isAuthenticated) return { path: '/dashboard' }
  const module = to.meta.module as string | undefined
  if (to.meta.requiresAuth && module && !authStore.permissions[module]?.view && !authStore.isSuperAdmin) return { path: '/403' }
  return true
})

export default router
