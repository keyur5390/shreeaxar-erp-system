import { onMounted, onUnmounted, ref } from 'vue'

const events = ['mousemove', 'keydown', 'mousedown', 'touchstart', 'scroll'] as const

export function useIdleTimer(idleMinutes = 15) {
  const isIdle = ref(false)
  let timer: ReturnType<typeof window.setTimeout> | null = null

  function clearTimer() {
    if (timer) window.clearTimeout(timer)
    timer = null
  }

  function resetTimer() {
    isIdle.value = false
    clearTimer()
    timer = window.setTimeout(() => { isIdle.value = true }, idleMinutes * 60 * 1000)
  }

  onMounted(() => {
    events.forEach((event) => window.addEventListener(event, resetTimer, { passive: true }))
    resetTimer()
  })

  onUnmounted(() => {
    clearTimer()
    events.forEach((event) => window.removeEventListener(event, resetTimer))
  })

  return { isIdle, resetTimer }
}
