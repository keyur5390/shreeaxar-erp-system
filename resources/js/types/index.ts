export type QuotationStatus = 'draft' | 'sent' | 'approved' | 'rejected' | 'expired' | 'cancelled'
export type ExpiryStatus = 'expired' | 'expiring_soon' | null
export type PermissionActions = { view: boolean; create: boolean; edit: boolean; delete: boolean }
export type PermissionMap = Record<string, PermissionActions>

export interface ApiResponse<T> { success: boolean; data: T; message?: string; errors?: Record<string, string[]> }
export interface PaginatedResponse<T> { data: T[]; current_page: number; last_page: number; per_page: number; total: number; from?: number | null; to?: number | null }
export interface SelectOption { label: string; value: string | number; disabled?: boolean }

export interface Permission { id: number; name: string; guard_name: string; created_at: string; updated_at: string }
export interface Role { id: number; name: string; guard_name: string; permissions: Permission[]; created_at: string; updated_at: string }
export interface Department { id: number; name: string; code: string; description?: string | null; is_active: boolean; created_at: string; updated_at: string }
export interface User { id: number; first_name: string; last_name: string; name: string; email: string; phone?: string | null; department_id?: number | null; department?: Department | null; roles: Role[]; permissions: Permission[]; email_verified_at?: string | null; is_active: boolean; created_at: string; updated_at: string }
export interface Unit { id: number; name: string; symbol: string; is_active: boolean; created_at: string; updated_at: string }
export interface AddressType { id: number; name: string; code: string; created_at: string; updated_at: string }
export interface Country { id: number; name: string; iso2: string; iso3: string; phone_code?: string | null; created_at: string; updated_at: string }
export interface State { id: number; country_id: number; name: string; code?: string | null; country?: Country; created_at: string; updated_at: string }
export interface Address { id: number; address_type_id: number; address_line_1: string; address_line_2?: string | null; city: string; state_id: number; country_id: number; postal_code?: string | null; address_type?: AddressType; state?: State; country?: Country; created_at: string; updated_at: string }
export interface Tax { id: number; name: string; rate: number; is_default: boolean; is_active: boolean; created_at: string; updated_at: string }
export interface BankDetail { id: number; bank_name: string; account_name: string; account_number: string; branch_name?: string | null; swift_code?: string | null; is_default: boolean; created_at: string; updated_at: string }
export interface CompanyDetail { id: number; name: string; legal_name?: string | null; email: string; phone: string; tax_number?: string | null; logo_path?: string | null; address: Address; bank_details: BankDetail[]; created_at: string; updated_at: string }
export interface Customer { id: number; company_name: string; contact_name: string; email: string; phone: string; gst_number?: string | null; is_active: boolean; addresses: CustomerAddress[]; created_at: string; updated_at: string }
export interface CustomerAddress extends Address { customer_id: number; is_default: boolean }
export interface ProductImage { id: number; product_id: number; path: string; alt_text?: string | null; sort_order: number; is_primary: boolean; created_at: string; updated_at: string }
export interface Product { id: number; name: string; sku: string; description?: string | null; unit_id: number; unit?: Unit; rate: number; tax_id?: number | null; tax?: Tax | null; images: ProductImage[]; is_active: boolean; created_at: string; updated_at: string }
export interface QuotationItem { id: number; quotation_id: number; product_id?: number | null; product?: Product | null; product_name: string; description?: string | null; material?: string | null; finish?: string | null; width_mm?: number | null; height_mm?: number | null; depth_mm?: number | null; quantity: number; unit_price: number; rate: number; discount_rate: number; discount_amount: number; tax_rate: number; line_total: number; image_path?: string | null; created_at: string; updated_at: string }
export interface QuotationStatusHistory { id: number; quotation_id: number; from_status?: QuotationStatus | null; to_status: QuotationStatus; changed_by: number; user?: User; remarks?: string | null; created_at: string; updated_at: string }
export interface Quotation { id: number; customer_id: number; customer?: Customer; quotation_number: string; status: QuotationStatus; issue_date: string; expiry_date: string; sub_total: number; subtotal: number; vat_rate: number; vat_amount: number; tax_total: number; discount_total: number; grand_total: number; total: number; notes?: string | null; items: QuotationItem[]; status_history: QuotationStatusHistory[]; expiry_status: ExpiryStatus; created_at: string; updated_at: string }
export interface OtpRecord { id: number; user_id?: number | null; email?: string | null; phone?: string | null; purpose: string; expires_at: string; verified_at?: string | null; created_at: string; updated_at: string }
export interface DashboardKPIs { total_customers: number; total_products: number; total_quotations: number; pending_quotations: number; approved_quotations: number; expired_quotations: number; total_revenue: number; conversion_rate: number }
export interface QuotationByStatus { status: QuotationStatus; count: number; total: number }
export interface QuotationTrend { date: string; count: number; total: number }
export interface TopProduct { product_id: number; product_name: string; quantity: number; total: number }
export interface Settings { company: CompanyDetail; default_tax_id?: number | null; quotation_prefix: string; quotation_validity_days: number; currency: 'RWF'; idle_timeout_minutes: number; low_stock_threshold?: number | null }
