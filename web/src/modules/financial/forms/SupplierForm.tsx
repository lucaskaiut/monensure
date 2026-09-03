import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Button, ButtonLink, Card, CardContent, Form, Section, TextField, TextareaField } from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { onlyDigits } from '@/shared/utils/document'
import type { SupplierPayload } from '../services/suppliers.service'
import { supplierSchema, type SupplierFormValues } from '../schemas/supplier.schema'

interface SupplierFormProps {
  mode: 'create' | 'edit'
  defaultValues?: Partial<SupplierFormValues>
  submitting: boolean
  onSubmit: (payload: SupplierPayload) => Promise<unknown>
}

export function SupplierForm({ mode, defaultValues, submitting, onSubmit }: SupplierFormProps) {
  const form = useForm<SupplierFormValues>({
    resolver: zodResolver(supplierSchema),
    defaultValues: {
      name: '',
      document: '',
      phone: '',
      email: '',
      observations: '',
      ...defaultValues,
    },
  })

  const handleSubmit = async (values: SupplierFormValues) => {
    const payload: SupplierPayload = {
      name: values.name,
      document: values.document ? onlyDigits(values.document) : null,
      phone: values.phone || null,
      email: values.email || null,
      observations: values.observations || null,
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
          <Section title="Dados do fornecedor">
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField name="name" label="Nome" required className="sm:col-span-2" />
              <TextField name="document" label="CPF / CNPJ" placeholder="Somente números" />
              <TextField name="phone" label="Telefone" placeholder="(41) 99999-9999" />
              <TextField name="email" label="E-mail" type="email" />
              <TextareaField name="observations" label="Observações" className="sm:col-span-2" />
            </div>
          </Section>

          <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <ButtonLink to="/financial/suppliers" variant="secondary">
              Cancelar
            </ButtonLink>
            <Button type="submit" loading={submitting}>
              {mode === 'create' ? 'Criar fornecedor' : 'Salvar alterações'}
            </Button>
          </div>
        </Form>
      </CardContent>
    </Card>
  )
}
