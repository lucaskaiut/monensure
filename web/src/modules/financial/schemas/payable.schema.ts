import { z } from 'zod'

const positiveMoney = z
  .string()
  .refine((value) => value !== '' && Number(value) > 0, 'Informe um valor maior que zero')

const positiveInteger = (message: string) =>
  z.string().refine((value) => /^\d+$/.test(value) && Number(value) >= 1, message)

export const payableSchema = z.object({
  description: z.string().min(1, 'Informe a descrição'),
  value: positiveMoney,
  due_date: z.string().min(1, 'Informe o vencimento'),
  issue_date: z.string().nullable(),
  supplier_id: z.string().nullable(),
  category_id: z.string().nullable(),
})

export const installmentPlanSchema = z.object({
  description: z.string().min(1, 'Informe a descrição'),
  value: positiveMoney,
  first_due_date: z.string().min(1, 'Informe o primeiro vencimento'),
  first_installment_number: positiveInteger('Informe o número da primeira parcela'),
  total_installments: positiveInteger('Informe a quantidade de parcelas'),
  supplier_id: z.string().nullable(),
  category_id: z.string().nullable(),
})

export type PayableFormValues = z.infer<typeof payableSchema>
export type InstallmentPlanFormValues = z.infer<typeof installmentPlanSchema>
