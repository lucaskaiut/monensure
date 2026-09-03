import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button, ButtonLink, Card, CardContent, Form, Section, TextField } from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { SupplierField } from '../components/SupplierField'
import { CategoryField } from '../components/CategoryField'
import type { InstallmentPlanPayload } from '../services/payables.service'
import { installmentPlanSchema, type InstallmentPlanFormValues } from '../schemas/payable.schema'

interface InstallmentPlanFormProps {
  submitting: boolean
  onSubmit: (payload: InstallmentPlanPayload) => Promise<unknown>
}

export function InstallmentPlanForm({ submitting, onSubmit }: InstallmentPlanFormProps) {
  const form = useForm<InstallmentPlanFormValues>({
    resolver: zodResolver(installmentPlanSchema),
    defaultValues: {
      description: '',
      value: '',
      first_due_date: '',
      first_installment_number: '',
      total_installments: '',
      supplier_id: null,
      category_id: null,
    },
  })

  const handleSubmit = async (values: InstallmentPlanFormValues) => {
    const payload: InstallmentPlanPayload = {
      description: values.description,
      value: Number(values.value),
      first_due_date: values.first_due_date,
      first_installment_number: Number(values.first_installment_number),
      total_installments: Number(values.total_installments),
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
          <Section title="Dados do parcelamento">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="description" label="Descrição" required className="sm:col-span-2" />
              <TextField name="value" label="Valor da parcela" type="number" step="0.01" required />
              <TextField name="first_due_date" label="Primeiro vencimento" type="date" required />
              <TextField
                name="first_installment_number"
                label="Número da primeira parcela"
                type="number"
                min={1}
                required
                hint="Ex.: 12 se já existirem 11 parcelas anteriores"
              />
              <TextField
                name="total_installments"
                label="Quantidade de parcelas"
                type="number"
                min={1}
                required
              />
              <SupplierField />
              <CategoryField />
            </div>
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/financial/payables" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              Gerar parcelas
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
