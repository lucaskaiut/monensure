import { http } from '@/shared/api/http'
import type { ApiResponse, ListParams, PaginatedResponse } from '@/shared/types/api'
import type { Category } from '@/shared/types/models'

export interface CategoryPayload {
  name: string
  parent_id?: string | null
}

export const categoriesService = {
  async list(params: ListParams): Promise<PaginatedResponse<Category>> {
    const response = await http.get<PaginatedResponse<Category>>('/financial/categories', { params })

    return response.data
  },

  async all(): Promise<Category[]> {
    const response = await http.get<ApiResponse<Category[]>>('/financial/categories/all')

    return response.data.data
  },

  async tree(): Promise<Category[]> {
    const response = await http.get<ApiResponse<Category[]>>('/financial/categories/tree')

    return response.data.data
  },

  async get(id: string): Promise<Category> {
    const response = await http.get<ApiResponse<Category>>(`/financial/categories/${id}`)

    return response.data.data
  },

  async create(payload: CategoryPayload): Promise<Category> {
    const response = await http.post<ApiResponse<Category>>('/financial/categories', payload)

    return response.data.data
  },

  async update(id: string, payload: CategoryPayload): Promise<Category> {
    const response = await http.put<ApiResponse<Category>>(`/financial/categories/${id}`, payload)

    return response.data.data
  },

  async remove(id: string): Promise<void> {
    await http.delete(`/financial/categories/${id}`)
  },
}
