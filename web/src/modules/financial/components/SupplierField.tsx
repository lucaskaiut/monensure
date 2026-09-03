import { useCallback } from 'react'
import { SearchSelectField, type SearchSelectOption } from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useCreateSupplier } from '../hooks/useSuppliers'
import { suppliersService } from '../services/suppliers.service'

export function SupplierField({
  name = 'supplier_id',
  required = false,
  allowCreate = true,
}: {
  name?: string
  required?: boolean
  /** Permite criar fornecedor inline quando a busca não retorna resultados. */
  allowCreate?: boolean
}) {
  const { can } = usePermissions()
  const createSupplier = useCreateSupplier()

  const loadOptions = useCallback(async (search: string): Promise<SearchSelectOption[]> => {
    const result = await suppliersService.list({ search: search || undefined, per_page: 20 })

    return result.data.map((supplier) => ({ value: supplier.id, label: supplier.name }))
  }, [])

  const resolveLabel = useCallback(async (id: string): Promise<SearchSelectOption | null> => {
    try {
      const supplier = await suppliersService.get(id)

      return { value: supplier.id, label: supplier.name }
    } catch {
      return null
    }
  }, [])

  const onCreateOption = useCallback(
    async (search: string): Promise<SearchSelectOption | null> => {
      const supplier = await createSupplier.mutateAsync({ name: search.trim() })

      return { value: supplier.id, label: supplier.name }
    },
    [createSupplier],
  )

  return (
    <SearchSelectField
      name={name}
      label="Fornecedor"
      loadOptions={loadOptions}
      resolveLabel={resolveLabel}
      onCreateOption={allowCreate && can(Permission.SUPPLIER_CREATE) ? onCreateOption : undefined}
      placeholder="Buscar fornecedor..."
      required={required}
    />
  )
}
