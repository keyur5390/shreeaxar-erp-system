import api from './api'
import { unwrap } from './crud'
import type { SettingRecord } from '@/types'

const endpoint = '/masters/settings'

export const settingsService = {
  async get(key: string): Promise<SettingRecord> {
    return unwrap((await api.get(`${endpoint}/${key}`)).data)
  },

  async update(key: string, value: string): Promise<SettingRecord> {
    return unwrap((await api.put(`${endpoint}/${key}`, { value })).data)
  },
}
