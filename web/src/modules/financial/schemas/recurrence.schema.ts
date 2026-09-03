import { z } from 'zod'
import type { RecurrenceFrequency } from '@/shared/types/models'

export const FREQUENCIES: Array<{ value: RecurrenceFrequency; label: string }> = [
  { value: 'mensal', label: 'Mensal' },
  { value: 'bimestral', label: 'Bimestral' },
  { value: 'trimestral', label: 'Trimestral' },
  { value: 'semestral', label: 'Semestral' },
  { value: 'anual', label: 'Anual' },
]

const positiveMoney = z
  .string()
  .refine((value) => value !== '' && Number(value) > 0, 'Informe um valor maior que zero')

const dayOfMonth = z
  .string()
  .refine(
    (value) => /^\d+$/.test(value) && Number(value) >= 1 && Number(value) <= 31,
    'Informe um dia entre 1 e 31',
  )

export const recurrenceSchema = z.object({
  description: z.string().min(1, 'Informe a descrição'),
  default_value: positiveMoney,
  due_day: dayOfMonth,
  frequency: z.enum(['mensal', 'bimestral', 'trimestral', 'semestral', 'anual']),
  supplier_id: z.string().nullable(),
  category_id: z.string().nullable(),
  active: z.boolean(),
  generate_automatically: z.boolean(),
})

export type RecurrenceFormValues = z.infer<typeof recurrenceSchema>
