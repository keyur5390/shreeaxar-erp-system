import api from './api'
import { unwrap } from './crud'
import type { Unit } from '@/types'

const endpoint = '/masters/units'

export const unitsService = {
  async list(): Promise<Unit[]> {
    return unwrap((await api.get(endpoint)).data)
  },

  async create(payload: { code: string; name: string }): Promise<Unit> {
    return unwrap((await api.post(endpoint, payload)).data)
  },

  async update(id: string, payload: { code: string; name: string }): Promise<Unit> {
    return unwrap((await api.put(`${endpoint}/${id}`, payload)).data)
  },

  async remove(id: string): Promise<void> {
    await api.delete(`${endpoint}/${id}`)
  },
}
