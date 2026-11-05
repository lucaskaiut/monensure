import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { toast } from '@/shared/stores/toast.store'
import {
  payoutsService,
  type PayoutFilters,
  type PayoutInstrument,
  type SchedulePayoutPayload,
} from '../services/payouts.service'

export function usePayoutsQuery(params: PayoutFilters) {
  return useQuery({
    queryKey: queryKeys.financial.payouts.list(params),
    queryFn: () => payoutsService.list(params),
    placeholderData: keepPreviousData,
    // Pagamentos em andamento mudam de status sozinhos (webhook/agendamento).
    refetchInterval: 30_000,
  })
}

export function usePayoutQuery(id: string | null) {
  return useQuery({
    queryKey: queryKeys.financial.payouts.detail(id ?? ''),
    queryFn: () => payoutsService.get(id!),
    enabled: !!id,
  })
}

export function usePayoutOverview(enabled = true) {
  return useQuery({
    queryKey: queryKeys.financial.payouts.overview(),
    queryFn: () => payoutsService.overview(),
    enabled,
  })
}

export function usePreviewPayout() {
  return useMutation({
    mutationFn: (payload: PayoutInstrument) => payoutsService.preview(payload),
  })
}

function useInvalidatePayouts() {
  const queryClient = useQueryClient()

  return () => {
    queryClient.invalidateQueries({ queryKey: queryKeys.financial.payouts.all })
    queryClient.invalidateQueries({ queryKey: queryKeys.financial.payables.all })
    queryClient.invalidateQueries({ queryKey: queryKeys.financial.summary() })
  }
}

export function useSchedulePayout(payableId: string) {
  const invalidate = useInvalidatePayouts()

  return useMutation({
    mutationFn: (payload: SchedulePayoutPayload) => payoutsService.schedule(payableId, payload),
    onSuccess: (order) => {
      invalidate()

      if (order.status === 'scheduled') {
        toast.success('Pagamento agendado', 'O pagamento será enviado ao Asaas no horário escolhido.')
      } else {
        toast.success('Pagamento enviado', 'Acompanhe a confirmação em Pagamentos Asaas.')
      }
    },
  })
}

export function useCancelPayout() {
  const invalidate = useInvalidatePayouts()

  return useMutation({
    mutationFn: (id: string) => payoutsService.cancel(id),
    onSuccess: () => {
      invalidate()
      toast.success('Agendamento cancelado', 'Nenhum valor foi enviado ao Asaas.')
    },
  })
}

export function useSyncPayout() {
  const invalidate = useInvalidatePayouts()

  return useMutation({
    mutationFn: (id: string) => payoutsService.sync(id),
    onSuccess: () => {
      invalidate()
      toast.info('Status atualizado', 'Consultamos o Asaas.')
    },
  })
}

export function useResolvePayout() {
  const invalidate = useInvalidatePayouts()

  return useMutation({
    mutationFn: ({ id, ...payload }: { id: string; outcome: 'paid' | 'failed'; password: string }) =>
      payoutsService.resolve(id, payload),
    onSuccess: () => {
      invalidate()
      toast.success('Pagamento resolvido', 'O status foi atualizado.')
    },
  })
}
