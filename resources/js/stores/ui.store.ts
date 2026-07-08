import { ref } from 'vue'
import { defineStore } from 'pinia'

export const useUiStore = defineStore('ui', () => {
  const sidebarOpen = ref(false)
  const notificationCount = ref(0)
  const pageTitle = ref('')
  const expandedGroups = ref<Record<string, boolean>>({})

  function setPageTitle(title: string) { pageTitle.value = title }
  function setSidebarOpen(open: boolean) { sidebarOpen.value = open }
  function toggleSidebar() { sidebarOpen.value = !sidebarOpen.value }
  function setNotificationCount(count: number) { notificationCount.value = count }
  function isGroupExpanded(key: string) { return expandedGroups.value[key] ?? true }
  function setGroupExpanded(key: string, expanded: boolean) { expandedGroups.value = { ...expandedGroups.value, [key]: expanded } }
  function toggleGroup(key: string) { setGroupExpanded(key, !isGroupExpanded(key)) }

  return { sidebarOpen, notificationCount, pageTitle, expandedGroups, setPageTitle, setSidebarOpen, toggleSidebar, setNotificationCount, isGroupExpanded, setGroupExpanded, toggleGroup }
})
