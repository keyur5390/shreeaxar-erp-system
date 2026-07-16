import { computed } from 'vue'
import { useBreakpoints, useWindowSize } from '@vueuse/core'

export function useBreakpoint() {
  const bp = useBreakpoints({
    mobile: 0,
    tablet: 768,
    desktop: 1024,
  })
  const { width } = useWindowSize()

  return {
    width,
    isMobile: computed(() => bp.smaller('tablet').value),
    isTablet: computed(() => bp.between('tablet', 'desktop').value),
    isDesktop: computed(() => bp.greaterOrEqual('desktop').value),
  }
}
