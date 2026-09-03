import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import {
  payablesService,
  type InstallmentPlanPayload,
  type PayableFilters,
  type PayablePayload,
  type UpdatePayablePayload,
} from '../services/payables.service'

export function usePayablesQuery(params: PayableFilters) {
  return useQuery({
    queryKey: queryKeys.financial.payables.list(params),
    queryFn: () => payablesService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function usePayableQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.financial.payables.detail(id ?? ''),
    queryFn: () => payablesService.get(id!),
    enabled: !!id,
  })
}

export function useCreatePayable() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: PayablePayload) => payablesService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.financial.payables.all })
      toast.success('Conta criada', 'A conta a pagar foi cadastrada com sucesso.')
    },
  })
}

export function useCreateInstallmentPlan() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: InstallmentPlanPayload) => payablesService.createInstallments(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.financial.payables.all })
      toast.success('Parcelamento criado', 'As parcelas foram geradas com sucesso.')
    },
  })
}

export function useUpdatePayable(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: UpdatePayablePayload) => payablesService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.financial.payables.all })
      toast.success('Conta atualizada', 'As alterações foram salvas.')
    },
  })
}

export function usePayPayable(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: { paid_at: string; paid_value: number }) => payablesService.pay(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.financial.payables.all })
      queryClient.invalidateQueries({ queryKey: queryKeys.financial.summary() })
      toast.success('Pagamento registrado', 'A conta foi marcada como paga.')
    },
  })
}

export function useCancelPayable() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, scope }: { id: string; scope?: 'this' | 'this_and_next' | 'all' }) =>
      payablesService.cancel(id, scope),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.financial.payables.all })
      queryClient.invalidateQueries({ queryKey: queryKeys.financial.summary() })
      toast.success('Conta cancelada', 'A(s) conta(s) foi(foram) cancelada(s).')
    },
  })
}

export function useDeletePayable() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => payablesService.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.financial.payables.all })
      queryClient.invalidateQueries({ queryKey: queryKeys.financial.summary() })
      toast.success('Conta removida', 'A conta foi excluída com sucesso.')
    },
  })
}
