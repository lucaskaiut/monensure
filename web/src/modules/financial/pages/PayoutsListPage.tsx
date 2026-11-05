import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { Ban, Eye, Landmark, RefreshCw, ShieldAlert } from 'lucide-react'
import {
  Alert,
  Badge,
  Button,
  ConfirmDialog,
  DataTable,
  EmptyState,
  Field,
  FilterBar,
  Input,
  Modal,
  Page,
  PageContent,
  PageHeader,
  Pagination,
  parsePerPage,
  SegmentedControl,
  Select,
  type Column,
} from '@/shared/design-system'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { parseApiError } from '@/shared/api/errors'
import { formatDocument } from '@/shared/utils/document'
import { formatCurrency, formatDate, formatDateTime } from '@/shared/utils/format'
import type { PayoutOrder, PayoutStatus } from '@/shared/types/models'
import {
  useCancelPayout,
  usePayoutOverview,
  usePayoutQuery,
  usePayoutsQuery,
  useResolvePayout,
  useSyncPayout,
} from '../hooks/usePayouts'
import { PAYOUT_STATUS_LABELS, PayoutStatusBadge } from '../components/PayoutStatusBadge'

const STATUS_OPTIONS = (Object.keys(PAYOUT_STATUS_LABELS) as PayoutStatus[]).map((status) => ({
  value: status,
  label: PAYOUT_STATUS_LABELS[status].label,
}))

function StatTile({ label, value, hint }: { label: string; value: string; hint?: string }) {
  return (
    <div className="rounded-xl border border-border bg-surface px-4 py-3">
      <p className="text-[13px] text-muted">{label}</p>
      <p className="text-lg font-semibold text-foreground">{value}</p>
      {hint && <p className="text-[12px] text-subtle">{hint}</p>}
    </div>
  )
}

export default function PayoutsListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const page = Number(searchParams.get('page') ?? 1)
  const perPage = parsePerPage(searchParams.get('per_page'))
  const status = (searchParams.get('status') ?? '') as PayoutStatus | ''
  const { can } = usePermissions()
  const canExecute = can(Permission.PAYOUT_EXECUTE)

  const [detailId, setDetailId] = useState<string | null>(null)
  const [toCancel, setToCancel] = useState<PayoutOrder | null>(null)
  const [toResolve, setToResolve] = useState<PayoutOrder | null>(null)

  const overview = usePayoutOverview()
  const query = usePayoutsQuery({ page, per_page: perPage, status })
  const cancel = useCancelPayout()
  const sync = useSyncPayout()

  const setParam = (key: string, value: string) => {
    setSearchParams(
      (params) => {
        if (value) params.set(key, value)
        else params.delete(key)
        if (key !== 'page') params.delete('page')
        return params
      },
      { replace: true },
    )
  }

  const columns: Array<Column<PayoutOrder>> = [
    {
      key: 'payable',
      header: 'Conta',
      render: (order) => (
        <div className="min-w-0">
          <p className="truncate font-medium text-foreground">{order.payable?.description ?? '—'}</p>
          <p className="truncate text-[13px] text-muted">
            {order.beneficiary_name ?? order.payable?.supplier?.name ?? '—'}
          </p>
        </div>
      ),
    },
    {
      key: 'method',
      header: 'Forma',
      render: (order) => (
        <div className="flex flex-col items-start gap-1">
          <span className="text-muted">{order.method_label}</span>
          {order.source === 'recurrence' && <Badge variant="primary">Automático</Badge>}
        </div>
      ),
    },
    {
      key: 'value',
      header: 'Valor',
      render: (order) => (
        <span className="text-foreground">{formatCurrency(order.paid_value ?? order.value)}</span>
      ),
    },
    {
      key: 'scheduled_for',
      header: 'Pagamento',
      render: (order) => <span className="text-muted">{formatDateTime(order.scheduled_for)}</span>,
    },
    {
      key: 'status',
      header: 'Status',
      render: (order) => (
        <div className="space-y-1">
          <PayoutStatusBadge status={order.status} />
          {order.failure_reason && (order.status === 'failed' || order.status === 'needs_review') && (
            <p className="max-w-64 truncate text-[12px] text-danger" title={order.failure_reason}>
              {order.failure_reason}
            </p>
          )}
        </div>
      ),
    },
    {
      key: 'actions',
      header: <span className="sr-only">Ações</span>,
      className: 'w-36 text-right',
      render: (order) => (
        <div className="flex items-center justify-end gap-1">
          <Button variant="ghost" size="sm" onClick={() => setDetailId(order.id)} aria-label="Ver detalhes">
            <Eye className="size-4" />
          </Button>
          {canExecute && (order.status === 'processing' || order.status === 'needs_review') && order.external_id && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => sync.mutate(order.id)}
              loading={sync.isPending && sync.variables === order.id}
              aria-label="Consultar status no Asaas"
            >
              <RefreshCw className="size-4" />
            </Button>
          )}
          {canExecute && order.status === 'needs_review' && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setToResolve(order)}
              aria-label="Resolver pagamento"
              className="text-danger hover:bg-danger-soft hover:text-danger"
            >
              <ShieldAlert className="size-4" />
            </Button>
          )}
          {canExecute && order.status === 'scheduled' && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setToCancel(order)}
              aria-label="Cancelar agendamento"
              className="text-danger hover:bg-danger-soft hover:text-danger"
            >
              <Ban className="size-4" />
            </Button>
          )}
        </div>
      ),
    },
  ]

  const data = overview.data

  return (
    <Page>
      <PageHeader
        title="Pagamentos Asaas"
        description="Contas pagas ou agendadas pela sua conta Asaas."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Pagamentos Asaas' }]}
        actions={
          data && (
            <Badge variant={data.environment === 'sandbox' ? 'warning' : 'success'}>
              {data.environment === 'sandbox' ? 'Sandbox' : 'Produção'}
            </Badge>
          )
        }
      />

      <PageContent>
        {data && !data.enabled && (
          <Alert variant="warning" title="Pagamentos via Asaas desativados">
            Nenhum pagamento é agendado ou executado enquanto ASAAS_PAYOUTS_ENABLED estiver falso no servidor.
          </Alert>
        )}

        {data && (
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <StatTile label="Saldo disponível" value={data.balance !== null ? formatCurrency(data.balance) : '—'} />
            <StatTile
              label="Comprometido hoje"
              value={formatCurrency(data.committed_today)}
              hint={`de ${formatCurrency(data.max_per_day)} por dia`}
            />
            <StatTile label="Limite por pagamento" value={formatCurrency(data.max_per_payment)} />
            <StatTile label="Limite diário" value={formatCurrency(data.max_per_day)} />
          </div>
        )}

        <FilterBar>
          <Select
            className="w-48"
            value={status}
            onChange={(event) => setParam('status', event.target.value)}
            options={STATUS_OPTIONS}
            placeholder="Todos os status"
            aria-label="Filtrar por status"
          />
        </FilterBar>

        <DataTable
          caption="Pagamentos via Asaas"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(order) => order.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={Landmark}
              title="Nenhum pagamento via Asaas"
              description="Use o botão de pagamento via Asaas em Contas a pagar para pagar ou agendar uma conta."
            />
          }
        />

        {query.data && (
          <Pagination
            meta={query.data.meta}
            onPageChange={(next) => setParam('page', String(next))}
            onPerPageChange={(next) => setParam('per_page', String(next))}
          />
        )}
      </PageContent>

      <PayoutDetailModal id={detailId} onClose={() => setDetailId(null)} />

      <ConfirmDialog
        open={toCancel !== null}
        onClose={() => setToCancel(null)}
        onConfirm={() => toCancel && cancel.mutate(toCancel.id, { onSettled: () => setToCancel(null) })}
        loading={cancel.isPending}
        title="Cancelar agendamento"
        description={
          <>
            O pagamento de <strong>{formatCurrency(toCancel?.value)}</strong> para{' '}
            <strong>{toCancel?.payable?.description}</strong> não será enviado ao Asaas.
          </>
        }
        confirmLabel="Cancelar agendamento"
      />

      <ResolvePayoutDialog
        key={toResolve?.id ?? 'none'}
        order={toResolve}
        onClose={() => setToResolve(null)}
      />
    </Page>
  )
}

function PayoutDetailModal({ id, onClose }: { id: string | null; onClose: () => void }) {
  const query = usePayoutQuery(id)
  const order = query.data

  return (
    <Modal open={id !== null} onClose={onClose} size="lg" title="Detalhes do pagamento">
      {!order ? (
        <p className="text-sm text-muted">Carregando...</p>
      ) : (
        <div className="space-y-4 text-sm">
          <div className="flex flex-wrap items-center gap-2">
            <PayoutStatusBadge status={order.status} />
            {order.external_status && <Badge>{`Asaas: ${order.external_status}`}</Badge>}
          </div>

          <dl className="grid gap-3 sm:grid-cols-2">
            <div>
              <dt className="text-muted">Conta</dt>
              <dd className="text-foreground">
                {order.payable?.description} · vence em {formatDate(order.payable?.due_date)}
              </dd>
            </div>
            <div>
              <dt className="text-muted">Beneficiário</dt>
              <dd className="text-foreground">
                {order.beneficiary_name ?? '—'}
                {order.beneficiary_document && !order.beneficiary_document.includes('*') && (
                  <span className="text-muted"> · {formatDocument(order.beneficiary_document)}</span>
                )}
              </dd>
            </div>
            <div>
              <dt className="text-muted">Valor aprovado</dt>
              <dd className="text-foreground">{formatCurrency(order.value)}</dd>
            </div>
            <div>
              <dt className="text-muted">Valor pago</dt>
              <dd className="text-foreground">{order.paid_value ? formatCurrency(order.paid_value) : '—'}</dd>
            </div>
            <div>
              <dt className="text-muted">Forma</dt>
              <dd className="text-foreground">
                {order.method_label}
                {order.pix_key_type && ` (${order.pix_key_type})`}
              </dd>
            </div>
            <div>
              <dt className="text-muted">Agendado para</dt>
              <dd className="text-foreground">{formatDateTime(order.scheduled_for)}</dd>
            </div>
            <div className="sm:col-span-2">
              <dt className="text-muted">Código</dt>
              <dd className="font-mono text-[12px] break-all text-foreground">{order.payment_code}</dd>
            </div>
          </dl>

          {order.failure_reason && (
            <Alert variant={order.status === 'paid' ? 'info' : 'danger'} title="Observação">
              {order.failure_reason}
            </Alert>
          )}

          {order.receipt_url && (
            <a
              href={order.receipt_url}
              target="_blank"
              rel="noreferrer"
              className="inline-block font-medium text-primary hover:underline"
            >
              Abrir comprovante no Asaas
            </a>
          )}

          {order.events && order.events.length > 0 && (
            <div>
              <p className="mb-2 font-medium text-foreground">Histórico</p>
              <ol className="space-y-2 border-l border-border pl-4">
                {order.events.map((event, index) => (
                  <li key={index}>
                    <p className="text-foreground">{event.message}</p>
                    <p className="text-[12px] text-subtle">{formatDateTime(event.created_at)}</p>
                  </li>
                ))}
              </ol>
            </div>
          )}
        </div>
      )}
    </Modal>
  )
}

function ResolvePayoutDialog({ order, onClose }: { order: PayoutOrder | null; onClose: () => void }) {
  const [outcome, setOutcome] = useState<'paid' | 'failed'>('failed')
  const [password, setPassword] = useState('')
  const resolve = useResolvePayout()
  const error = resolve.error ? parseApiError(resolve.error) : null

  if (!order) return null

  return (
    <Modal
      open
      onClose={onClose}
      title="Resolver pagamento em conferência"
      description={`${order.payable?.description ?? ''} · ${formatCurrency(order.paid_value ?? order.value)}`}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>
            Cancelar
          </Button>
          <Button
            onClick={() =>
              resolve.mutate({ id: order.id, outcome, password }, { onSuccess: () => onClose() })
            }
            loading={resolve.isPending}
            disabled={!password}
          >
            Confirmar
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        <Alert variant="warning" title="Confira no painel do Asaas antes de decidir">
          {order.failure_reason ??
            'O resultado do envio não foi confirmado. Veja no extrato do Asaas se o valor saiu.'}
        </Alert>

        <SegmentedControl
          value={outcome}
          options={[
            { value: 'failed', label: 'Não foi pago' },
            { value: 'paid', label: 'Foi pago' },
          ]}
          onChange={setOutcome}
        />

        <p className="text-[13px] text-muted">
          {outcome === 'paid'
            ? 'A conta será marcada como paga com o valor enviado.'
            : 'A ordem será encerrada e a conta volta a ficar disponível para pagamento.'}
        </p>

        <Field
          label="Confirme sua senha"
          htmlFor="resolve-password"
          required
          error={error?.fieldErrors.password?.[0]}
        >
          <Input
            id="resolve-password"
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
          />
        </Field>

        {error && !error.fieldErrors.password && (
          <Alert variant="danger" title="Não foi possível resolver">
            {error.message}
          </Alert>
        )}
      </div>
    </Modal>
  )
}
