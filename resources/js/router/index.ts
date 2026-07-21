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
  { path: 'users', name: 'Users', component: lazy(() => import('@/views/users/UserListView.vue')), meta: { requiresAuth: true, module: 'users', breadcrumb: 'Users' } },
  { path: 'users/new', name: 'UserCreate', component: lazy(() => import('@/views/users/UserFormView.vue')), meta: { requiresAuth: true, module: 'users', breadcrumb: 'Add User' } },
  { path: 'users/:id/edit', name: 'UserEdit', component: lazy(() => import('@/views/users/UserFormView.vue')), meta: { requiresAuth: true, module: 'users', breadcrumb: 'Edit User' } },
  { path: 'users/:id', name: 'UserProfile', component: lazy(() => import('@/views/users/UserProfileView.vue')), meta: { requiresAuth: true, module: 'users', breadcrumb: 'User Profile' } },
  { path: 'customers', name: 'Customers', component: lazy(() => import('@/views/customers/CustomerListView.vue')), meta: { requiresAuth: true, module: 'customers', breadcrumb: 'Customers' } },
  { path: 'customers/new', name: 'CustomerCreate', component: lazy(() => import('@/views/customers/CustomerFormView.vue')), meta: { requiresAuth: true, module: 'customers', breadcrumb: 'Add Customer' } },
  { path: 'customers/:id/edit', name: 'CustomerEdit', component: lazy(() => import('@/views/customers/CustomerFormView.vue')), meta: { requiresAuth: true, module: 'customers', breadcrumb: 'Edit Customer' } },
  { path: 'customers/:id', name: 'CustomerDetail', component: lazy(() => import('@/views/customers/CustomerDetailView.vue')), meta: { requiresAuth: true, module: 'customers', breadcrumb: 'Customer Detail' } },
  { path: 'products', name: 'Products', component: lazy(() => import('@/views/products/ProductListView.vue')), meta: { requiresAuth: true, module: 'products', breadcrumb: 'Products' } },
  { path: 'products/new', name: 'ProductCreate', component: lazy(() => import('@/views/products/ProductFormView.vue')), meta: { requiresAuth: true, module: 'products', breadcrumb: 'Add Product' } },
  { path: 'products/:id/edit', name: 'ProductEdit', component: lazy(() => import('@/views/products/ProductFormView.vue')), meta: { requiresAuth: true, module: 'products', breadcrumb: 'Edit Product' } },
  { path: 'products/:id', name: 'ProductDetail', component: lazy(() => import('@/views/products/ProductDetailView.vue')), meta: { requiresAuth: true, module: 'products', breadcrumb: 'Product Detail' } },
  { path: 'quotations', name: 'Quotations', component: lazy(() => import('@/views/quotations/QuotationListView.vue')), meta: { requiresAuth: true, module: 'quotations', breadcrumb: 'Quotations' }, props: { statusFilter: undefined } },
  { path: 'quotations/new', name: 'QuotationCreate', component: lazy(() => import('@/views/quotations/QuotationFormView.vue')), meta: { requiresAuth: true, module: 'quotations', breadcrumb: 'Create Quotation' } },
  { path: 'quotations/requested', name: 'QuotationsRequested', component: lazy(() => import('@/views/quotations/QuotationListView.vue')), meta: { requiresAuth: true, module: 'quotations', breadcrumb: 'Requested' }, props: { statusFilter: 'Requested for Quotation' } },
  { path: 'quotations/sent', name: 'QuotationsSent', component: lazy(() => import('@/views/quotations/QuotationListView.vue')), meta: { requiresAuth: true, module: 'quotations', breadcrumb: 'Sent' }, props: { statusFilter: 'Sent' } },
  { path: 'quotations/awaiting', name: 'QuotationsAwaiting', component: lazy(() => import('@/views/quotations/QuotationListView.vue')), meta: { requiresAuth: true, module: 'quotations', breadcrumb: 'Awaiting Response' }, props: { statusFilter: 'Awaiting Customer Response' } },
  { path: 'quotations/negotiation', name: 'QuotationsNegotiation', component: lazy(() => import('@/views/quotations/QuotationListView.vue')), meta: { requiresAuth: true, module: 'quotations', breadcrumb: 'Under Negotiation' }, props: { statusFilter: 'Under Negotiation' } },
  { path: 'quotations/approved', name: 'QuotationsApproved', component: lazy(() => import('@/views/quotations/QuotationListView.vue')), meta: { requiresAuth: true, module: 'quotations', breadcrumb: 'Approved' }, props: { statusFilter: 'Approved' } },
  { path: 'quotations/accepted', name: 'QuotationsAccepted', component: lazy(() => import('@/views/quotations/QuotationListView.vue')), meta: { requiresAuth: true, module: 'quotations', breadcrumb: 'Accepted' }, props: { statusFilter: 'Accepted' } },
  { path: 'quotations/rejected', name: 'QuotationsRejected', component: lazy(() => import('@/views/quotations/QuotationListView.vue')), meta: { requiresAuth: true, module: 'quotations', breadcrumb: 'Rejected' }, props: { statusFilter: 'Rejected' } },
  { path: 'quotations/:id/edit', name: 'QuotationEdit', component: lazy(() => import('@/views/quotations/QuotationFormView.vue')), meta: { requiresAuth: true, module: 'quotations', breadcrumb: 'Edit Quotation' } },
  { path: 'quotations/:id', name: 'QuotationDetail', component: lazy(() => import('@/views/quotations/QuotationDetailView.vue')), meta: { requiresAuth: true, module: 'quotations', breadcrumb: 'Quotation Detail' } },
  { path: 'masters/roles/:id/permissions', name: 'RolePermissions', component: lazy(() => import('@/pages/masters/RolePermissions.vue')), meta: { requiresAuth: true, module: 'roles', breadcrumb: 'Permissions' } },
  { path: 'masters/roles', name: 'Roles', component: lazy(() => import('@/pages/masters/Roles.vue')), meta: { requiresAuth: true, module: 'roles', breadcrumb: 'Roles' } },
  { path: 'masters/departments', name: 'Departments', component: lazy(() => import('@/pages/masters/Departments.vue')), meta: { requiresAuth: true, module: 'departments', breadcrumb: 'Departments' } },
  { path: 'masters/units', name: 'Units', component: lazy(() => import('@/pages/masters/Units.vue')), meta: { requiresAuth: true, module: 'units', breadcrumb: 'Units' } },
  { path: 'masters/currencies', name: 'Currencies', component: lazy(() => import('@/pages/masters/Currencies.vue')), meta: { requiresAuth: true, module: 'currencies', breadcrumb: 'Currencies' } },
  { path: 'masters/taxes', name: 'Taxes', component: lazy(() => import('@/pages/masters/Taxes.vue')), meta: { requiresAuth: true, module: 'taxes', breadcrumb: 'Tax' } },
  { path: 'masters/address-types', name: 'AddressTypes', component: lazy(() => import('@/pages/masters/AddressTypes.vue')), meta: { requiresAuth: true, module: 'address_types', breadcrumb: 'Address Types' } },
  { path: 'masters/countries-states', name: 'CountriesStates', component: lazy(() => import('@/pages/masters/CountriesStates.vue')), meta: { requiresAuth: true, module: 'countries', breadcrumb: 'Countries & States' } },
  { path: 'masters/quotation-statuses', name: 'QuotationStatuses', component: lazy(() => import('@/pages/masters/QuotationStatuses.vue')), meta: { requiresAuth: true, module: 'quotation_statuses', breadcrumb: 'Quotation Statuses' } },
  { path: 'masters/bank-details', name: 'BankDetails', component: lazy(() => import('@/pages/masters/BankDetails.vue')), meta: { requiresAuth: true, module: 'bank_details', breadcrumb: 'Bank Details' } },
  { path: 'masters/terms-and-conditions', name: 'TermsAndConditions', component: lazy(() => import('@/pages/masters/TermsAndConditions.vue')), meta: { requiresAuth: true, module: 'terms_and_conditions', breadcrumb: 'Terms & Conditions' } },
  { path: 'masters/company-detail', name: 'CompanyDetail', component: lazy(() => import('@/pages/masters/CompanyDetail.vue')), meta: { requiresAuth: true, module: 'company_detail', breadcrumb: 'Company Detail' } },
  { path: 'masters/tax', redirect: '/masters/taxes' },
  { path: 'masters/:pathMatch(.*)*', name: 'Masters', component: Placeholder, props: titleProps('Masters'), meta: { requiresAuth: true, module: 'masters', breadcrumb: 'Masters' } },
  { path: 'settings', name: 'PortalSettings', component: lazy(() => import('@/pages/settings/PortalSettingsView.vue')), meta: { requiresAuth: true, superAdminOnly: true, breadcrumb: 'Portal Settings' } },
  { path: 'settings/audit-log', name: 'AuditLog', component: lazy(() => import('@/pages/settings/AuditLogView.vue')), meta: { requiresAuth: true, superAdminOnly: true, breadcrumb: 'Audit Log' } },
  { path: 'account', name: 'My Account', component: lazy(() => import('@/views/account/AccountSettingsView.vue')), meta: { requiresAuth: true, breadcrumb: 'My Account' } },
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
  if (to.meta.requiresAuth && to.meta.superAdminOnly && !authStore.isSuperAdmin) return { path: '/403' }
  if (to.meta.requiresAuth && module && !authStore.permissions[module]?.view && !authStore.isSuperAdmin) return { path: '/403' }
  return true
})

export default router
