import api from './api'
import { unwrap } from './crud'
import type { Country, PaginatedItems } from '@/types'

const endpoint = '/masters/countries'

export interface CountryListParams {
  page?: number
  per_page?: number
  search?: string
}

export const countriesService = {
  async list(params?: CountryListParams): Promise<PaginatedItems<Country>> {
    return unwrap((await api.get(endpoint, { params })).data)
  },

  async create(payload: { name: string; iso_code: string }): Promise<Country> {
    return unwrap((await api.post(endpoint, payload)).data)
  },

  async update(id: string, payload: { name: string; iso_code: string }): Promise<Country> {
    return unwrap((await api.put(`${endpoint}/${id}`, payload)).data)
  },

  async remove(id: string): Promise<void> {
    await api.delete(`${endpoint}/${id}`)
  },

  async states(countryId: string) {
    return unwrap((await api.get(`${endpoint}/${countryId}/states`)).data)
  },
}
