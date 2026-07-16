import api from './api'
import { unwrap } from './crud'
import type { CompanyDetailRecord } from '@/types'

const endpoint = '/masters/company'

export type CompanyDetailPayload = {
  name: string
  email?: string | null
  phone?: string | null
  address?: string | null
  tin_number?: string | null
  vat_number?: string | null
  website?: string | null
}

export const companyService = {
  async get(): Promise<CompanyDetailRecord> {
    return unwrap((await api.get(endpoint)).data)
  },

  async update(payload: CompanyDetailPayload): Promise<CompanyDetailRecord> {
    return unwrap((await api.put(endpoint, payload)).data)
  },

  async uploadLogo(file: File): Promise<CompanyDetailRecord> {
    const formData = new FormData()
    formData.append('logo', file)
    return unwrap((await api.post(`${endpoint}/logo`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })).data)
  },
}
