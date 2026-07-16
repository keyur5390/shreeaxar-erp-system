export type QuotationStatus = 'draft' | 'sent' | 'approved' | 'rejected' | 'expired' | 'cancelled'
export type ExpiryStatus = 'expired' | 'expiring_soon' | null
export type PermissionActions = { view: boolean; create: boolean; edit: boolean; delete: boolean }
export type PermissionMap = Record<string, PermissionActions>

export interface ApiResponse<T> { success: boolean; data: T; message?: string; errors?: Record<string, string[]> }
export interface PaginatedResponse<T> { data: T[]; current_page: number; last_page: number; per_page: number; total: number; from?: number | null; to?: number | null }
export interface SelectOption { label: string; value: string | number; disabled?: boolean }

export interface Permission { id: string; name: string; guard_name: string; created_at?: string; updated_at?: string }
export interface Role { id: string; name: string; guard_name: string; users_count?: number; permissions: Permission[]; created_at?: string; updated_at?: string }
export interface ModulePermissionMatrix { module: string; view: boolean; create: boolean; edit: boolean; delete: boolean }
export type PermissionAction = 'view' | 'create' | 'edit' | 'delete'
export interface Department { id: string; name: string; users_count?: number; created_at?: string; updated_at?: string }
export interface User { id: string; first_name: string; last_name: string; name?: string; email: string; contact_number?: string | null; phone?: string | null; profile_image?: string | null; profile_image_url?: string | null; department_id?: string | null; department?: Department | null; roles: Role[]; permissions: Permission[]; email_verified_at?: string | null; is_active: boolean; created_at: string; updated_at: string }
export interface UserListItem { id: string; first_name: string; last_name: string; email: string; contact_number?: string | null; profile_image?: string | null; profile_image_url?: string | null; is_active: boolean; department_id?: string | null; department_name?: string | null; role_id?: string | null; role_name?: string | null; created_at: string }
export interface UserAddress { id?: string; address_type_id: string; address_type_name?: string; address_line_1: string; address_line_2?: string | null; country_id: string; country_name?: string; state_id?: string | null; state_name?: string | null; city?: string | null; postal_code?: string | null }
export interface UserDetail { id: string; first_name: string; last_name: string; email: string; contact_number?: string | null; profile_image?: string | null; profile_image_url?: string | null; is_active: boolean; department_id?: string | null; department?: { id: string; name: string } | null; role?: { id: string; name: string } | null; roles: { id: string; name: string }[]; addresses?: UserAddress[]; created_at: string; updated_at: string }
export interface UserListParams { search?: string; role_id?: string; department_id?: string; is_active?: boolean | ''; page?: number; per_page?: number }
export interface UserFormAddress { address_type_id: string; address_line_1: string; address_line_2?: string; country_id: string; state_id: string; city?: string; postal_code?: string }
export interface Unit { id: string; code: string; name: string; is_default: boolean; products_count?: number; created_at?: string; updated_at?: string }
export interface AddressType { id: string; name: string; usage_count?: number; created_at?: string; updated_at?: string }
export interface Country { id: string; name: string; iso_code: string; states_count?: number; created_at?: string; updated_at?: string }
export interface State { id: string; country_id: string; name: string; addresses_count?: number; created_at?: string; updated_at?: string }
export interface PaginatedItems<T> { items: T[]; pagination: { current_page: number; from: number | null; last_page: number; per_page: number; to: number | null; total: number } }
export interface Address { id: number; address_type_id: number; address_line_1: string; address_line_2?: string | null; city: string; state_id: number; country_id: number; postal_code?: string | null; address_type?: AddressType; state?: State; country?: Country; created_at: string; updated_at: string }
export interface Tax { id: string; name: string; rate: number; is_default: boolean; is_fixed: boolean; created_at?: string; updated_at?: string }
export interface BankDetail { id: string; bank_name: string; account_holder_name: string; account_number: string; branch_name?: string | null; swift_code?: string | null; is_active: boolean; is_primary: boolean; created_at?: string; updated_at?: string }
export interface QuotationStatusMaster { id: string; name: string; color: string; is_system: boolean; is_default: boolean; sort_order: number; quotations_count?: number; created_at?: string; updated_at?: string }
export interface CompanyDetailRecord { id: string; name: string; logo?: string | null; logo_url?: string | null; email?: string | null; phone?: string | null; address?: string | null; tin_number?: string | null; vat_number?: string | null; website?: string | null; updated_at?: string }
export interface SettingRecord { key: string; value: string | null }
export interface CustomerListItem {
  id: string
  company_name: string
  email: string | null
  secondary_email?: string | null
  contact_number: string | null
  secondary_contact?: string | null
  tin_number: string | null
  is_active: boolean
  addresses_count: number
  quotations_count: number
  created_at: string
  updated_at: string
}
export interface CustomerAddress {
  id?: string
  address_type_id: string | null
  address_type_name?: string
  address_line_1: string
  address_line_2?: string | null
  country_id: string
  country_name?: string
  state_id?: string | null
  state_name?: string | null
  city?: string | null
  postal_code?: string | null
}
export interface CustomerDetail {
  id: string
  company_name: string
  email: string | null
  secondary_email?: string | null
  contact_number: string | null
  secondary_contact?: string | null
  tin_number: string | null
  is_active: boolean
  addresses?: CustomerAddress[]
  created_at: string
  updated_at: string
}
export interface CustomerFormAddress {
  address_type_id: string
  address_line_1: string
  address_line_2?: string
  country_id: string
  state_id: string
  city?: string
  postal_code?: string
}
export interface CustomerListParams { search?: string; is_active?: boolean | ''; page?: number; per_page?: number }
export interface CustomerSearchResult {
  id: string
  company_name: string
  tin_number: string | null
  email: string | null
  contact_number: string | null
  is_active: boolean
}
export interface CustomerStats {
  total_quotations: number
  total_value: number
  accepted_count: number
  last_quotation_date: string | null
  by_status: Array<{ status_name: string; count: number }>
}
export interface CustomerDeleteResult { soft_deleted?: boolean; deleted?: boolean; message?: string }
export interface QuotationSummary {
  id: string
  quotation_number: string
  quotation_date: string
  total_amount: number
  status?: { id: string; name: string; color: string } | null
  created_at: string
}
export interface QuotationListItem extends QuotationSummary {
  expiry_date: string
  expiry_status: ExpiryStatus
  days_until_expiry: number | null
  customer?: { company_name: string; email?: string | null; tin_number: string | null } | null
  authorized_by?: { first_name: string; last_name: string } | null
}
export interface QuotationItemForm {
  product_id?: string | null
  description: string
  image_url?: string | null
  unit: string
  rate: number
  quantity: number
  discount_rate: number
}
export interface QuotationItemDetail {
  id: string
  quotation_id: string
  product_id?: string | null
  sort_order: number
  description: string
  image_url?: string | null
  unit: string
  rate: number
  quantity: number
  discount_rate: number
  line_total: number
  product?: { title: string; primary_image?: string | null; primary_image_url?: string | null } | null
  created_at: string
  updated_at: string
}
export interface QuotationStatusHistoryEntry {
  id: string
  quotation_id: string
  from_status_id?: string | null
  to_status_id: string
  changed_by_id: string
  note?: string | null
  from_status?: { id: string; name: string; color: string } | null
  to_status?: { id: string; name: string; color: string } | null
  changed_by?: { id: string; first_name: string; last_name: string } | null
  created_at: string
}
export interface QuotationDetail {
  id: string
  quotation_number: string
  customer_id: string
  status_id: string
  quotation_date: string
  expiry_date: string
  authorized_by_id: string
  bank_detail_id?: string | null
  bank_snapshot?: Record<string, string | null> | null
  terms_conditions?: string | null
  notes?: string | null
  sub_total: number
  vat_amount: number
  discount_amount: number
  total_amount: number
  vat_rate: number
  revision_number: number
  last_modified_at?: string | null
  expiry_status: ExpiryStatus
  days_until_expiry: number | null
  customer?: {
    id: string
    company_name: string
    email?: string | null
    contact_number?: string | null
    tin_number?: string | null
    is_active: boolean
  } | null
  status?: { id: string; name: string; color: string } | null
  authorized_by?: { id: string; first_name: string; last_name: string; email?: string } | null
  bank_detail?: {
    id: string
    bank_name: string
    account_number: string
    account_holder_name: string
    branch_name?: string | null
    swift_code?: string | null
  } | null
  items: QuotationItemDetail[]
  status_history: QuotationStatusHistoryEntry[]
  created_at: string
  updated_at: string
}
export interface QuotationPayload {
  customer_id: string
  status_id: string
  quotation_date: string
  expiry_date: string
  authorized_by_id: string
  bank_detail_id?: string | null
  terms_conditions?: string | null
  notes?: string | null
  items: QuotationItemForm[]
  last_modified_at?: string | null
}
export interface QuotationStats {
  total_quotations: number
  total_value: number
  accepted_count: number
  pending_count: number
  last_quotation_date: string | null
  by_status: Array<{ status_name: string; count: number }>
}
export interface QuotationExpirySummary {
  expiring_today: number
  expiring_soon_7d: number
  expired: number
}
export interface QuotationStatusCounts {
  all: number
  requested: number
  drafted: number
  sent: number
  awaiting_response: number
  under_negotiation: number
  approved: number
  accepted: number
  rejected: number
}
export interface QuotationDuplicateResult {
  quotation: QuotationDetail
  warnings: string[]
}
/** @deprecated Use CustomerDetail or CustomerListItem */
export interface Customer { id: string; company_name: string; contact_name?: string; email: string | null; phone?: string | null; contact_number?: string | null; gst_number?: string | null; tin_number?: string | null; is_active: boolean; addresses: CustomerAddress[]; created_at: string; updated_at: string }
export interface ProductImage {
  id: string
  product_id: string
  image_url: string
  sort_order: number
  created_at: string
  updated_at: string
}
export interface ProductListItem {
  id: string
  title: string
  model_number: string | null
  rate: number
  unit: { code: string; name: string } | null
  primary_image_url: string | null
  images_count: number
  is_active: boolean
  created_at: string
  updated_at: string
}
export interface ProductUsageStats {
  quotations_count: number
  quotation_items_count: number
  total_qty_sold: number
  last_used_date: string | null
}
export interface ProductRecentQuotationItem {
  id: string
  quotation_id: string
  quotation_number: string | null
  quotation_date: string | null
  customer_name: string | null
  quantity: number
  line_total: number
  status: { id: string; name: string; color: string } | null
  created_at: string
}
export interface ProductDetail {
  id: string
  title: string
  model_number: string | null
  description: string | null
  rate: number
  unit_id: string
  unit: { id: string; code: string; name: string } | null
  primary_image_url: string | null
  images: ProductImage[]
  is_active: boolean
  usage_stats?: ProductUsageStats
  recent_quotation_items?: ProductRecentQuotationItem[]
  created_at: string
  updated_at: string
}
export interface ProductListParams {
  search?: string
  unit_id?: string
  is_active?: boolean | ''
  page?: number
  grid?: boolean
}
export interface ProductSearchResult {
  id: string
  title: string
  model_number: string | null
  rate: number
  unit: { code: string; name: string } | null
  primary_image_url: string | null
  is_active: boolean
}
export interface ProductDeleteResult {
  soft_deleted?: boolean
  deleted?: boolean
  message?: string
}
export interface ProductDuplicateResult {
  product: ProductDetail
  warnings: string[]
}
export type ProductPayload = {
  title: string
  rate: number
  unit_id: string
  model_number?: string | null
  description?: string | null
  is_active?: boolean
}
/** @deprecated Use ProductDetail or ProductListItem */
export interface Product { id: string; title: string; model_number?: string | null; description?: string | null; unit_id: string; unit?: Unit; rate: number; primary_image_url?: string | null; images: ProductImage[]; is_active: boolean; created_at: string; updated_at: string }
export interface QuotationItem { id: number; quotation_id: number; product_id?: number | null; product?: Product | null; product_name: string; description?: string | null; material?: string | null; finish?: string | null; width_mm?: number | null; height_mm?: number | null; depth_mm?: number | null; quantity: number; unit_price: number; rate: number; discount_rate: number; discount_amount: number; tax_rate: number; line_total: number; image_path?: string | null; created_at: string; updated_at: string }
export interface QuotationStatusHistory { id: number; quotation_id: number; from_status?: QuotationStatus | null; to_status: QuotationStatus; changed_by: number; user?: User; remarks?: string | null; created_at: string; updated_at: string }
export interface Quotation { id: number; customer_id: number; customer?: Customer; quotation_number: string; status: QuotationStatus; issue_date: string; expiry_date: string; sub_total: number; subtotal: number; vat_rate: number; vat_amount: number; tax_total: number; discount_total: number; grand_total: number; total: number; notes?: string | null; items: QuotationItem[]; status_history: QuotationStatusHistory[]; expiry_status: ExpiryStatus; created_at: string; updated_at: string }
export interface OtpRecord { id: number; user_id?: number | null; email?: string | null; phone?: string | null; purpose: string; expires_at: string; verified_at?: string | null; created_at: string; updated_at: string }
export interface DashboardKPIs {
  total_users: number
  total_customers: number
  active_customers: number
  total_products: number
  total_quotations: number
  total_revenue: number
  new_this_month: number
  expiring_today: number
  expiring_soon_7d: number
}
export interface DashboardByStatus {
  id: string
  name: string
  color: string | null
  count: number
  total_value: number
  percentage: number
}
export interface DashboardTrendMonth {
  month: number
  month_name: string
  count: number
  total_value: number
}
export interface TopProduct {
  product_id: string
  title: string
  model_number: string | null
  primary_image_url: string | null
  total_qty: number
  total_value: number
}
export interface RecentActivityItem {
  id: string
  action: string
  module: string
  user_email: string | null
  record_id: string | null
  created_at: string
}
export interface DashboardData {
  kpis: DashboardKPIs
  by_status: DashboardByStatus[]
  trend: DashboardTrendMonth[]
  top_products: TopProduct[]
  recent_activity: RecentActivityItem[]
}
/** @deprecated Use DashboardByStatus */
export interface QuotationByStatus { status: QuotationStatus; count: number; total: number }
/** @deprecated Use DashboardTrendMonth */
export interface QuotationTrend { date: string; count: number; total: number }
export interface Settings { company: CompanyDetailRecord; default_tax_id?: number | null; quotation_prefix: string; quotation_validity_days: number; currency: 'RWF'; idle_timeout_minutes: number; low_stock_threshold?: number | null }
