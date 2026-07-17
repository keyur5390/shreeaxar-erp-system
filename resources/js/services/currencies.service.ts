import api from './api'
import { unwrap } from './crud'
import type { Currency } from '@/types'

const endpoint = '/masters/currencies'

export const currenciesService = {
  async list(): Promise<Currency[]> {
    return unwrap((await api.get(endpoint)).data)
  },

  async create(payload: Omit<Currency, 'id' | 'products_count' | 'quotations_count' | 'created_at' | 'updated_at'>): Promise<Currency> {
    return unwrap((await api.post(endpoint, payload)).data)
  },

  async update(id: string, payload: Partial<Omit<Currency, 'id' | 'products_count' | 'quotations_count' | 'created_at' | 'updated_at'>>): Promise<Currency> {
    return unwrap((await api.put(`${endpoint}/${id}`, payload)).data)
  },

  async remove(id: string): Promise<void> {
    await api.delete(`${endpoint}/${id}`)
  },
}
