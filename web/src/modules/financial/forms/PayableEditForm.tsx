import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  RadioGroup,
  Section,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { SupplierField } from '../components/SupplierField'
import { CategoryField } from '../components/CategoryField'
import type { UpdatePayablePayload } from '../services/payables.service'
import { payableSchema, type PayableFormValues } from '../schemas/payable.schema'
import type { Payable } from '@/shared/types/models'

type Scope = 'this' | 'this_and_next' | 'all'

export function PayableEditForm({
  payable,
  submitting,
  onSubmit,
}: {
  payable: Payable
  submitting: boolean
  onSubmit: (payload: UpdatePayablePayload) => Promise<unknown>
}) {
  const [scope, setScope] = useState<Scope>('this')

  const form = useForm<PayableFormValues>({
    resolver: zodResolver(payableSchema),
    defaultValues: {
      description: payable.description,
      value: payable.value,
      due_date: payable.due_date ?? '',
      issue_date: payable.issue_date ?? null,
      supplier_id: payable.supplier?.id ?? null,
      category_id: payable.category?.id ?? null,
    },
  })

  const handleSubmit = async (values: PayableFormValues) => {
    const payload: UpdatePayablePayload = {
      description: values.description,
      value: Number(values.value),
      due_date: values.due_date,
      issue_date: values.issue_date || null,
      supplier_id: values.supplier_id || null,
      category_id: values.category_id || null,
    }

    if (payable.is_installment) {
      payload.scope = scope
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
          {payable.is_installment && (
            <Section title="Escopo da edição">
              <RadioGroup
                name="scope"
                value={scope}
                onChange={setScope}
                options={[
                  { value: 'this', label: 'Apenas esta parcela' },
                  { value: 'this_and_next', label: 'Esta e as próximas' },
                  { value: 'all', label: 'Todas do grupo' },
                ]}
                aria-label="Escopo da edição"
              />
            </Section>
          )}

          <Section title="Dados da conta">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="description" label="Descrição" required className="sm:col-span-2" />
              <TextField name="value" label="Valor" type="number" step="0.01" required />
              <TextField name="due_date" label="Vencimento" type="date" required />
              <TextField name="issue_date" label="Data de lançamento" type="date" />
              <SupplierField />
              <CategoryField />
            </div>
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/financial/payables" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              Salvar alterações
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
