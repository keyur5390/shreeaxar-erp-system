import { ref } from 'vue'
import { defineStore } from 'pinia'

export const useUiStore = defineStore('ui', () => {
  const sidebarOpen = ref(true)
  const pageTitle = ref('')
  function setPageTitle(title: string) { pageTitle.value = title }
  function toggleSidebar() { sidebarOpen.value = !sidebarOpen.value }
  return { sidebarOpen, pageTitle, setPageTitle, toggleSidebar }
})
