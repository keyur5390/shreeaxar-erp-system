import { ref } from 'vue'
import { defineStore } from 'pinia'

const SIDEBAR_COLLAPSED_KEY = 'shreeaxar_sidebar_collapsed'

function readSidebarCollapsed(): boolean {
  try {
    return localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === 'true'
  } catch {
    return false
  }
}

export const useUiStore = defineStore('ui', () => {
  const sidebarOpen = ref(false)
  const sidebarCollapsed = ref(readSidebarCollapsed())
  const notificationCount = ref(0)
  const pageTitle = ref('')
  const expandedGroups = ref<Record<string, boolean>>({})

  function setPageTitle(title: string) { pageTitle.value = title }
  function setSidebarOpen(open: boolean) { sidebarOpen.value = open }
  function toggleSidebar() { sidebarOpen.value = !sidebarOpen.value }
  function setSidebarCollapsed(collapsed: boolean) {
    sidebarCollapsed.value = collapsed
    try {
      localStorage.setItem(SIDEBAR_COLLAPSED_KEY, String(collapsed))
    } catch {
      // ignore storage errors
    }
  }
  function toggleSidebarCollapse() { setSidebarCollapsed(!sidebarCollapsed.value) }
  function setNotificationCount(count: number) { notificationCount.value = count }
  function isGroupExpanded(key: string) { return expandedGroups.value[key] ?? false }
  function setGroupExpanded(key: string, expanded: boolean) { expandedGroups.value = { ...expandedGroups.value, [key]: expanded } }
  function toggleGroup(key: string) { setGroupExpanded(key, !isGroupExpanded(key)) }

  return {
    sidebarOpen,
    sidebarCollapsed,
    notificationCount,
    pageTitle,
    expandedGroups,
    setPageTitle,
    setSidebarOpen,
    toggleSidebar,
    setSidebarCollapsed,
    toggleSidebarCollapse,
    setNotificationCount,
    isGroupExpanded,
    setGroupExpanded,
    toggleGroup,
  }
})
