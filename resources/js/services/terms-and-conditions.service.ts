import api from './api'
import { unwrap } from './crud'
import type { TermsAndCondition } from '@/types'

const endpoint = '/masters/terms-and-conditions'

export type TermsAndConditionPayload = {
  name: string
  content: string
  is_default?: boolean
}

export const termsAndConditionsService = {
  async list(): Promise<TermsAndCondition[]> {
    return unwrap((await api.get(endpoint)).data)
  },

  async create(payload: TermsAndConditionPayload): Promise<TermsAndCondition> {
    return unwrap((await api.post(endpoint, payload)).data)
  },

  async update(id: string, payload: TermsAndConditionPayload): Promise<TermsAndCondition> {
    return unwrap((await api.put(`${endpoint}/${id}`, payload)).data)
  },

  async setDefault(id: string): Promise<TermsAndCondition> {
    return unwrap((await api.patch(`${endpoint}/${id}/set-default`)).data)
  },

  async remove(id: string): Promise<{ message?: string }> {
    const response = await api.delete(`${endpoint}/${id}`)
    return { message: response.data?.message as string | undefined }
  },
}
