import api from './api'
import type { PermissionMap, User } from '@/types'

export interface LoginCredentials { email: string; password: string; remember?: boolean }
export interface AuthPayload { user: User; token: string; permissions?: PermissionMap }

function unwrap<T>(response: { data: T | { data: T } }): T {
  return response.data && typeof response.data === 'object' && 'data' in response.data ? (response.data as { data: T }).data : response.data as T
}

export const authService = {
  async login(credentials: LoginCredentials): Promise<AuthPayload> { return unwrap(await api.post('/auth/login', credentials)) },
  async me(): Promise<AuthPayload> { return unwrap(await api.get('/auth/me')) },
  async refresh(): Promise<AuthPayload> { return unwrap(await api.post('/auth/refresh')) },
  async logout(): Promise<void> { await api.post('/auth/logout') },
  async forgotPassword(email: string): Promise<void> { await api.post('/auth/forgot-password', { email }) },
}
