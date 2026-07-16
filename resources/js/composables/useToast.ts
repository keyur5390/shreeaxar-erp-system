export type ToastType = 'success' | 'error' | 'info'

export function useToast() {
  function toast(message: string, type: ToastType = 'info') {
    window.dispatchEvent(new CustomEvent('shreeaxar:toast', { detail: { message, type } }))
  }

  return { toast }
}
