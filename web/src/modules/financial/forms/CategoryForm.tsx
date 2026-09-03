import { useCallback } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  SearchSelectField,
  Section,
  TextField,
  type SearchSelectOption,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import type { CategoryPayload } from '../services/categories.service'
import { categoriesService } from '../services/categories.service'
import { categorySchema, type CategoryFormValues } from '../schemas/category.schema'

interface CategoryFormProps {
  mode: 'create' | 'edit'
  excludeId?: string
  defaultValues?: Partial<CategoryFormValues>
  submitting: boolean
  onSubmit: (payload: CategoryPayload) => Promise<unknown>
}

export function CategoryForm({ mode, excludeId, defaultValues, submitting, onSubmit }: CategoryFormProps) {
  const form = useForm<CategoryFormValues>({
    resolver: zodResolver(categorySchema),
    defaultValues: {
      name: '',
      parent_id: null,
      ...defaultValues,
    },
  })

  const loadParentOptions = useCallback(
    async (search: string): Promise<SearchSelectOption[]> => {
      const result = await categoriesService.list({ search: search || undefined, per_page: 20 })

      return result.data
        .filter((category) => category.id !== excludeId && category.parent_id === null)
        .map((category) => ({ value: category.id, label: category.name }))
    },
    [excludeId],
  )

  const resolveParentLabel = useCallback(async (id: string): Promise<SearchSelectOption | null> => {
    try {
      const category = await categoriesService.get(id)

      return { value: category.id, label: category.name }
    } catch {
      return null
    }
  }, [])

  const handleSubmit = async (values: CategoryFormValues) => {
    const payload: CategoryPayload = {
      name: values.name,
      parent_id: values.parent_id || null,
    }

    try {
      await onSubmit(payload)
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  return (
    <Card>
      <CardContent>
        <Form form={form} onSubmit={handleSubmit} className="space-y-8">
          <Section title="Dados da categoria">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="name" label="Nome" required />
              <SearchSelectField
                name="parent_id"
                label="Categoria pai"
                loadOptions={loadParentOptions}
                resolveLabel={resolveParentLabel}
                placeholder="Sem categoria pai (raiz)"
              />
            </div>
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/financial/categories" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Criar categoria' : 'Salvar alterações'}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
