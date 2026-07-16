import { onMounted, onUnmounted } from 'vue'

type ShortcutHandler = (event: KeyboardEvent) => void

function isEditableTarget(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) return false
  const tag = target.tagName
  if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return true
  return target.isContentEditable
}

export function useKeyboardShortcuts(shortcuts: Record<string, ShortcutHandler>) {
  function onKeydown(event: KeyboardEvent) {
    if (isEditableTarget(event.target)) return

    for (const [combo, handler] of Object.entries(shortcuts)) {
      const parts = combo.toLowerCase().split('+').map((part) => part.trim())
      const needsCtrl = parts.includes('ctrl') || parts.includes('cmd')
      const key = parts.find((part) => !['ctrl', 'cmd', 'shift', 'alt'].includes(part))

      if (!key) continue

      const ctrlPressed = event.ctrlKey || event.metaKey
      if (needsCtrl && !ctrlPressed) continue
      if (!needsCtrl && ctrlPressed) continue
      if (event.key.toLowerCase() !== key) continue

      event.preventDefault()
      handler(event)
      return
    }
  }

  onMounted(() => window.addEventListener('keydown', onKeydown))
  onUnmounted(() => window.removeEventListener('keydown', onKeydown))
}
