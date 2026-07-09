import api from './api'
import type { PermissionMap, User } from '@/types'

export interface LoginCredentials { email: string; password: string; remember?: boolean }
export interface AuthPayload { user: User; token?: string; permissions?: PermissionMap | string[]; expires_at?: string | null; expiresAt?: string | null }

function unwrap<T>(response: { data: T | { data: T } }): T {
  return response.data && typeof response.data === 'object' && 'data' in response.data ? (response.data as { data: T }).data : response.data as T
}

export const authService = {
  async login(credentials: LoginCredentials): Promise<AuthPayload> { return unwrap(await api.post('/auth/login', credentials)) },
  async me(): Promise<AuthPayload> { return unwrap(await api.get('/auth/me')) },
  async refresh(): Promise<AuthPayload> { return unwrap(await api.post('/auth/refresh')) },
  async logout(): Promise<void> { await api.post('/auth/logout') },
  async forgotPassword(email: string): Promise<void> { await api.post('/auth/forgot-password', { email }) },
  async verifyOtp(email: string, otp: string): Promise<{ reset_token: string }> { return unwrap(await api.post('/auth/verify-otp', { email, otp })) },
  async resetPassword(resetToken: string, password: string, passwordConfirmation: string): Promise<void> { await api.post('/auth/reset-password', { reset_token: resetToken, new_password: password, new_password_confirmation: passwordConfirmation }) },
}
