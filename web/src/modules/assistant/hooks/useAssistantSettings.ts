import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import type {
  AssistantConnectionTestPayload,
  AssistantSettingsPayload,
} from '../schemas/assistant-settings.schema'
import { assistantSettingsService } from '../services/assistant-settings.service'

export function useAssistantSettingsQuery() {
  return useQuery({
    queryKey: queryKeys.assistant.settings,
    queryFn: () => assistantSettingsService.get(),
  })
}

export function useUpdateAssistantSettings() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: AssistantSettingsPayload) => assistantSettingsService.update(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.assistant.settings })
      toast.success('Configurações salvas', 'As configurações do assistente foram atualizadas.')
    },
  })
}

export function useTestAssistantConnection() {
  return useMutation({
    mutationFn: (payload: AssistantConnectionTestPayload) => assistantSettingsService.testConnection(payload),
    onSuccess: (result) => {
      if (result.ok) {
        toast.success('Conexão válida', result.message)
      } else {
        toast.error('Falha na conexão', result.message)
      }
    },
    onError: () => {
      toast.error('Falha na conexão', 'Não foi possível testar a conexão com o provedor de IA.')
    },
  })
}
