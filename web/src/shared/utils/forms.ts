import type { FieldValues, Path, UseFormReturn } from 'react-hook-form'
import type { ApiError } from '@/shared/api/errors'

/**
 * Aplica erros de validação (422) da API nos campos do formulário.
 */
export function applyApiErrorsToForm<
  TFieldValues extends FieldValues,
  TTransformedValues extends FieldValues = TFieldValues,
>(form: UseFormReturn<TFieldValues, unknown, TTransformedValues>, error: ApiError): void {
  for (const [field, messages] of Object.entries(error.fieldErrors)) {
    form.setError(field as Path<TFieldValues>, {
      type: 'server',
      message: messages[0],
    })
  }
}
