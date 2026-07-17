import { inject, type InjectionKey } from 'vue'

export function useRequiredInject<T>(key: InjectionKey<T>, message: string): T {
  const value = inject(key)

  if (value === undefined) {
    throw new Error(message)
  }

  return value
}
