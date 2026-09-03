import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button, ButtonLink, Card, CardContent, Form, Section, SelectField, SwitchField, TextField } from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { SupplierField } from '../components/SupplierField'
import { CategoryField } from '../components/CategoryField'
import type { RecurrencePayload } from '../services/recurrences.service'
import { FREQUENCIES, recurrenceSchema, type RecurrenceFormValues } from '../schemas/recurrence.schema'

interface RecurrenceFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<RecurrenceFormValues>
  submitting: boolean
  onSubmit: (payload: RecurrencePayload) => Promise<unknown>
}

export function RecurrenceForm({ mode, defaultValues, submitting, onSubmit }: RecurrenceFormProps) {
  const form = useForm<RecurrenceFormValues>({
    resolver: zodResolver(recurrenceSchema),
    defaultValues: {
      description: '',
      default_value: '',
      due_day: '10',
      frequency: 'mensal',
      supplier_id: null,
      category_id: null,
      active: true,
      generate_automatically: true,
      ...defaultValues,
    },
  })

  const handleSubmit = async (values: RecurrenceFormValues) => {
    const payload: RecurrencePayload = {
      description: values.description,
      default_value: Number(values.default_value),
      due_day: Number(values.due_day),
      frequency: values.frequency,
      supplier_id: values.supplier_id || null,
      category_id: values.category_id || null,
      active: values.active,
      generate_automatically: values.generate_automatically,
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
          <Section title="Dados da recorrência">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="description" label="Descrição" required className="sm:col-span-2" />
              <TextField name="default_value" label="Valor" type="number" step="0.01" required />
              <TextField name="due_day" label="Dia do vencimento" type="number" min={1} max={31} required />
              <SelectField
                name="frequency"
                label="Frequência"
                options={FREQUENCIES.map((frequency) => ({
                  value: frequency.value,
                  label: frequency.label,
                }))}
                required
              />
              <SupplierField />
              <CategoryField />
            </div>
          </Section>

          <Section title="Automação">
            <SwitchField name="active" label="Ativa" />
            <SwitchField
              name="generate_automatically"
              label="Gerar lançamentos automaticamente"
              hint="O sistema gera os próximos 12 meses de lançamentos diariamente."
            />
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/financial/recurrences" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Criar recorrência' : 'Salvar alterações'}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
