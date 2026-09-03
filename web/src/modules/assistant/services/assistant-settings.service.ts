import { http } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/types/api'
import type { AssistantConnectionTestResult, AssistantSettings } from '@/shared/types/models'
import type {
  AssistantConnectionTestPayload,
  AssistantSettingsPayload,
} from '../schemas/assistant-settings.schema'

export const assistantSettingsService = {
  async get(): Promise<AssistantSettings> {
    const response = await http.get<ApiResponse<AssistantSettings>>('/assistant/settings')

    return response.data.data
  },

  async update(payload: AssistantSettingsPayload): Promise<AssistantSettings> {
    const response = await http.patch<ApiResponse<AssistantSettings>>('/assistant/settings', payload)

    return response.data.data
  },

  async testConnection(payload: AssistantConnectionTestPayload): Promise<AssistantConnectionTestResult> {
    const response = await http.post<ApiResponse<AssistantConnectionTestResult>>(
      '/assistant/settings/test-connection',
      payload,
    )

    return response.data.data
  },
}
