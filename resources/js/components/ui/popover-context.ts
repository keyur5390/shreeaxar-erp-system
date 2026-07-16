import type { InjectionKey, Ref } from 'vue'

export type PopoverSide = 'top' | 'bottom' | 'left' | 'right'

export interface PopoverContext {
  open: Ref<boolean>
  side: Ref<PopoverSide>
  triggerRef: Ref<HTMLElement | null>
  setTriggerRef: (el: HTMLElement | null) => void
  toggle: () => void
  close: () => void
}

export const popoverKey: InjectionKey<PopoverContext> = Symbol('popover')
