import api from './api'
import { unwrap } from './crud'
import type { State } from '@/types'

const endpoint = '/masters/states'

export const statesService = {
  async list(countryId?: string): Promise<State[]> {
    const params = countryId ? { country_id: countryId } : undefined
    return unwrap((await api.get(endpoint, { params })).data)
  },

  async create(payload: { name: string; country_id: string }): Promise<State> {
    return unwrap((await api.post(endpoint, payload)).data)
  },

  async update(id: string, payload: { name: string; country_id: string }): Promise<State> {
    return unwrap((await api.put(`${endpoint}/${id}`, payload)).data)
  },

  async remove(id: string): Promise<void> {
    await api.delete(`${endpoint}/${id}`)
  },
}
