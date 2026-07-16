import type { InjectionKey, Ref } from 'vue'

export interface TabsContext {
  activeTab: Ref<string>
  setActiveTab: (value: string) => void
}

export const tabsKey: InjectionKey<TabsContext> = Symbol('tabs')
