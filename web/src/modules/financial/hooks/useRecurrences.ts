import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import type { ListParams } from '@/shared/types/api'
import { toast } from '@/shared/stores/toast.store'
import { recurrencesService, type RecurrencePayload } from '../services/recurrences.service'

function invalidateFinancialQueries(queryClient: ReturnType<typeof useQueryClient>) {
  queryClient.invalidateQueries({ queryKey: queryKeys.financial.recurrences.all })
  queryClient.invalidateQueries({ queryKey: queryKeys.financial.payables.all })
}

export function useRecurrencesQuery(params: ListParams) {
  return useQuery({
    queryKey: queryKeys.financial.recurrences.list(params),
    queryFn: () => recurrencesService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useRecurrenceQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.financial.recurrences.detail(id ?? ''),
    queryFn: () => recurrencesService.get(id!),
    enabled: !!id,
  })
}

export function useCreateRecurrence() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: RecurrencePayload) => recurrencesService.create(payload),
    onSuccess: (_data, variables) => {
      invalidateFinancialQueries(queryClient)

      if (variables.generate_automatically === false) {
        toast.success('Recorrência criada', 'A recorrência foi cadastrada com sucesso.')
        return
      }

      toast.success('Recorrência criada', 'As contas do período foram geradas automaticamente.')
    },
  })
}

export function useUpdateRecurrence(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: RecurrencePayload) => recurrencesService.update(id, payload),
    onSuccess: (_data, variables) => {
      invalidateFinancialQueries(queryClient)

      if (variables.generate_automatically === false || variables.active === false) {
        toast.success('Recorrência atualizada', 'As alterações foram salvas.')
        return
      }

      toast.success('Recorrência atualizada', 'As contas do período foram atualizadas.')
    },
  })
}

export function useDeleteRecurrence() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => recurrencesService.remove(id),
    onSuccess: () => {
      invalidateFinancialQueries(queryClient)
      toast.success('Recorrência removida', 'A recorrência foi excluída com sucesso.')
    },
  })
}

export function useGenerateRecurrencePayables() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => recurrencesService.generatePayables(),
    onSuccess: (result) => {
      invalidateFinancialQueries(queryClient)

      if (result.generated > 0) {
        toast.success(
          'Contas geradas',
          `${result.generated} conta(s) criada(s) a partir das recorrências ativas.`,
        )
        return
      }

      toast.info(
        'Nada a gerar',
        'Todas as recorrências ativas já possuem contas para os próximos 12 meses.',
      )
    },
  })
}
