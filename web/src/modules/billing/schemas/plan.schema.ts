import { z } from 'zod'

export const planSchema = z.object({
  name: z.string().min(1, 'Informe o nome do plano').max(255),
  description: z.string().max(2000).optional().or(z.literal('')),
  // HTML number inputs enviam string; o genérico alinha o input type com o valor do form.
  price: z.coerce.number<number>().min(0.01, 'Informe um valor válido'),
  recurrence_value: z.coerce.number<number>().int().min(1, 'Informe a recorrência'),
  recurrence_unit: z.enum(['days', 'weeks', 'months', 'years']),
  free_trial_days: z.coerce.number<number>().int().min(0).max(365),
  active: z.boolean(),
})

export type PlanFormValues = z.output<typeof planSchema>
export type PlanFormInput = z.input<typeof planSchema>

export const RECURRENCE_UNITS = [
  { value: 'days', label: 'Dias' },
  { value: 'weeks', label: 'Semanas' },
  { value: 'months', label: 'Meses' },
  { value: 'years', label: 'Anos' },
] as const
