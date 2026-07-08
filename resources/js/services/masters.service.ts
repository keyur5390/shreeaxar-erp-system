import api from './api'
import { unwrap } from './crud'
import type { AddressType, CompanyDetail, Country, Department, Settings, State, Tax, Unit } from '@/types'

export const mastersService = {
  async getCompany(): Promise<CompanyDetail> { return unwrap((await api.get('/company')).data) },
  async getSettings(): Promise<Settings> { return unwrap((await api.get('/settings')).data) },
  async getDepartments(): Promise<Department[]> { return unwrap((await api.get('/departments')).data) },
  async getUnits(): Promise<Unit[]> { return unwrap((await api.get('/units')).data) },
  async getTaxes(): Promise<Tax[]> { return unwrap((await api.get('/taxes')).data) },
  async getAddressTypes(): Promise<AddressType[]> { return unwrap((await api.get('/address-types')).data) },
  async getCountries(): Promise<Country[]> { return unwrap((await api.get('/countries')).data) },
  async getStates(countryId?: number | string): Promise<State[]> { return unwrap((await api.get('/states', { params: { country_id: countryId } })).data) },
}
