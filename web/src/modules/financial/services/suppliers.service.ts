import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { Supplier } from '@/shared/types/models'

export interface SupplierPayload {
  name: string
  document?: string | null
  phone?: string | null
  email?: string | null
  observations?: string | null
}

export const suppliersService = {
  async list(params: ListParams): Promise<PaginatedResponse<Supplier>> {
    const response = await http.get<PaginatedResponse<Supplier>>('/financial/suppliers', { params })

    return response.data
  },

  async all(): Promise<Supplier[]> {
    const response = await http.get<ApiResponse<Supplier[]>>('/financial/suppliers/all')

    return response.data.data
  },

  async get(id: string): Promise<Supplier> {
    const response = await http.get<ApiResponse<Supplier>>(`/financial/suppliers/${id}`)

    return response.data.data
  },

  async create(payload: SupplierPayload): Promise<Supplier> {
    const response = await http.post<ApiResponse<Supplier>>('/financial/suppliers', payload)

    return response.data.data
  },

  async update(id: string, payload: SupplierPayload): Promise<Supplier> {
    const response = await http.put<ApiResponse<Supplier>>(`/financial/suppliers/${id}`, payload)

    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/financial/suppliers/${id}`)
  },
}
