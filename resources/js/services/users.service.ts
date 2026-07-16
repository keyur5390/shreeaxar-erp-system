import api from './api'
import { unwrap } from './crud'
import type { PaginatedItems, UserDetail, UserListItem, UserListParams } from '@/types'

function buildUserFormData(payload: Record<string, unknown>, avatar?: File | null, removeAvatar?: boolean): FormData {
  const formData = new FormData()

  const scalarFields = ['first_name', 'last_name', 'email', 'password', 'password_confirmation', 'contact_number', 'department_id', 'role_id'] as const
  scalarFields.forEach((field) => {
    const value = payload[field]
    if (value !== undefined && value !== null && value !== '') {
      formData.append(field, String(value))
    }
  })

  if (removeAvatar) {
    formData.append('remove_avatar', '1')
  }

  if (avatar instanceof File) {
    formData.append('profile_image', avatar)
  }

  const addresses = payload.addresses as Array<Record<string, string | undefined>> | undefined
  if (addresses) {
    addresses.forEach((address, index) => {
      Object.entries(address).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          formData.append(`addresses[${index}][${key}]`, value)
        }
      })
    })
  }

  return formData
}

export const usersService = {
  async getUsers(params?: UserListParams): Promise<PaginatedItems<UserListItem>> {
    const query: Record<string, string | number | boolean> = {}
    if (params?.search) query.search = params.search
    if (params?.role_id) query.role_id = params.role_id
    if (params?.department_id) query.department_id = params.department_id
    if (params?.is_active !== undefined && params?.is_active !== '') query.is_active = params.is_active
    if (params?.page) query.page = params.page
    if (params?.per_page) query.per_page = params.per_page
    return unwrap((await api.get('/users', { params: query })).data)
  },

  async get(id: string): Promise<UserDetail> {
    return unwrap((await api.get(`/users/${id}`)).data)
  },

  async create(payload: Record<string, unknown>, avatar?: File | null): Promise<UserDetail> {
    const formData = buildUserFormData(payload, avatar)
    return unwrap((await api.post('/users', formData, { headers: { 'Content-Type': 'multipart/form-data' } })).data)
  },

  async update(id: string, payload: Record<string, unknown>, avatar?: File | null, removeAvatar?: boolean): Promise<UserDetail> {
    const formData = buildUserFormData(payload, avatar, removeAvatar)
    formData.append('_method', 'PUT')
    return unwrap((await api.post(`/users/${id}`, formData, { headers: { 'Content-Type': 'multipart/form-data' } })).data)
  },

  async remove(id: string): Promise<void> {
    await api.delete(`/users/${id}`)
  },

  async checkEmail(email: string, excludeId?: string): Promise<{ available: boolean }> {
    return unwrap((await api.get('/users/check-email', { params: { email, exclude_id: excludeId } })).data)
  },

  async toggleStatus(id: string): Promise<{ id: string; is_active: boolean }> {
    return unwrap((await api.patch(`/users/${id}/toggle-status`)).data)
  },
}
