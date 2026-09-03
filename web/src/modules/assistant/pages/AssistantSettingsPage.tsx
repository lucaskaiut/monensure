import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { Sparkles } from 'lucide-react'
import {
  Button,
  ButtonLink,
  Card,
  CardContent,
  Form,
  Loading,
  Page,
  PageContent,
  PageHeader,
  Section,
  SwitchField,
  TextareaField,
  TextField,
} from '@/shared/design-system'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import {
  assistantSettingsSchema,
  type AssistantSettingsFormInput,
  type AssistantSettingsFormValues,
} from '../schemas/assistant-settings.schema'
import {
  useAssistantSettingsQuery,
  useTestAssistantConnection,
  useUpdateAssistantSettings,
} from '../hooks/useAssistantSettings'

function emptyToNull(value: string | null | undefined): string | null {
  const trimmed = value?.trim() ?? ''

  return trimmed === '' ? null : trimmed
}

export default function AssistantSettingsPage() {
  const settingsQuery = useAssistantSettingsQuery()
  const updateSettings = useUpdateAssistantSettings()
  const testConnection = useTestAssistantConnection()

  const form = useForm<AssistantSettingsFormInput, unknown, AssistantSettingsFormValues>({
    resolver: zodResolver(assistantSettingsSchema),
    defaultValues: {
      enabled: true,
      endpoint: null,
      api_key: '',
      model: null,
      temperature: null,
      max_tokens: null,
      additional_prompt: null,
    },
  })

  useEffect(() => {
    if (!settingsQuery.data) return

    const settings = settingsQuery.data

    form.reset({
      enabled: settings.enabled,
      endpoint: settings.endpoint,
      api_key: '',
      model: settings.model,
      temperature: settings.temperature,
      max_tokens: settings.max_tokens,
      additional_prompt: settings.additional_prompt,
    })
  }, [settingsQuery.data, form])

  const handleSubmit = async (values: AssistantSettingsFormValues) => {
    const payload = {
      enabled: values.enabled,
      endpoint: emptyToNull(values.endpoint),
      model: emptyToNull(values.model),
      temperature: values.temperature,
      max_tokens: values.max_tokens,
      additional_prompt: emptyToNull(values.additional_prompt),
      ...(values.api_key?.trim() ? { api_key: values.api_key.trim() } : {}),
    }

    try {
      await updateSettings.mutateAsync(payload)
      form.setValue('api_key', '')
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  const handleTestConnection = async () => {
    const values = form.getValues()
    const temperature =
      typeof values.temperature === 'number'
        ? values.temperature
        : values.temperature === '' || values.temperature == null
          ? null
          : Number(values.temperature)
    const maxTokens =
      typeof values.max_tokens === 'number'
        ? values.max_tokens
        : values.max_tokens === '' || values.max_tokens == null
          ? null
          : Number(values.max_tokens)

    const payload = {
      endpoint: emptyToNull(values.endpoint) ?? settingsQuery.data?.effective.endpoint,
      model: emptyToNull(values.model) ?? settingsQuery.data?.effective.model,
      temperature:
        temperature != null && Number.isFinite(temperature)
          ? temperature
          : (settingsQuery.data?.effective.temperature ?? null),
      max_tokens:
        maxTokens != null && Number.isFinite(maxTokens)
          ? maxTokens
          : (settingsQuery.data?.effective.max_tokens ?? null),
      ...(values.api_key?.trim() ? { api_key: values.api_key.trim() } : {}),
    }

    await testConnection.mutateAsync(payload)
  }

  if (settingsQuery.isPending) {
    return (
      <Page>
        <Loading label="Carregando configurações..." />
      </Page>
    )
  }

  const settings = settingsQuery.data
  const defaults = settings?.defaults
  const effective = settings?.effective

  return (
    <Page>
      <PageHeader
        title="Assistente de IA"
        description="Configure o provedor, modelo e instruções adicionais para esta empresa."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Assistente de IA' },
        ]}
        actions={
          <ButtonLink to="/assistant" variant="secondary">
            <Sparkles className="size-4" />
            Abrir chat
          </ButtonLink>
        }
      />

      <PageContent>
        <Card>
          <CardContent>
            <Form form={form} onSubmit={handleSubmit} className="space-y-8">
              <Section
                title="Status"
                description="Desative para impedir o uso do assistente nesta empresa."
              >
                <SwitchField name="enabled" label="Assistente habilitado" />
              </Section>

              <Section
                title="Conexão com o provedor"
                description="Deixe os campos em branco para usar os valores padrão do servidor (.env)."
              >
                <div className="grid gap-4 sm:grid-cols-2">
                  <TextField
                    name="endpoint"
                    label="URL da API"
                    placeholder={defaults?.endpoint || 'https://api.openai.com/v1'}
                    hint={
                      effective?.endpoint
                        ? `Em uso: ${effective.endpoint}`
                        : 'Usará o padrão do servidor quando vazio'
                    }
                    className="sm:col-span-2"
                  />
                  <TextField
                    name="model"
                    label="Modelo"
                    placeholder={defaults?.model || 'gpt-4o-mini'}
                    hint={effective?.model ? `Em uso: ${effective.model}` : undefined}
                  />
                  <TextField
                    name="api_key"
                    label="Chave da API"
                    type="password"
                    autoComplete="off"
                    placeholder={
                      settings?.has_api_key
                        ? 'Chave configurada — deixe em branco para manter'
                        : defaults?.has_api_key
                          ? 'Usará a chave padrão do servidor'
                          : 'Informe a chave da API'
                    }
                    hint={
                      effective?.has_api_key
                        ? 'Há uma chave efetiva configurada para esta empresa'
                        : 'Nenhuma chave efetiva — configure aqui ou no servidor'
                    }
                  />
                  <TextField
                    name="temperature"
                    label="Temperatura"
                    type="number"
                    step="0.1"
                    min={0}
                    max={2}
                    placeholder={String(defaults?.temperature ?? 0.2)}
                  />
                  <TextField
                    name="max_tokens"
                    label="Máximo de tokens"
                    type="number"
                    min={1}
                    placeholder={defaults?.max_tokens ? String(defaults.max_tokens) : 'Sem limite'}
                  />
                </div>

                <div className="flex flex-wrap gap-2 pt-2">
                  <Button
                    type="button"
                    variant="secondary"
                    loading={testConnection.isPending}
                    onClick={() => void handleTestConnection()}
                  >
                    Testar conexão
                  </Button>
                </div>
              </Section>

              <Section
                title="Prompt adicional"
                description="Instruções extras anexadas ao prompt base do agente para esta empresa."
              >
                <TextareaField
                  name="additional_prompt"
                  label="Prompt adicional"
                  rows={6}
                  placeholder="Ex.: Sempre mencione o centro de custo ao criar contas. Priorize fornecedores cadastrados."
                />
              </Section>

              <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <ButtonLink to="/dashboard" variant="secondary">
                  Cancelar
                </ButtonLink>
                <Button type="submit" loading={updateSettings.isPending}>
                  Salvar configurações
                </Button>
              </div>
            </Form>
          </CardContent>
        </Card>
      </PageContent>
    </Page>
  )
}
