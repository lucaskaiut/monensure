import type { PixKeyType } from '@/shared/types/models'

export const PIX_KEY_TYPE_OPTIONS: Array<{ value: PixKeyType; label: string }> = [
  { value: 'CPF', label: 'CPF' },
  { value: 'CNPJ', label: 'CNPJ' },
  { value: 'EMAIL', label: 'E-mail' },
  { value: 'PHONE', label: 'Telefone' },
  { value: 'EVP', label: 'Chave aleatória' },
]

export function pixKeyTypeLabel(type: PixKeyType | null | undefined): string {
  return PIX_KEY_TYPE_OPTIONS.find((option) => option.value === type)?.label ?? '—'
}
