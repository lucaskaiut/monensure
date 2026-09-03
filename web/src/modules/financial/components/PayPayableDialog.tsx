import { useState } from 'react'
import { Button, DatePicker, Modal } from '@/shared/design-system'
import { formatCurrency, toLocalIsoDate } from '@/shared/utils/format'
import type { Payable } from '@/shared/types/models'

export function PayPayableDialog({
  payable,
  open,
  loading,
  onClose,
  onConfirm,
}: {
  payable: Payable | null
  open: boolean
  loading: boolean
  onClose: () => void
  onConfirm: (payload: { paid_at: string; paid_value: number }) => void
}) {
  const [paidAt, setPaidAt] = useState(toLocalIsoDate())
  const [paidValue, setPaidValue] = useState('')

  const baseValue = payable ? Number(payable.value) : 0

  const confirm = () => {
    if (!payable) return

    onConfirm({
      paid_at: paidAt,
      paid_value: paidValue === '' ? baseValue : Number(paidValue),
    })
  }

  const difference =
    payable && paidValue !== '' ? Number(paidValue) - Number(payable.value) : 0

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Registrar pagamento"
      description={payable ? payable.description : undefined}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button onClick={confirm} loading={loading}>
            Confirmar pagamento
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        <div className="rounded-lg bg-surface-2 p-3 text-sm">
          <p className="text-muted">Valor da conta</p>
          <p className="text-lg font-semibold text-foreground">{formatCurrency(baseValue)}</p>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-1.5">
            <label htmlFor="pay-paid-at" className="block text-[13px] font-medium text-foreground">
              Data do pagamento
            </label>
            <DatePicker
              id="pay-paid-at"
              value={paidAt}
              onChange={(event) => setPaidAt(event.target.value)}
            />
          </div>
          <div className="space-y-1.5">
            <label htmlFor="pay-paid-value" className="block text-[13px] font-medium text-foreground">
              Valor pago
            </label>
            <input
              id="pay-paid-value"
              type="number"
              step="0.01"
              placeholder={String(baseValue)}
              value={paidValue}
              onChange={(event) => setPaidValue(event.target.value)}
              className="h-10 w-full rounded-lg bg-surface-2 px-3.5 text-sm text-foreground"
            />
          </div>
        </div>

        {difference !== 0 && (
          <p className="text-[13px] text-warning">
            Divergência de {formatCurrency(difference)} em relação ao valor original.
          </p>
        )}
      </div>
    </Modal>
  )
}
