import api from './api'
import { unwrap } from './crud'
import type {
  DashboardByStatus,
  DashboardData,
  DashboardKPIs,
  DashboardTrendMonth,
  RecentActivityItem,
  TopProduct,
} from '@/types'

export const dashboardService = {
  async getAll(): Promise<DashboardData> {
    return unwrap((await api.get('/dashboard/all')).data)
  },

  async getKpis(): Promise<DashboardKPIs> {
    return unwrap((await api.get('/dashboard/kpis')).data)
  },

  async getByStatus(): Promise<DashboardByStatus[]> {
    return unwrap((await api.get('/dashboard/quotation-by-status')).data)
  },

  async getQuotationTrend(year: number): Promise<DashboardTrendMonth[]> {
    return unwrap((await api.get('/dashboard/quotation-trend', { params: { year } })).data)
  },
}

export type {
  DashboardData,
  DashboardKPIs,
  DashboardByStatus,
  DashboardTrendMonth,
  TopProduct,
  RecentActivityItem,
}
