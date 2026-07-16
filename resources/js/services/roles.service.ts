import api from './api'
import { unwrap } from './crud'
import type { ModulePermissionMatrix, Role } from '@/types'

const endpoint = '/masters/roles'

export const rolesService = {
  async list(): Promise<Role[]> {
    return unwrap((await api.get(endpoint)).data)
  },

  async get(id: string): Promise<Role> {
    return unwrap((await api.get(`${endpoint}/${id}`)).data)
  },

  async create(payload: { name: string }): Promise<Role> {
    return unwrap((await api.post(endpoint, payload)).data)
  },

  async update(id: string, payload: { name: string }): Promise<Role> {
    return unwrap((await api.put(`${endpoint}/${id}`, payload)).data)
  },

  async remove(id: string): Promise<void> {
    await api.delete(`${endpoint}/${id}`)
  },

  async getPermissions(id: string): Promise<ModulePermissionMatrix[]> {
    return unwrap((await api.get(`${endpoint}/${id}/permissions`)).data)
  },

  async updatePermissions(id: string, permissions: ModulePermissionMatrix[]): Promise<ModulePermissionMatrix[]> {
    return unwrap((await api.put(`${endpoint}/${id}/permissions`, { permissions })).data)
  },

  async getModules(): Promise<string[]> {
    return unwrap((await api.get('/masters/modules')).data)
  },
}
