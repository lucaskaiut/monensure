import { Badge } from '@/shared/design-system'
import type { PayableStatus } from '@/shared/types/models'

const STATUS_CONFIG: Record<PayableStatus, { label: string; variant: 'neutral' | 'primary' | 'success' | 'warning' | 'danger' }> = {
  pendente: { label: 'Pendente', variant: 'warning' },
  pago: { label: 'Pago', variant: 'success' },
  cancelado: { label: 'Cancelado', variant: 'danger' },
}

export function PayableStatusBadge({ status }: { status: PayableStatus }) {
  const config = STATUS_CONFIG[status] ?? { label: status, variant: 'neutral' as const }

  return <Badge variant={config.variant}>{config.label}</Badge>
}
