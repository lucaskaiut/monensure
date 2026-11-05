import { http } from '@/shared/api/http'
import type { ApiResponse, PaginatedResponse } from '@/shared/types/api'
import type {
  PayoutMethod,
  PayoutOrder,
  PayoutOverview,
  PayoutPreview,
  PayoutStatus,
  PixKeyType,
} from '@/shared/types/models'

export interface PayoutFilters {
  status?: PayoutStatus | ''
  payable_id?: string
  page?: number
  per_page?: number
}

export interface PayoutInstrument {
  method: PayoutMethod
  payment_code: string
  pix_key_type?: PixKeyType | null
}

export interface SchedulePayoutPayload extends PayoutInstrument {
  value?: number | null
  scheduled_for?: string | null
  password: string
}

export const payoutsService = {
  async list(params: PayoutFilters): Promise<PaginatedResponse<PayoutOrder>> {
    const response = await http.get<PaginatedResponse<PayoutOrder>>('/financial/payouts', {
      params: { ...params, status: params.status || undefined },
    })

    return response.data
  },

  async get(id: string): Promise<PayoutOrder> {
    const response = await http.get<ApiResponse<PayoutOrder>>(`/financial/payouts/${id}`)

    return response.data.data
  },

  async overview(): Promise<PayoutOverview> {
    const response = await http.get<ApiResponse<PayoutOverview>>('/financial/payouts/overview')

    return response.data.data
  },

  async preview(payload: PayoutInstrument): Promise<PayoutPreview> {
    const response = await http.post<ApiResponse<PayoutPreview>>('/financial/payouts/preview', payload)

    return response.data.data
  },

  async schedule(payableId: string, payload: SchedulePayoutPayload): Promise<PayoutOrder> {
    const response = await http.post<ApiResponse<PayoutOrder>>(
      `/financial/payables/${payableId}/payouts`,
      payload,
    )

    return response.data.data
  },

  async cancel(id: string): Promise<PayoutOrder> {
    const response = await http.post<ApiResponse<PayoutOrder>>(`/financial/payouts/${id}/cancel`)

    return response.data.data
  },

  async sync(id: string): Promise<PayoutOrder> {
    const response = await http.post<ApiResponse<PayoutOrder>>(`/financial/payouts/${id}/sync`)

    return response.data.data
  },

  async resolve(id: string, payload: { outcome: 'paid' | 'failed'; password: string }): Promise<PayoutOrder> {
    const response = await http.post<ApiResponse<PayoutOrder>>(`/financial/payouts/${id}/resolve`, payload)

    return response.data.data
  },
}
