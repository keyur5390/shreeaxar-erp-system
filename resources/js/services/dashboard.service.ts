import api from './api'
import { unwrap } from './crud'
import type { DashboardKPIs, QuotationByStatus, QuotationTrend, TopProduct } from '@/types'

export interface DashboardData { kpis: DashboardKPIs; quotations_by_status: QuotationByStatus[]; quotation_trends: QuotationTrend[]; top_products: TopProduct[] }

export const dashboardService = {
  async getDashboard(params?: Record<string, unknown>): Promise<DashboardData> { return unwrap((await api.get('/dashboard', { params })).data) },
}
