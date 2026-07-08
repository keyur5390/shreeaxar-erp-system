import api from './api'
import { createCrudService, unwrap } from './crud'
import type { Quotation, QuotationStatus } from '@/types'

export const quotationsService = {
  ...createCrudService<Quotation>('/quotations'),
  async updateStatus(id: number | string, status: QuotationStatus, remarks?: string | null): Promise<Quotation> {
    return unwrap((await api.patch(`/quotations/${id}/status`, { status, remarks })).data)
  },
}
