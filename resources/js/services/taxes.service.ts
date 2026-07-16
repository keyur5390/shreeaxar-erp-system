import api from './api'
import { unwrap } from './crud'
import type { Tax } from '@/types'

const endpoint = '/masters/taxes'

export const taxesService = {
  async list(): Promise<Tax[]> {
    return unwrap((await api.get(endpoint)).data)
  },

  async update(id: string, payload: { rate: number }): Promise<Tax> {
    return unwrap((await api.put(`${endpoint}/${id}`, payload)).data)
  },
}
