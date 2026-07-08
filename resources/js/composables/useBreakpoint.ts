import { computed, onMounted, onUnmounted, ref } from 'vue'

export function useBreakpoint() {
  const width = ref(typeof window === 'undefined' ? 1024 : window.innerWidth)
  const update = () => { width.value = window.innerWidth }

  onMounted(() => window.addEventListener('resize', update, { passive: true }))
  onUnmounted(() => window.removeEventListener('resize', update))

  return {
    width,
    isMobile: computed(() => width.value < 768),
    isTablet: computed(() => width.value >= 768 && width.value < 1024),
    isDesktop: computed(() => width.value >= 1024),
  }
}
