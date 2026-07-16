import api from './api'
import { unwrap } from './crud'
import type {
  CustomerDeleteResult,
  CustomerDetail,
  CustomerListItem,
  CustomerListParams,
  CustomerSearchResult,
  CustomerStats,
} from '@/types'
import type { PaginatedItems } from '@/types'

export type CustomerPayload = {
  company_name: string
  email?: string | null
  secondary_email?: string | null
  contact_number?: string | null
  secondary_contact?: string | null
  tin_number?: string | null
  addresses?: Array<{
    address_type_id: string
    address_line_1: string
    address_line_2?: string | null
    country_id: string
    state_id?: string | null
    city?: string | null
    postal_code?: string | null
  }>
}

export const customersService = {
  async list(params?: CustomerListParams): Promise<PaginatedItems<CustomerListItem>> {
    const query: Record<string, string | number | boolean> = {}
    if (params?.search) query.search = params.search
    if (params?.is_active !== undefined && params?.is_active !== '') query.is_active = params.is_active
    if (params?.page) query.page = params.page
    if (params?.per_page) query.per_page = params.per_page
    return unwrap((await api.get('/customers', { params: query })).data)
  },

  async get(id: string): Promise<CustomerDetail> {
    return unwrap((await api.get(`/customers/${id}`)).data)
  },

  async create(payload: CustomerPayload): Promise<CustomerDetail> {
    return unwrap((await api.post('/customers', payload, {
      headers: { 'Content-Type': 'application/json' },
    })).data)
  },

  async update(id: string, payload: CustomerPayload): Promise<CustomerDetail> {
    return unwrap((await api.put(`/customers/${id}`, payload, {
      headers: { 'Content-Type': 'application/json' },
    })).data)
  },

  async remove(id: string): Promise<CustomerDeleteResult> {
    return unwrap((await api.delete(`/customers/${id}`)).data)
  },

  async checkEmail(email: string, excludeId?: string): Promise<{ available: boolean }> {
    return unwrap((await api.get('/customers/check-email', { params: { email, exclude_id: excludeId } })).data)
  },

  async checkTin(tin: string, excludeId?: string): Promise<{ available: boolean }> {
    return unwrap((await api.get('/customers/check-tin', { params: { tin, exclude_id: excludeId } })).data)
  },

  async toggleStatus(id: string): Promise<{ id: string; is_active: boolean }> {
    return unwrap((await api.patch(`/customers/${id}/toggle-status`)).data)
  },

  async stats(id: string): Promise<CustomerStats> {
    return unwrap((await api.get(`/customers/${id}/stats`)).data)
  },

  async search(q: string, limit = 10): Promise<CustomerSearchResult[]> {
    return unwrap((await api.get('/customers/search', { params: { q, limit } })).data)
  },

  async quickCreate(payload: {
    company_name: string
    email?: string | null
    contact_number?: string | null
  }): Promise<CustomerDetail> {
    return unwrap((await api.post('/customers/quick-create', payload)).data)
  },
}
