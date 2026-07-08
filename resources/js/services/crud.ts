import api from './api'
import type { PaginatedResponse } from '@/types'

export interface ListParams { page?: number; per_page?: number; search?: string; [key: string]: unknown }

type MaybeWrapped<T> = T | { data: T }
export function unwrap<T>(payload: MaybeWrapped<T>): T { return payload && typeof payload === 'object' && 'data' in payload ? (payload as { data: T }).data : payload as T }

export function createCrudService<T, CreatePayload = Partial<T>, UpdatePayload = Partial<T>>(endpoint: string) {
  return {
    async list(params?: ListParams): Promise<PaginatedResponse<T> | T[]> { return unwrap((await api.get(endpoint, { params })).data) },
    async get(id: number | string): Promise<T> { return unwrap((await api.get(`${endpoint}/${id}`)).data) },
    async create(payload: CreatePayload): Promise<T> { return unwrap((await api.post(endpoint, payload)).data) },
    async update(id: number | string, payload: UpdatePayload): Promise<T> { return unwrap((await api.put(`${endpoint}/${id}`, payload)).data) },
    async remove(id: number | string): Promise<void> { await api.delete(`${endpoint}/${id}`) },
  }
}
