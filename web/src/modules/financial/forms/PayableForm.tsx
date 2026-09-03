import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button, ButtonLink, Card, CardContent, Form, Section, TextField } from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { SupplierField } from '../components/SupplierField'
import { CategoryField } from '../components/CategoryField'
import type { PayablePayload } from '../services/payables.service'
import { payableSchema, type PayableFormValues } from '../schemas/payable.schema'

interface PayableFormProps {
  submitting: boolean
  onSubmit: (payload: PayablePayload) => Promise<unknown>
}

export function PayableForm({ submitting, onSubmit }: PayableFormProps) {
  const form = useForm<PayableFormValues>({
    resolver: zodResolver(payableSchema),
    defaultValues: {
      description: '',
      value: '',
      due_date: '',
      issue_date: null,
      supplier_id: null,
      category_id: null,
    },
  })

  const handleSubmit = async (values: PayableFormValues) => {
    const payload: PayablePayload = {
      description: values.description,
      value: Number(values.value),
      due_date: values.due_date,
      issue_date: values.issue_date || null,
      supplier_id: values.supplier_id || null,
      category_id: values.category_id || null,
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
              Criar conta
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
