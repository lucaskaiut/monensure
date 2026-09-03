import { useState } from 'react'
import { Button, Modal, RadioGroup } from '@/shared/design-system'
import type { Payable } from '@/shared/types/models'

type Scope = 'this' | 'this_and_next' | 'all'

export function CancelPayableDialog({
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
  onConfirm: (scope?: Scope) => void
}) {
  const [scope, setScope] = useState<Scope>('this')

  const confirm = () => {
    if (!payable) return

    onConfirm(payable.is_installment ? scope : undefined)
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Cancelar conta"
      description={payable ? payable.description : undefined}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Voltar
          </Button>
          <Button variant="danger" onClick={confirm} loading={loading}>
            Cancelar
          </Button>
        </>
      }
    >
      {payable?.is_installment ? (
        <div className="space-y-3">
          <p className="text-sm text-muted">Escolha quais parcelas deseja cancelar:</p>
          <RadioGroup
            name="cancel-scope"
            value={scope}
            onChange={setScope}
            options={[
              { value: 'this', label: 'Apenas esta parcela' },
              { value: 'this_and_next', label: 'Esta e as próximas' },
              { value: 'all', label: 'Todas do grupo' },
            ]}
            aria-label="Escopo do cancelamento"
          />
        </div>
      ) : (
        <p className="text-sm text-muted">
          Tem certeza que deseja cancelar esta conta? Esta ação pode ser desfeita apenas reeditando o
          lançamento.
        </p>
      )}
    </Modal>
  )
}
