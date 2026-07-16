import api from './api'
import { unwrap } from './crud'
import type { Department } from '@/types'

const endpoint = '/masters/departments'

export const departmentsService = {
  async list(): Promise<Department[]> {
    return unwrap((await api.get(endpoint)).data)
  },

  async create(payload: { name: string }): Promise<Department> {
    return unwrap((await api.post(endpoint, payload)).data)
  },

  async update(id: string, payload: { name: string }): Promise<Department> {
    return unwrap((await api.put(`${endpoint}/${id}`, payload)).data)
  },

  async remove(id: string): Promise<void> {
    await api.delete(`${endpoint}/${id}`)
  },
}
