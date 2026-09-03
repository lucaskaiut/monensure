import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import type { ListParams } from '@/shared/types/api'
import { toast } from '@/shared/stores/toast.store'
import { suppliersService, type SupplierPayload } from '../services/suppliers.service'

export function useSuppliersQuery(params: ListParams) {
  return useQuery({
    queryKey: queryKeys.financial.suppliers.list(params),
    queryFn: () => suppliersService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useAllSuppliers() {
  return useQuery({
    queryKey: queryKeys.financial.suppliers.all,
    queryFn: () => suppliersService.all(),
  })
}

export function useSupplierQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.financial.suppliers.detail(id ?? ''),
    queryFn: () => suppliersService.get(id!),
    enabled: !!id,
  })
}

export function useCreateSupplier() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: SupplierPayload) => suppliersService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.financial.suppliers.all })
      toast.success('Fornecedor criado', 'O fornecedor foi cadastrado com sucesso.')
    },
  })
}

export function useUpdateSupplier(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: SupplierPayload) => suppliersService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.financial.suppliers.all })
      toast.success('Fornecedor atualizado', 'As alterações foram salvas.')
    },
  })
}

export function useDeleteSupplier() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => suppliersService.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.financial.suppliers.all })
      toast.success('Fornecedor removido', 'O fornecedor foi excluído com sucesso.')
    },
  })
}
