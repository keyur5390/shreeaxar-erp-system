import { ref } from 'vue'
import { defineStore } from 'pinia'
import type { CompanyDetail } from '@/types'

export const useCompanyStore = defineStore('company', () => {
  const company = ref<CompanyDetail | null>(null)
  function setCompany(nextCompany: CompanyDetail | null) { company.value = nextCompany }
  return { company, setCompany }
})
