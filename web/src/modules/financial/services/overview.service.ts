import { http } from '@/shared/api/http'
import type { ApiResponse, PaginatedResponse } from '@/shared/types/api'
import type { CashflowProjection, FinancialSummary, Payable } from '@/shared/types/models'

export const overviewService = {
  async summary(): Promise<FinancialSummary> {
    const response = await http.get<ApiResponse<FinancialSummary>>('/financial/summary')

    return response.data.data
  },

  async cashflow(): Promise<CashflowProjection> {
    const response = await http.get<ApiResponse<CashflowProjection>>('/financial/cashflow')

    return response.data.data
  },

  async overdue(params: {
    supplier_id?: string
    category_id?: string
    page?: number
    per_page?: number
  }): Promise<PaginatedResponse<Payable>> {
    const response = await http.get<PaginatedResponse<Payable>>('/financial/overdue', { params })

    return response.data
  },
}
