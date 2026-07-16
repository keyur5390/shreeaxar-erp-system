import api from './api'
import { unwrap } from './crud'
import type { AddressType, CompanyDetailRecord, Country, Department, State, Tax, Unit } from '@/types'

export const mastersService = {
  async getCompany(): Promise<CompanyDetailRecord> { return unwrap((await api.get('/masters/company')).data) },
  async getDepartments(): Promise<Department[]> { return unwrap((await api.get('/masters/departments')).data) },
  async getUnits(): Promise<Unit[]> { return unwrap((await api.get('/masters/units')).data) },
  async getTaxes(): Promise<Tax[]> { return unwrap((await api.get('/masters/taxes')).data) },
  async getAddressTypes(): Promise<AddressType[]> { return unwrap((await api.get('/masters/address-types')).data) },
  async getCountries(): Promise<Country[]> { return unwrap((await api.get('/masters/countries')).data) },
  async getStates(countryId?: number | string): Promise<State[]> { return unwrap((await api.get('/masters/states', { params: { country_id: countryId } })).data) },
}
