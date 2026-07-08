import { ref } from 'vue'
import { defineStore } from 'pinia'
import { mastersService } from '@/services/masters.service'
import type { CompanyDetail } from '@/types'

export const useCompanyStore = defineStore('company', () => {
  const company = ref<CompanyDetail | null>(null)
  function setCompany(nextCompany: CompanyDetail | null) { company.value = nextCompany }
  async function loadCompany() {
    company.value = await mastersService.getCompany()
    return company.value
  }
  return { company, setCompany, loadCompany }
})
