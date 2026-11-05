import { useState } from 'react'
import { Landmark } from 'lucide-react'
import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  ConfirmDialog,
  Field,
  Input,
  Modal,
  Select,
} from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { parseApiError } from '@/shared/api/errors'
import { formatCurrency, formatDateTime } from '@/shared/utils/format'
import type { FinancialRecurrence, PixKeyType } from '@/shared/types/models'
import { useDisableAutoPay, useEnableAutoPay } from '../hooks/useRecurrences'
import { PIX_KEY_TYPE_OPTIONS, pixKeyTypeLabel } from '../utils/pix'

export function RecurrenceAutoPayCard({ recurrence }: { recurrence: FinancialRecurrence }) {
  const { can } = usePermissions()
  const [editing, setEditing] = useState(false)
  const [confirmDisable, setConfirmDisable] = useState(false)
  const disable = useDisableAutoPay(recurrence.id)

  const autoPay = recurrence.auto_pay
  const enabled = autoPay?.enabled ?? false
  const canManage = can(Permission.PAYOUT_EXECUTE) && can(Permission.RECURRENCE_UPDATE)

  return (
    <Card>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <div className="flex gap-3">
            <Landmark className="mt-0.5 size-5 text-muted" aria-hidden="true" />
            <div>
              <p className="font-semibold text-foreground">Pagamento automático via PIX</p>
              <p className="text-sm text-muted">
                Cada conta desta recorrência é agendada 3 dias antes e paga via Asaas no dia do vencimento.
              </p>
            </div>
          </div>
          <Badge variant={enabled ? 'success' : 'neutral'}>{enabled ? 'Ativo' : 'Desligado'}</Badge>
        </div>

        {enabled && autoPay && (
          <dl className="grid gap-3 rounded-xl bg-surface-2 p-4 text-sm sm:grid-cols-2">
            <div>
              <dt className="text-muted">Chave PIX</dt>
              <dd className="break-all text-foreground">
                {autoPay.pix_key} <span className="text-muted">({pixKeyTypeLabel(autoPay.pix_key_type)})</span>
              </dd>
            </div>
            <div>
              <dt className="text-muted">Horário</dt>
              <dd className="text-foreground">{autoPay.time} do dia do vencimento</dd>
            </div>
            <div>
              <dt className="text-muted">Valor máximo por mês</dt>
              <dd className="text-foreground">{formatCurrency(autoPay.max_value)}</dd>
            </div>
            <div>
              <dt className="text-muted">Aprovado</dt>
              <dd className="text-foreground">
                {autoPay.approved_by ?? '—'} · {formatDateTime(autoPay.approved_at)}
              </dd>
            </div>
          </dl>
        )}

        {!recurrence.active && (
          <p className="text-[13px] text-muted">Ative a recorrência para usar o pagamento automático.</p>
        )}

        {canManage && recurrence.active && (
          <div className="flex flex-wrap justify-end gap-2">
            {enabled && (
              <Button variant="secondary" onClick={() => setConfirmDisable(true)}>
                Desligar
              </Button>
            )}
            <Button onClick={() => setEditing(true)}>{enabled ? 'Alterar' : 'Ativar pagamento automático'}</Button>
          </div>
        )}
      </CardContent>

      {editing && <AutoPayModal recurrence={recurrence} onClose={() => setEditing(false)} />}

      <ConfirmDialog
        open={confirmDisable}
        onClose={() => setConfirmDisable(false)}
        onConfirm={() => disable.mutate(undefined, { onSettled: () => setConfirmDisable(false) })}
        loading={disable.isPending}
        title="Desligar pagamento automático"
        description="Os pagamentos desta recorrência ainda não enviados ao Asaas serão cancelados. As próximas contas ficam para pagamento manual."
        confirmLabel="Desligar"
      />
    </Card>
  )
}

function AutoPayModal({ recurrence, onClose }: { recurrence: FinancialRecurrence; onClose: () => void }) {
  const current = recurrence.auto_pay
  const supplier = recurrence.supplier
  const hasSupplierKey = !!supplier?.pix_key

  // Por padrão usa a chave do fornecedor; a recorrência pode ter uma própria.
  const [useSupplierKey, setUseSupplierKey] = useState(
    hasSupplierKey && (!current?.enabled || current.pix_key === supplier?.pix_key),
  )
  const [pixKeyType, setPixKeyType] = useState<PixKeyType | ''>(
    current?.enabled && !useSupplierKey ? (current.pix_key_type ?? '') : '',
  )
  const [pixKey, setPixKey] = useState(current?.enabled && !useSupplierKey ? (current.pix_key ?? '') : '')
  const [time, setTime] = useState(current?.time ?? '09:00')
  const [maxValue, setMaxValue] = useState(
    current?.max_value ?? Number(recurrence.default_value).toFixed(2),
  )
  const [password, setPassword] = useState('')

  const enable = useEnableAutoPay(recurrence.id)
  const error = enable.error ? parseApiError(enable.error) : null

  const keyMissing = !useSupplierKey && (!pixKeyType || !pixKey.trim())
  const maxBelowDefault = Number(maxValue) > 0 && Number(maxValue) < Number(recurrence.default_value)

  const submit = () =>
    enable.mutate(
      {
        pix_key: useSupplierKey ? null : pixKey.trim(),
        pix_key_type: useSupplierKey ? null : pixKeyType || null,
        time,
        max_value: Number(maxValue),
        password,
      },
      { onSuccess: () => onClose() },
    )

  return (
    <Modal
      open
      onClose={onClose}
      size="lg"
      title="Pagamento automático via PIX"
      description={recurrence.description}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button
            onClick={submit}
            loading={enable.isPending}
            disabled={keyMissing || !password || !(Number(maxValue) > 0) || !time}
          >
            Aprovar e ativar
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        {hasSupplierKey && (
          <div className="space-y-2">
            <label className="flex items-center gap-2 text-sm text-foreground">
              <input
                type="radio"
                checked={useSupplierKey}
                onChange={() => setUseSupplierKey(true)}
              />
              Usar a chave do fornecedor: <strong className="break-all">{supplier?.pix_key}</strong>
              <span className="text-muted">({pixKeyTypeLabel(supplier?.pix_key_type)})</span>
            </label>
            <label className="flex items-center gap-2 text-sm text-foreground">
              <input type="radio" checked={!useSupplierKey} onChange={() => setUseSupplierKey(false)} />
              Usar outra chave nesta recorrência
            </label>
          </div>
        )}

        {!hasSupplierKey && (
          <p className="text-[13px] text-muted">
            {supplier
              ? `O fornecedor ${supplier.name} não tem chave PIX cadastrada. Informe uma chave para esta recorrência.`
              : 'A recorrência não tem fornecedor. Informe a chave PIX de destino.'}
          </p>
        )}

        {!useSupplierKey && (
          <div className="grid gap-4 sm:grid-cols-[180px_1fr]">
            <Field label="Tipo da chave" htmlFor="autopay-key-type" required>
              <Select
                id="autopay-key-type"
                value={pixKeyType}
                onChange={(event) => setPixKeyType(event.target.value as PixKeyType)}
                options={PIX_KEY_TYPE_OPTIONS}
                placeholder="Selecione"
              />
            </Field>
            <Field label="Chave PIX" htmlFor="autopay-key" required error={error?.fieldErrors.pix_key?.[0]}>
              <Input id="autopay-key" value={pixKey} onChange={(event) => setPixKey(event.target.value)} autoComplete="off" />
            </Field>
          </div>
        )}

        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Horário do pagamento" htmlFor="autopay-time" required hint="No dia do vencimento (horário de Brasília).">
            <Input id="autopay-time" type="time" value={time} onChange={(event) => setTime(event.target.value)} />
          </Field>
          <Field
            label="Valor máximo por mês"
            htmlFor="autopay-max"
            required
            hint="Contas acima deste valor não são pagas automaticamente."
            error={error?.fieldErrors.max_value?.[0]}
          >
            <Input
              id="autopay-max"
              type="number"
              step="0.01"
              min="0.01"
              value={maxValue}
              onChange={(event) => setMaxValue(event.target.value)}
            />
          </Field>
        </div>

        {maxBelowDefault && (
          <p className="text-[13px] text-warning">
            O máximo é menor que o valor padrão da recorrência ({formatCurrency(recurrence.default_value)}): essas contas
            não serão pagas automaticamente.
          </p>
        )}

        <Alert variant="info" title="O que você está aprovando">
          A partir de agora, o sistema paga sozinho, sem pedir senha a cada mês, as contas desta recorrência de até{' '}
          {formatCurrency(Number(maxValue) || 0)} para a chave escolhida. A chave fica gravada: se a chave do fornecedor
          mudar depois, esta recorrência continua com a chave aprovada. Cada pagamento continua sujeito aos limites, ao
          saldo e ao webhook de autorização, e pode ser cancelado em Pagamentos Asaas antes do horário.
        </Alert>

        <Field label="Confirme sua senha" htmlFor="autopay-password" required error={error?.fieldErrors.password?.[0]}>
          <Input
            id="autopay-password"
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
          />
        </Field>

        {error && !error.fieldErrors.password && !error.fieldErrors.pix_key && !error.fieldErrors.max_value && (
          <Alert variant="danger" title="Não foi possível ativar">
            {error.message}
          </Alert>
        )}
      </div>
    </Modal>
  )
}
