import api from './api'
import { unwrap } from './crud'
import type { AddressType } from '@/types'

const endpoint = '/masters/address-types'

export const addressTypesService = {
  async list(): Promise<AddressType[]> {
    return unwrap((await api.get(endpoint)).data)
  },

  async create(payload: { name: string }): Promise<AddressType> {
    return unwrap((await api.post(endpoint, payload)).data)
  },

  async update(id: string, payload: { name: string }): Promise<AddressType> {
    return unwrap((await api.put(`${endpoint}/${id}`, payload)).data)
  },

  async remove(id: string): Promise<void> {
    await api.delete(`${endpoint}/${id}`)
  },
}
