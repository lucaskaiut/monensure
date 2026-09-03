import { z } from 'zod'

export const categorySchema = z.object({
  name: z.string().min(1, 'Informe o nome'),
  parent_id: z.string().nullable(),
})

export type CategoryFormValues = z.infer<typeof categorySchema>
