import { z } from 'zod'

/** Aceita number do form ou string vazia do input HTML; normaliza para number | null. */
const nullableNumber = (min: number, max: number) =>
  z
    .union([z.number(), z.string(), z.null(), z.undefined()])
    .transform((value) => {
      if (value === '' || value === null || value === undefined) {
        return null
      }

      const parsed = typeof value === 'number' ? value : Number(value)
      return Number.isFinite(parsed) ? parsed : null
    })
    .pipe(z.number().min(min).max(max).nullable())

export const assistantSettingsSchema = z.object({
  enabled: z.boolean(),
  endpoint: z.string().nullable(),
  api_key: z.string().optional(),
  model: z.string().nullable(),
  temperature: nullableNumber(0, 2),
  max_tokens: nullableNumber(1, 128000),
  additional_prompt: z.string().nullable(),
})

export type AssistantSettingsFormValues = z.output<typeof assistantSettingsSchema>
export type AssistantSettingsFormInput = z.input<typeof assistantSettingsSchema>

export type AssistantSettingsPayload = Omit<AssistantSettingsFormValues, 'api_key'> & {
  api_key?: string
}

export type AssistantConnectionTestPayload = {
  endpoint?: string | null
  api_key?: string
  model?: string | null
  temperature?: number | null
  max_tokens?: number | null
}
