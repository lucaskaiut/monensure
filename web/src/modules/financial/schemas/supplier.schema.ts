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
  pix_key_type: z.string(),
  pix_key: z.string(),
}).refine((values) => !values.pix_key || !!values.pix_key_type, {
  message: 'Informe o tipo da chave',
  path: ['pix_key_type'],
}).refine((values) => !values.pix_key_type || !!values.pix_key.trim(), {
  message: 'Informe a chave PIX',
  path: ['pix_key'],
})

export type SupplierFormValues = z.infer<typeof supplierSchema>
