import { useCallback } from 'react'
import { SearchSelectField, type SearchSelectOption } from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useCreateCategory } from '../hooks/useCategories'
import { categoriesService } from '../services/categories.service'

export function CategoryField({
  name = 'category_id',
  required = false,
  excludeId,
  allowCreate = true,
}: {
  name?: string
  required?: boolean
  excludeId?: string
  /** Permite criar categoria inline quando a busca não retorna resultados. */
  allowCreate?: boolean
}) {
  const { can } = usePermissions()
  const createCategory = useCreateCategory()

  const loadOptions = useCallback(
    async (search: string): Promise<SearchSelectOption[]> => {
      const result = await categoriesService.list({ search: search || undefined, per_page: 20 })

      return result.data
        .filter((category) => category.id !== excludeId)
        .map((category) => ({
          value: category.id,
          label: category.name,
          parent_id: category.parent_id,
        }))
    },
    [excludeId],
  )

  const resolveLabel = useCallback(async (id: string): Promise<SearchSelectOption | null> => {
    try {
      const category = await categoriesService.get(id)

      return { value: category.id, label: category.name, parent_id: category.parent_id }
    } catch {
      return null
    }
  }, [])

  const onCreateOption = useCallback(
    async (search: string): Promise<SearchSelectOption | null> => {
      const category = await createCategory.mutateAsync({ name: search.trim(), parent_id: null })

      return { value: category.id, label: category.name, parent_id: category.parent_id }
    },
    [createCategory],
  )

  return (
    <SearchSelectField
      name={name}
      label="Categoria"
      loadOptions={loadOptions}
      resolveLabel={resolveLabel}
      onCreateOption={allowCreate && can(Permission.CATEGORY_CREATE) ? onCreateOption : undefined}
      placeholder="Buscar categoria..."
      required={required}
    />
  )
}
