import api from './api'
import { createCrudService, unwrap } from './crud'
import type {
  PaginatedItems,
  QuotationDetail,
  QuotationDuplicateResult,
  QuotationExpirySummary,
  QuotationListItem,
  QuotationPayload,
  QuotationStats,
  QuotationStatusCounts,
  QuotationStatusMaster,
  QuotationSummary,
} from '@/types'

export interface QuotationListParams {
  search?: string
  status_id?: string
  customer_id?: string
  authorized_by_id?: string
  date_from?: string
  date_to?: string
  expiring_within?: number
  limit?: number
  sort?: 'created_at_desc'
  page?: number
}

export interface QuotationEmailResult {
  status_changed: boolean
}

export interface QuotationEmailPayload {
  to?: string
  cc?: string[]
  subject?: string
  body?: string
}

export const quotationsService = {
  ...createCrudService<QuotationDetail, QuotationPayload, QuotationPayload>('/quotations'),

  async list(params?: QuotationListParams): Promise<QuotationSummary[] | PaginatedItems<QuotationListItem>> {
    return unwrap((await api.get('/quotations', { params })).data)
  },

  async statusCounts(): Promise<QuotationStatusCounts> {
    return unwrap((await api.get('/quotations/status-counts')).data)
  },

  async stats(params?: {
    customer_id?: string
    authorized_by_id?: string
    status_id?: string
    search?: string
    date_from?: string
    date_to?: string
  }): Promise<QuotationStats> {
    return unwrap((await api.get('/quotations/stats', { params })).data)
  },

  async expiryAlerts(days = 7): Promise<QuotationListItem[]> {
    return unwrap((await api.get('/quotations/expiry-alerts', { params: { days } })).data)
  },

  async expirySummary(): Promise<QuotationExpirySummary> {
    return unwrap((await api.get('/quotations/expiry-summary')).data)
  },

  async getAllowedStatuses(id: string): Promise<QuotationStatusMaster[]> {
    return unwrap((await api.get(`/quotations/${id}/allowed-statuses`)).data)
  },

  async updateStatus(id: string, statusId: string, note?: string | null): Promise<QuotationDetail> {
    return unwrap((await api.patch(`/quotations/${id}/status`, { status_id: statusId, note })).data)
  },

  async duplicate(id: string): Promise<QuotationDuplicateResult> {
    return unwrap((await api.post(`/quotations/${id}/duplicate`)).data)
  },

  async downloadPdf(id: string, filename?: string | null): Promise<void> {
    const response = await api.get(`/quotations/${id}/pdf`, { responseType: 'blob' })
    const blob = new Blob([response.data], { type: 'application/pdf' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename ? `${filename}.pdf` : `quotation-${id}.pdf`
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  },

  async sendEmail(id: string, payload?: QuotationEmailPayload): Promise<QuotationEmailResult> {
    return unwrap((await api.post(`/quotations/${id}/email`, payload ?? {})).data)
  },
}
