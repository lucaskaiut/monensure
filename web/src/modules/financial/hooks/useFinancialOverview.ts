import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import { overviewService } from '../services/overview.service'

export function useFinancialSummary(enabled = true) {
  return useQuery({
    queryKey: queryKeys.financial.summary(),
    queryFn: () => overviewService.summary(),
    enabled,
  })
}

export function useCashflowProjection() {
  return useQuery({
    queryKey: queryKeys.financial.cashflow(),
    queryFn: () => overviewService.cashflow(),
  })
}

export function useOverduePayables(params: {
  supplier_id?: string
  category_id?: string
  page?: number
  per_page?: number
}) {
  return useQuery({
    queryKey: queryKeys.financial.overdue({
      supplier_id: params.supplier_id ? Number(params.supplier_id) : undefined,
      category_id: params.category_id ? Number(params.category_id) : undefined,
    }),
    queryFn: () => overviewService.overdue(params),
    placeholderData: keepPreviousData,
  })
}
