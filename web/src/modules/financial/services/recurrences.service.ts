import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { FinancialRecurrence, RecurrenceFrequency } from '@/shared/types/models'

export interface RecurrencePayload {
  description: string
  default_value: number
  due_day: number
  frequency: RecurrenceFrequency
  supplier_id?: string | null
  category_id?: string | null
  active?: boolean
  generate_automatically?: boolean
}

export const recurrencesService = {
  async list(params: ListParams): Promise<PaginatedResponse<FinancialRecurrence>> {
    const response = await http.get<PaginatedResponse<FinancialRecurrence>>('/financial/recurrences', {
      params,
    })

    return response.data
  },

  async get(id: string): Promise<FinancialRecurrence> {
    const response = await http.get<ApiResponse<FinancialRecurrence>>(`/financial/recurrences/${id}`)

    return response.data.data
  },

  async create(payload: RecurrencePayload): Promise<FinancialRecurrence> {
    const response = await http.post<ApiResponse<FinancialRecurrence>>('/financial/recurrences', payload)

    return response.data.data
  },

  async update(id: string, payload: RecurrencePayload): Promise<FinancialRecurrence> {
    const response = await http.put<ApiResponse<FinancialRecurrence>>(
      `/financial/recurrences/${id}`,
      payload,
    )

    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/financial/recurrences/${id}`)
  },
}
