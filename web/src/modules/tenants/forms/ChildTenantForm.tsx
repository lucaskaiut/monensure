import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  Section,
  SelectField,
  SwitchField,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { onlyDigits } from '@/shared/utils/document'
import { usePlansQuery } from '@/modules/billing/hooks/useBilling'
import type {
  CreateChildTenantPayload,
  UpdateChildTenantPayload,
} from '../services/tenants.service'
import {
  createChildTenantSchema,
  updateChildTenantSchema,
  type CreateChildTenantFormValues,
  type UpdateChildTenantFormValues,
} from '../schemas/tenant.schema'

type CreateProps = {
  mode: 'create'
  submitting: boolean
  onSubmit: (payload: CreateChildTenantPayload) => Promise<unknown>
  defaultValues?: Partial<CreateChildTenantFormValues>
}

type EditProps = {
  mode: 'edit'
  submitting: boolean
  onSubmit: (payload: UpdateChildTenantPayload) => Promise<unknown>
  defaultValues?: Partial<UpdateChildTenantFormValues>
}

type ChildTenantFormProps = CreateProps | EditProps

function toDateInput(value: string | null | undefined): string {
  if (!value) return ''
  return value.slice(0, 10)
}

export function ChildTenantForm(props: ChildTenantFormProps) {
  const { mode, submitting } = props
  const { data: plans } = usePlansQuery()

  if (mode === 'create') {
    return (
      <CreateForm
        submitting={submitting}
        plans={plans ?? []}
        defaultValues={props.defaultValues}
        onSubmit={props.onSubmit}
      />
    )
  }

  return (
    <EditForm
      submitting={submitting}
      plans={plans ?? []}
      defaultValues={props.defaultValues}
      onSubmit={props.onSubmit}
    />
  )
}

function CreateForm({
  submitting,
  plans,
  defaultValues,
  onSubmit,
}: {
  submitting: boolean
  plans: Array<{ id: string; name: string }>
  defaultValues?: Partial<CreateChildTenantFormValues>
  onSubmit: (payload: CreateChildTenantPayload) => Promise<unknown>
}) {
  const form = useForm<CreateChildTenantFormValues>({
    resolver: zodResolver(createChildTenantSchema),
    defaultValues: {
      tenant: { name: '', document: '', email: '', phone: '', domain: '' },
      user: { name: '', email: '', password: '' },
      plan_id: null,
      is_complimentary: false,
      complimentary_ends_at: null,
      ...defaultValues,
    },
  })

  const isComplimentary = useWatch({ control: form.control, name: 'is_complimentary' })

  const handleSubmit = async (values: CreateChildTenantFormValues) => {
    const payload: CreateChildTenantPayload = {
      tenant: {
        name: values.tenant.name,
        document: onlyDigits(values.tenant.document),
        email: values.tenant.email,
        phone: values.tenant.phone,
        domain: values.tenant.domain,
      },
      user: {
        name: values.user.name,
        email: values.user.email,
        password: values.user.password,
      },
      plan_id: values.plan_id || null,
      is_complimentary: values.is_complimentary,
      complimentary_ends_at: values.is_complimentary
        ? values.complimentary_ends_at || null
        : null,
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
          <TenantFields />
          <AdminFields />
          <AccessFields plans={plans} isComplimentary={Boolean(isComplimentary)} />
          <FormActions submitting={submitting} submitLabel="Criar empresa" />
        </Form>
      </CardContent>
    </Card>
  )
}

function EditForm({
  submitting,
  plans,
  defaultValues,
  onSubmit,
}: {
  submitting: boolean
  plans: Array<{ id: string; name: string }>
  defaultValues?: Partial<UpdateChildTenantFormValues>
  onSubmit: (payload: UpdateChildTenantPayload) => Promise<unknown>
}) {
  const form = useForm<UpdateChildTenantFormValues>({
    resolver: zodResolver(updateChildTenantSchema),
    defaultValues: {
      tenant: { name: '', document: '', email: '', phone: '', domain: '' },
      plan_id: null,
      is_complimentary: false,
      complimentary_ends_at: null,
      ...defaultValues,
      complimentary_ends_at: toDateInput(defaultValues?.complimentary_ends_at) || null,
    },
  })

  const isComplimentary = useWatch({ control: form.control, name: 'is_complimentary' })

  const handleSubmit = async (values: UpdateChildTenantFormValues) => {
    const payload: UpdateChildTenantPayload = {
      tenant: {
        name: values.tenant.name,
        document: onlyDigits(values.tenant.document),
        email: values.tenant.email,
        phone: values.tenant.phone,
        domain: values.tenant.domain,
      },
      plan_id: values.plan_id || null,
      is_complimentary: values.is_complimentary,
      complimentary_ends_at: values.is_complimentary
        ? values.complimentary_ends_at || null
        : null,
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
          <TenantFields />
          <AccessFields plans={plans} isComplimentary={Boolean(isComplimentary)} />
          <FormActions submitting={submitting} submitLabel="Salvar alterações" />
        </Form>
      </CardContent>
    </Card>
  )
}

function TenantFields() {
  return (
    <Section title="Dados da empresa">
      <div className="grid gap-4 sm:grid-cols-2">
        <TextField name="tenant.name" label="Nome" required className="sm:col-span-2" />
        <TextField name="tenant.document" label="CPF / CNPJ" required placeholder="Somente números" />
        <TextField name="tenant.email" label="E-mail" type="email" required />
        <TextField name="tenant.phone" label="Telefone" required placeholder="(41) 99999-9999" />
        <TextField name="tenant.domain" label="Domínio" required placeholder="empresa.com.br" />
      </div>
    </Section>
  )
}

function AdminFields() {
  return (
    <Section title="Administrador" description="Credenciais do usuário que administrará esta empresa.">
      <div className="grid gap-4 sm:grid-cols-2">
        <TextField name="user.name" label="Nome" required className="sm:col-span-2" />
        <TextField name="user.email" label="E-mail" type="email" required />
        <TextField
          name="user.password"
          label="Senha"
          type="password"
          autoComplete="new-password"
          required
          hint="Mínimo de 8 caracteres"
        />
      </div>
    </Section>
  )
}

function AccessFields({
  plans,
  isComplimentary,
}: {
  plans: Array<{ id: string; name: string }>
  isComplimentary: boolean
}) {
  return (
    <Section
      title="Acesso"
      description="Defina o plano e, se for parceria, libere o acesso sem cobrança."
    >
      <div className="space-y-4">
        <SelectField
          name="plan_id"
          label="Plano"
          options={plans.map((plan) => ({ value: plan.id, label: plan.name }))}
          placeholder="Sem plano"
          required={isComplimentary}
        />
        <SwitchField
          name="is_complimentary"
          label="Acesso cortesia (parceria)"
          hint="Libera o uso do sistema sem gerar cobranças enquanto estiver ativo."
        />
        {isComplimentary && (
          <TextField
            name="complimentary_ends_at"
            label="Término da cortesia"
            type="date"
            hint="Deixe vazio para acesso indeterminado."
          />
        )}
      </div>
    </Section>
  )
}

function FormActions({ submitting, submitLabel }: { submitting: boolean; submitLabel: string }) {
  return (
    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
      <ButtonLink to="/tenants" variant="secondary">
        Cancelar
      </ButtonLink>
      <Button type="submit" loading={submitting}>
        {submitLabel}
      </Button>
    </div>
  )
}
