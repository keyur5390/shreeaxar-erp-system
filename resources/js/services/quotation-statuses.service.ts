import api from './api'
import { unwrap } from './crud'
import type { QuotationStatusMaster } from '@/types'

const endpoint = '/masters/quotation-statuses'

export const quotationStatusesService = {
  async list(): Promise<QuotationStatusMaster[]> {
    return unwrap((await api.get(endpoint)).data)
  },

  async create(payload: { name: string; color: string }): Promise<QuotationStatusMaster> {
    return unwrap((await api.post(endpoint, payload)).data)
  },

  async update(id: string, payload: Partial<{ name: string; color: string; sort_order: number }>): Promise<QuotationStatusMaster> {
    return unwrap((await api.put(`${endpoint}/${id}`, payload)).data)
  },

  async reorder(items: Array<{ id: string; sort_order: number }>): Promise<void> {
    await api.put(`${endpoint}/reorder`, items)
  },

  async remove(id: string): Promise<void> {
    await api.delete(`${endpoint}/${id}`)
  },
}
