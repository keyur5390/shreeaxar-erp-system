import { onMounted, onUnmounted, ref, type ComputedRef, type Ref } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'

export function useFormDirtyGuard(isDirty: Ref<boolean> | ComputedRef<boolean>) {
  const bypassGuard = ref(false)

  function allowNavigation() {
    bypassGuard.value = true
  }

  onBeforeRouteLeave((_to, _from, next) => {
    if (bypassGuard.value || !isDirty.value) {
      next()
      return
    }
    if (window.confirm('You have unsaved changes. Leave without saving?')) {
      next()
    } else {
      next(false)
    }
  })

  const beforeUnloadHandler = (event: BeforeUnloadEvent) => {
    if (isDirty.value && !bypassGuard.value) {
      event.preventDefault()
      event.returnValue = ''
    }
  }

  onMounted(() => window.addEventListener('beforeunload', beforeUnloadHandler))
  onUnmounted(() => window.removeEventListener('beforeunload', beforeUnloadHandler))

  return { allowNavigation }
}
