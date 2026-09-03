import { http } from '@/shared/api/http'
import type { ApiResponse, PaginatedResponse } from '@/shared/types/api'
import type { Payable } from '@/shared/types/models'

export interface PayableFilters {
  status?: string
  supplier_id?: string
  category_id?: string
  from?: string
  to?: string
  search?: string
  page?: number
  per_page?: number
}

export interface PayablePayload {
  description: string
  value: number
  due_date: string
  issue_date?: string | null
  supplier_id?: string | null
  category_id?: string | null
}

export interface InstallmentPlanPayload {
  description: string
  value: number
  first_due_date: string
  first_installment_number: number
  total_installments: number
  supplier_id?: string | null
  category_id?: string | null
}

export interface UpdatePayablePayload {
  scope?: 'this' | 'this_and_next' | 'all'
  description?: string
  value?: number
  due_date?: string
  issue_date?: string | null
  supplier_id?: string | null
  category_id?: string | null
}

export const payablesService = {
  async list(params: PayableFilters): Promise<PaginatedResponse<Payable>> {
    const response = await http.get<PaginatedResponse<Payable>>('/financial/payables', { params })

    return response.data
  },

  async get(id: string): Promise<Payable> {
    const response = await http.get<ApiResponse<Payable>>(`/financial/payables/${id}`)

    return response.data.data
  },

  async create(payload: PayablePayload): Promise<Payable> {
    const response = await http.post<ApiResponse<Payable>>('/financial/payables', payload)

    return response.data.data
  },

  async createInstallments(payload: InstallmentPlanPayload): Promise<unknown> {
    const response = await http.post<ApiResponse<unknown>>('/financial/payables/installments', payload)

    return response.data.data
  },

  async update(id: string, payload: UpdatePayablePayload): Promise<{ updated: number }> {
    const response = await http.put<ApiResponse<{ updated: number }>>(
      `/financial/payables/${id}`,
      payload,
    )

    return response.data.data
  },

  async pay(id: string, payload: { paid_at: string; paid_value: number }): Promise<Payable> {
    const response = await http.post<ApiResponse<Payable>>(`/financial/payables/${id}/pay`, payload)

    return response.data.data
  },

  async cancel(id: string, scope?: 'this' | 'this_and_next' | 'all'): Promise<{ cancelled: number }> {
    const response = await http.post<ApiResponse<{ cancelled: number }>>(
      `/financial/payables/${id}/cancel`,
      scope ? { scope } : {},
    )

    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/financial/payables/${id}`)
  },
}
