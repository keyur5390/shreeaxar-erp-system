import { ref } from 'vue'
import { defineStore } from 'pinia'
import { mastersService } from '@/services/masters.service'
import type { CompanyDetailRecord } from '@/types'

export const useCompanyStore = defineStore('company', () => {
  const company = ref<CompanyDetailRecord | null>(null)
  function setCompany(nextCompany: CompanyDetailRecord | null) { company.value = nextCompany }
  async function loadCompany() {
    company.value = await mastersService.getCompany()
    return company.value
  }
  return { company, setCompany, loadCompany }
})
