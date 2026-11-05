import { useState } from 'react'
import { ArrowLeft, ShieldCheck } from 'lucide-react'
import {
  Alert,
  Badge,
  Button,
  DatePicker,
  Field,
  Input,
  Modal,
  SegmentedControl,
  Select,
  Textarea,
} from '@/shared/design-system'
import { parseApiError } from '@/shared/api/errors'
import { formatDocument, onlyDigits } from '@/shared/utils/document'
import { formatCurrency, formatDate, toLocalIsoDate } from '@/shared/utils/format'
import type { Payable, PayoutMethod, PayoutPreview, PixKeyType } from '@/shared/types/models'
import { usePayoutOverview, usePreviewPayout, useSchedulePayout } from '../hooks/usePayouts'
import { PIX_KEY_TYPE_OPTIONS } from '../utils/pix'

const METHOD_OPTIONS: Array<{ value: PayoutMethod; label: string }> = [
  { value: 'boleto', label: 'Boleto' },
  { value: 'pix_qr_code', label: 'PIX copia e cola' },
  { value: 'pix_key', label: 'Chave PIX' },
]


type When = 'now' | 'schedule'

function defaultTime(): string {
  const next = new Date(Date.now() + 60 * 60 * 1000)

  return `${String(next.getHours()).padStart(2, '0')}:00`
}

export function AsaasPayDialog({
  payable,
  open,
  onClose,
}: {
  payable: Payable | null
  open: boolean
  onClose: () => void
}) {
  const [method, setMethod] = useState<PayoutMethod>('boleto')
  const [code, setCode] = useState('')
  const [pixKeyType, setPixKeyType] = useState<PixKeyType | ''>('')
  const [preview, setPreview] = useState<PayoutPreview | null>(null)
  const [value, setValue] = useState('')
  const [when, setWhen] = useState<When>('now')
  const [date, setDate] = useState(payable?.due_date ?? toLocalIsoDate())
  const [time, setTime] = useState(defaultTime)
  const [password, setPassword] = useState('')

  const overview = usePayoutOverview(open)
  const previewMutation = usePreviewPayout()
  const schedule = useSchedulePayout(payable?.id ?? '')

  const previewError = previewMutation.error ? parseApiError(previewMutation.error) : null
  const scheduleError = schedule.error ? parseApiError(schedule.error) : null

  const reset = () => {
    setPreview(null)
    setPassword('')
    previewMutation.reset()
    schedule.reset()
  }

  const validate = () => {
    if (!code.trim()) return

    previewMutation.mutate(
      { method, payment_code: code, pix_key_type: method === 'pix_key' ? pixKeyType || null : null },
      {
        onSuccess: (result) => {
          setPreview(result)
          setValue(
            result.value !== null
              ? result.value.toFixed(2)
              : payable
                ? Number(payable.value).toFixed(2)
                : '',
          )
        },
      },
    )
  }

  if (!payable) return null

  const fixedValue = preview !== null && preview.value !== null && !preview.allows_custom_value
  const amount = fixedValue ? (preview?.value ?? 0) : Number(value || 0)
  const scheduledAt = when === 'schedule' ? new Date(`${date}T${time}:00`) : null
  const scheduleInPast = scheduledAt !== null && scheduledAt.getTime() < Date.now() - 60_000

  const supplierDocument = onlyDigits(payable.supplier?.document ?? '')
  const beneficiaryDocument = preview?.beneficiary_document ?? ''
  const documentMismatch =
    supplierDocument !== '' &&
    beneficiaryDocument !== '' &&
    !beneficiaryDocument.includes('*') &&
    supplierDocument !== beneficiaryDocument

  const valueDiffers = amount > 0 && Math.abs(amount - Number(payable.value)) >= 0.01

  const confirm = () => {
    if (!preview) return

    schedule.mutate(
      {
        method,
        payment_code: preview.payment_code,
        pix_key_type: method === 'pix_key' ? pixKeyType || null : null,
        value: fixedValue ? null : amount,
        scheduled_for: scheduledAt ? scheduledAt.toISOString() : null,
        password,
      },
      { onSuccess: () => onClose() },
    )
  }

  const disabled = overview.data !== undefined && !overview.data.enabled

  return (
    <Modal
      open={open}
      onClose={onClose}
      size="lg"
      title="Pagar via Asaas"
      description={`${payable.description} · ${formatCurrency(payable.value)} · vence em ${formatDate(payable.due_date)}`}
      footer={
        preview === null ? (
          <>
            <Button variant="secondary" onClick={onClose}>
              Cancelar
            </Button>
            <Button
              onClick={validate}
              loading={previewMutation.isPending}
              disabled={disabled || !code.trim() || (method === 'pix_key' && !pixKeyType)}
            >
              Validar no Asaas
            </Button>
          </>
        ) : (
          <>
            <Button variant="secondary" onClick={reset}>
              <ArrowLeft className="size-4" />
              Voltar
            </Button>
            <Button
              onClick={confirm}
              loading={schedule.isPending}
              disabled={!preview.can_pay || amount <= 0 || !password || scheduleInPast}
            >
              {when === 'now' ? `Pagar ${formatCurrency(amount)} agora` : 'Agendar pagamento'}
            </Button>
          </>
        )
      }
    >
      <div className="space-y-4">
        {overview.data && (
          <div className="flex flex-wrap items-center gap-2 text-[13px] text-muted">
            <Badge variant={overview.data.environment === 'sandbox' ? 'warning' : 'success'}>
              {overview.data.environment === 'sandbox' ? 'Sandbox' : 'Produção'}
            </Badge>
            {overview.data.balance !== null && <span>Saldo: {formatCurrency(overview.data.balance)}</span>}
            <span>Limite por pagamento: {formatCurrency(overview.data.max_per_payment)}</span>
          </div>
        )}

        {disabled && (
          <Alert variant="warning" title="Pagamentos via Asaas desativados">
            Defina ASAAS_PAYOUTS_ENABLED=true no servidor para habilitar.
          </Alert>
        )}

        {preview === null ? (
          <>
            <SegmentedControl
              value={method}
              options={METHOD_OPTIONS}
              onChange={(next) => {
                setMethod(next)
                previewMutation.reset()

                // Chave PIX do fornecedor como sugestão (pode ser trocada).
                if (next === 'pix_key' && payable?.supplier?.pix_key) {
                  setCode(payable.supplier.pix_key)
                  setPixKeyType(payable.supplier.pix_key_type ?? '')
                } else if (next !== method) {
                  setCode('')
                }
              }}
            />

            {method === 'pix_key' ? (
              <div className="grid gap-4 sm:grid-cols-[180px_1fr]">
                <Field label="Tipo da chave" htmlFor="payout-key-type" required>
                  <Select
                    id="payout-key-type"
                    value={pixKeyType}
                    onChange={(event) => setPixKeyType(event.target.value as PixKeyType)}
                    options={PIX_KEY_TYPE_OPTIONS}
                    placeholder="Selecione"
                  />
                </Field>
                <Field label="Chave PIX" htmlFor="payout-code" required>
                  <Input
                    id="payout-code"
                    value={code}
                    onChange={(event) => setCode(event.target.value)}
                    autoComplete="off"
                  />
                </Field>
              </div>
            ) : (
              <Field
                label={method === 'boleto' ? 'Linha digitável' : 'Código PIX copia e cola'}
                htmlFor="payout-code"
                required
                hint={
                  method === 'boleto'
                    ? 'Cole os 47 ou 48 dígitos do boleto (pontos e espaços são ignorados).'
                    : 'Cole o código completo, começando por 000201.'
                }
              >
                <Textarea
                  id="payout-code"
                  rows={3}
                  value={code}
                  onChange={(event) => setCode(event.target.value)}
                  className="font-mono text-[13px]"
                />
              </Field>
            )}

            {method === 'pix_key' && payable.supplier?.pix_key && code === payable.supplier.pix_key && (
              <p className="text-[13px] text-muted">Chave PIX cadastrada no fornecedor {payable.supplier.name}.</p>
            )}

            {method === 'pix_key' && (
              <Alert variant="info" title="Confira a chave com atenção">
                O Asaas não mostra o titular de uma chave PIX antes da transferência. O nome do
                recebedor aparece no comprovante.
              </Alert>
            )}

            {previewError && (
              <Alert variant="danger" title="Não foi possível validar">
                {previewError.message}
              </Alert>
            )}
          </>
        ) : (
          <>
            <div className="grid gap-3 rounded-xl bg-surface-2 p-4 text-sm sm:grid-cols-2">
              <div>
                <p className="text-muted">Beneficiário</p>
                <p className="font-medium text-foreground">
                  {preview.beneficiary_name ?? (method === 'pix_key' ? preview.payment_code : '—')}
                </p>
                {preview.beneficiary_document && (
                  <p className="text-[13px] text-muted">
                    {preview.beneficiary_document.includes('*')
                      ? preview.beneficiary_document
                      : formatDocument(preview.beneficiary_document)}
                  </p>
                )}
              </div>
              <div>
                <p className="text-muted">Valor no Asaas</p>
                <p className="font-medium text-foreground">
                  {preview.value !== null ? formatCurrency(preview.value) : 'Definido por você'}
                </p>
                {preview.due_date && (
                  <p className="text-[13px] text-muted">Vencimento: {formatDate(preview.due_date)}</p>
                )}
                {preview.fee !== null && preview.fee > 0 && (
                  <p className="text-[13px] text-muted">Taxa: {formatCurrency(preview.fee)}</p>
                )}
              </div>
            </div>

            {!preview.can_pay && (
              <Alert variant="danger" title="O Asaas informou que este pagamento não pode ser feito">
                {preview.cannot_pay_reason ?? 'Verifique o código informado.'}
              </Alert>
            )}

            {documentMismatch && (
              <Alert variant="warning" title="Beneficiário diferente do fornecedor">
                O documento do beneficiário ({formatDocument(beneficiaryDocument)}) não é o do fornecedor
                cadastrado ({formatDocument(supplierDocument)}). Confira antes de pagar.
              </Alert>
            )}

            {!fixedValue && (
              <Field label="Valor a pagar" htmlFor="payout-value" required>
                <Input
                  id="payout-value"
                  type="number"
                  step="0.01"
                  min="0.01"
                  value={value}
                  onChange={(event) => setValue(event.target.value)}
                />
              </Field>
            )}

            {valueDiffers && (
              <p className="text-[13px] text-warning">
                O valor a pagar ({formatCurrency(amount)}) é diferente do valor da conta (
                {formatCurrency(payable.value)}).
              </p>
            )}

            <SegmentedControl
              value={when}
              options={[
                { value: 'now', label: 'Pagar agora' },
                { value: 'schedule', label: 'Agendar' },
              ]}
              onChange={setWhen}
            />

            {when === 'schedule' && (
              <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Data" htmlFor="payout-date" required>
                  <DatePicker
                    id="payout-date"
                    value={date}
                    min={toLocalIsoDate()}
                    onChange={(event) => setDate(event.target.value)}
                  />
                </Field>
                <Field
                  label="Horário"
                  htmlFor="payout-time"
                  required
                  error={scheduleInPast ? 'Escolha um horário no futuro.' : undefined}
                >
                  <Input
                    id="payout-time"
                    type="time"
                    value={time}
                    onChange={(event) => setTime(event.target.value)}
                  />
                </Field>
              </div>
            )}

            <p className="text-[13px] text-muted">
              No horário do pagamento, o sistema valida o código de novo no Asaas. Se o valor ficar maior
              que {formatCurrency(amount)} ou o beneficiário mudar, o pagamento não é enviado.
            </p>

            <Field
              label="Confirme sua senha"
              htmlFor="payout-password"
              required
              error={scheduleError?.fieldErrors.password?.[0]}
            >
              <Input
                id="payout-password"
                type="password"
                autoComplete="current-password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                onKeyDown={(event) => {
                  if (event.key === 'Enter' && password && preview.can_pay && amount > 0 && !scheduleInPast) confirm()
                }}
              />
            </Field>

            {scheduleError && !scheduleError.fieldErrors.password && (
              <Alert variant="danger" title="Pagamento não realizado">
                {scheduleError.message}
              </Alert>
            )}

            <p className="flex items-center gap-1.5 text-[13px] text-muted">
              <ShieldCheck className="size-4" aria-hidden="true" />
              Cada saída é conferida pelo webhook de autorização antes de o Asaas executá-la.
            </p>
          </>
        )}
      </div>
    </Modal>
  )
}
