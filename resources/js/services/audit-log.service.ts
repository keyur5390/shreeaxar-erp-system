import api from './api'
import { unwrap } from './crud'
import type { PaginatedItems } from '@/types'

export interface AuditLogItem {
  id: string
  user_id: string | null
  user_email: string | null
  action: string
  module: string
  record_id: string | null
  old_values: Record<string, unknown> | null
  new_values: Record<string, unknown> | null
  ip_address: string | null
  created_at: string
}

export interface AuditLogListParams {
  page?: number
  limit?: number
  module?: string
  action?: string
  user_id?: string
  date_from?: string
  date_to?: string
}

function buildParams(params?: AuditLogListParams): Record<string, string | number> {
  const query: Record<string, string | number> = {}
  if (params?.page) query.page = params.page
  if (params?.limit) query.limit = params.limit
  if (params?.module) query.module = params.module
  if (params?.action) query.action = params.action
  if (params?.user_id) query.user_id = params.user_id
  if (params?.date_from) query.date_from = params.date_from
  if (params?.date_to) query.date_to = params.date_to
  return query
}

export const auditLogService = {
  async list(params?: AuditLogListParams): Promise<PaginatedItems<AuditLogItem>> {
    return unwrap((await api.get('/audit-logs', { params: buildParams(params) })).data)
  },

  async exportCsv(params?: Omit<AuditLogListParams, 'page' | 'limit'>): Promise<Blob> {
    const response = await api.get('/audit-logs/export', {
      params: { ...buildParams(params), format: 'csv' },
      responseType: 'blob',
    })
    return response.data
  },

  async cleanup(olderThanDays: number): Promise<{ deleted_count: number }> {
    return unwrap((await api.delete('/audit-logs/cleanup', { data: { older_than_days: olderThanDays } })).data)
  },
}
