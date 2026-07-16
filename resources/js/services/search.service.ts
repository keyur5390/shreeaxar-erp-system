import axios from 'axios'
import api from './api'
import { unwrap } from './crud'

export interface GlobalSearchItem {
  id: string
  label: string
  sub?: string | null
  url: string
}

export interface GlobalSearchResult {
  customers: GlobalSearchItem[]
  products: GlobalSearchItem[]
  quotations: GlobalSearchItem[]
  total_count: number
}

export const searchService = {
  async search(q: string, cancelToken?: ReturnType<typeof axios.CancelToken.source>['token']): Promise<GlobalSearchResult> {
    return unwrap((await api.get('/search', {
      params: { q },
      cancelToken,
    })).data)
  },
}
