import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { queryKeys } from '@/shared/constants/query-keys'
import type { ListParams } from '@/shared/types/api'
import { toast } from '@/shared/stores/toast.store'
import { categoriesService, type CategoryPayload } from '../services/categories.service'

export function useCategoriesQuery(params: ListParams) {
  return useQuery({
    queryKey: queryKeys.financial.categories.list(params),
    queryFn: () => categoriesService.list(params),
    placeholderData: keepPreviousData,
  })
}

export function useAllCategories() {
  return useQuery({
    queryKey: queryKeys.financial.categories.all,
    queryFn: () => categoriesService.all(),
  })
}

export function useCategoriesTree() {
  return useQuery({
    queryKey: queryKeys.financial.categories.tree(),
    queryFn: () => categoriesService.tree(),
  })
}

export function useCategoryQuery(id: string | undefined) {
  return useQuery({
    queryKey: queryKeys.financial.categories.detail(id ?? ''),
    queryFn: () => categoriesService.get(id!),
    enabled: !!id,
  })
}

export function useCreateCategory() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: CategoryPayload) => categoriesService.create(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.financial.categories.all })
      toast.success('Categoria criada', 'A categoria foi cadastrada com sucesso.')
    },
  })
}

export function useUpdateCategory(id: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: CategoryPayload) => categoriesService.update(id, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.financial.categories.all })
      toast.success('Categoria atualizada', 'As alterações foram salvas.')
    },
  })
}

export function useDeleteCategory() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => categoriesService.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.financial.categories.all })
      toast.success('Categoria removida', 'A categoria foi excluída com sucesso.')
    },
  })
}
