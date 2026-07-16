import axios, { AxiosError, type AxiosRequestConfig, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios'
import { useAuthStore } from '@/stores/auth.store'

export type LaravelValidationErrors = Record<string, string[]>

export class ValidationError extends Error {
  errors: LaravelValidationErrors
  response?: AxiosResponse

  constructor(message: string, errors: LaravelValidationErrors, response?: AxiosResponse) {
    super(message)
    this.name = 'ValidationError'
    this.errors = errors
    this.response = response
  }
}

type QueuedRequest = {
  resolve: (token: string | null) => void
  reject: (error: unknown) => void
}

type RetriableRequestConfig = AxiosRequestConfig & { _retry?: boolean; _skipAuthRefresh?: boolean; _csrfRetry?: boolean }

let isRefreshing = false
let csrfTokenPromise: Promise<void> | null = null
let failedQueue: QueuedRequest[] = []

export function resetCsrfToken(): void {
  csrfTokenPromise = null
}

function notify(message: string): void {
  if (typeof window !== 'undefined') {
    window.dispatchEvent(new CustomEvent('shreeaxar:toast', { detail: { message, type: 'error' } }))
  }
  console.error(message)
}

function processQueue(error: unknown, token: string | null = null): void {
  failedQueue.forEach((request) => {
    if (error) request.reject(error)
    else request.resolve(token)
  })
  failedQueue = []
}

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  timeout: 30000,
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
})

function sanctumCsrfUrl(): string {
  const apiUrl = import.meta.env.VITE_API_URL ?? ''
  if (typeof apiUrl === 'string' && apiUrl.startsWith('/')) {
    return '/sanctum/csrf-cookie'
  }

  const appUrl = import.meta.env.VITE_APP_URL ?? apiUrl.replace(/\/api\/?$/, '')
  return `${appUrl.replace(/\/$/, '')}/sanctum/csrf-cookie`
}

export function getCsrfToken(): Promise<void> {
  if (!csrfTokenPromise) {
    csrfTokenPromise = axios
      .get(sanctumCsrfUrl(), {
        withCredentials: true,
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        xsrfCookieName: 'XSRF-TOKEN',
        xsrfHeaderName: 'X-XSRF-TOKEN',
      })
      .then(() => undefined)
      .catch((error) => {
        resetCsrfToken()
        throw error
      })
  }

  return csrfTokenPromise
}

api.interceptors.request.use(async (config: InternalAxiosRequestConfig) => {
  config.headers.set('X-Requested-With', 'XMLHttpRequest')

  const method = config.method?.toUpperCase()
  const url = config.url ?? ''
  if (method && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method) && !url.includes('/sanctum/csrf-cookie')) {
    await getCsrfToken()
  }

  const authStore = useAuthStore()
  if (authStore.token) config.headers.set('Authorization', `Bearer ${authStore.token}`)

  return config
})

api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError<{ message?: string; errors?: LaravelValidationErrors }>) => {
    const originalRequest = (error.config ?? {}) as RetriableRequestConfig
    const status = error.response?.status

    if (!error.response) {
      notify('No internet connection.')
      return Promise.reject(error)
    }

    if (status === 419 && !originalRequest._csrfRetry) {
      originalRequest._csrfRetry = true
      resetCsrfToken()
      await getCsrfToken()
      return api(originalRequest)
    }

    if (status === 401 && !originalRequest._retry && !originalRequest._skipAuthRefresh && !originalRequest.url?.includes('/auth/refresh')) {
      if (isRefreshing) {
        return new Promise((resolve, reject) => {
          failedQueue.push({ resolve, reject })
        }).then((token) => {
          if (token) originalRequest.headers = { ...originalRequest.headers, Authorization: `Bearer ${token}` }
          return api(originalRequest)
        })
      }

      originalRequest._retry = true
      isRefreshing = true

      try {
        const refreshResponse = await api.post<{ token?: string; expires_at?: string | null; expiresAt?: string | null; data?: { token?: string; expires_at?: string | null; expiresAt?: string | null } }>('/auth/refresh', undefined, { _skipAuthRefresh: true } as RetriableRequestConfig)
        const token = refreshResponse.data.token ?? refreshResponse.data.data?.token ?? null
        const expiresAt = refreshResponse.data.expires_at ?? refreshResponse.data.expiresAt ?? refreshResponse.data.data?.expires_at ?? refreshResponse.data.data?.expiresAt ?? null
        const authStore = useAuthStore()
        authStore.setToken(token, expiresAt)
        processQueue(null, token)
        if (token) originalRequest.headers = { ...originalRequest.headers, Authorization: `Bearer ${token}` }
        return api(originalRequest)
      } catch (refreshError) {
        processQueue(refreshError, null)
        const authStore = useAuthStore()
        authStore.clearAuth()
        const { default: router } = await import('@/router')
        router.push('/login')
        return Promise.reject(refreshError)
      } finally {
        isRefreshing = false
      }
    }

    if (status === 429) {
      const retryAfter = error.response.headers['retry-after']
      notify(`Too many requests. Try again in ${retryAfter ?? 'a few'} seconds.`)
    } else if (status === 422) {
      const validationError = new ValidationError(error.response.data.message ?? 'The given data was invalid.', error.response.data.errors ?? {}, error.response)
      return Promise.reject(validationError)
    } else if (status === 502 || status === 503) {
      notify('Server temporarily unavailable.')
    } else if (error.response.data?.message) {
      error.message = error.response.data.message
    }

    return Promise.reject(error)
  },
)

export default api
