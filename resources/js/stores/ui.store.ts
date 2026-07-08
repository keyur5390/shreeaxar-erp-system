import { ref } from 'vue'
import { defineStore } from 'pinia'

const EXPANDED_GROUPS_KEY = 'shreeaxar.expandedGroups'

function loadExpandedGroups(): Record<string, boolean> {
  try {
    return JSON.parse(localStorage.getItem(EXPANDED_GROUPS_KEY) || '{}')
  } catch {
    return {}
  }
}

export const useUiStore = defineStore('ui', () => {
  const sidebarOpen = ref(false)
  const pageTitle = ref('')
  const expandedGroups = ref<Record<string, boolean>>(loadExpandedGroups())

  function setPageTitle(title: string) { pageTitle.value = title }
  function setSidebarOpen(open: boolean) { sidebarOpen.value = open }
  function toggleSidebar() { sidebarOpen.value = !sidebarOpen.value }
  function isGroupExpanded(key: string) { return expandedGroups.value[key] ?? true }
  function setGroupExpanded(key: string, expanded: boolean) {
    expandedGroups.value = { ...expandedGroups.value, [key]: expanded }
    localStorage.setItem(EXPANDED_GROUPS_KEY, JSON.stringify(expandedGroups.value))
  }
  function toggleGroup(key: string) { setGroupExpanded(key, !isGroupExpanded(key)) }

  return {
    sidebarOpen,
    pageTitle,
    expandedGroups,
    setPageTitle,
    setSidebarOpen,
    toggleSidebar,
    isGroupExpanded,
    setGroupExpanded,
    toggleGroup,
  }
})
