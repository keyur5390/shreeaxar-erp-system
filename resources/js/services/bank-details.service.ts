import api from './api'
import { unwrap } from './crud'
import type { BankDetail } from '@/types'

const endpoint = '/masters/bank-details'

export type BankDetailPayload = {
  bank_name: string
  account_number: string
  account_holder_name: string
  branch_name?: string | null
  swift_code?: string | null
  is_primary?: boolean
}

export const bankDetailsService = {
  async list(): Promise<BankDetail[]> {
    return unwrap((await api.get(endpoint)).data)
  },

  async create(payload: BankDetailPayload): Promise<BankDetail> {
    return unwrap((await api.post(endpoint, payload)).data)
  },

  async update(id: string, payload: BankDetailPayload): Promise<BankDetail> {
    return unwrap((await api.put(`${endpoint}/${id}`, payload)).data)
  },

  async setPrimary(id: string): Promise<BankDetail> {
    return unwrap((await api.patch(`${endpoint}/${id}/set-primary`)).data)
  },

  async remove(id: string): Promise<{ message?: string }> {
    const response = await api.delete(`${endpoint}/${id}`)
    return { message: response.data?.message as string | undefined }
  },
}
