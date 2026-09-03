import { z } from 'zod'
import { isValidCpfOrCnpj } from '@/shared/utils/document'

export const supplierSchema = z.object({
  name: z.string().min(1, 'Informe o nome'),
  document: z.string().refine((value) => !value || isValidCpfOrCnpj(value), 'Informe um CPF ou CNPJ válido'),
  phone: z.string(),
  email: z
    .string()
    .refine((value) => !value || z.string().email().safeParse(value).success, 'Informe um e-mail válido'),
  observations: z.string(),
})

export type SupplierFormValues = z.infer<typeof supplierSchema>
