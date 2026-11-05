import { Badge } from '@/shared/design-system'
import type { PayoutStatus } from '@/shared/types/models'

type Variant = 'neutral' | 'primary' | 'success' | 'warning' | 'danger'

export const PAYOUT_STATUS_LABELS: Record<PayoutStatus, { label: string; variant: Variant }> = {
  scheduled: { label: 'Agendado', variant: 'primary' },
  submitting: { label: 'Enviando', variant: 'warning' },
  processing: { label: 'Processando', variant: 'warning' },
  needs_review: { label: 'Conferir', variant: 'danger' },
  paid: { label: 'Pago', variant: 'success' },
  failed: { label: 'Falhou', variant: 'danger' },
  cancelled: { label: 'Cancelado', variant: 'neutral' },
}

export function PayoutStatusBadge({ status }: { status: PayoutStatus }) {
  const config = PAYOUT_STATUS_LABELS[status] ?? { label: status, variant: 'neutral' as const }

  return <Badge variant={config.variant}>{config.label}</Badge>
}
