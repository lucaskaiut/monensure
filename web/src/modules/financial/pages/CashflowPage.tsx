import { TrendingUp, AlertTriangle } from 'lucide-react'
import {
  Card,
  CardContent,
  DataTable,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  type Column,
} from '@/shared/design-system'
import { formatCurrency, formatDate } from '@/shared/utils/format'
import type { Payable } from '@/shared/types/models'
import { useCashflowProjection, useOverduePayables } from '../hooks/useFinancialOverview'
import { PayableStatusBadge } from '../components/PayableStatusBadge'

export default function CashflowPage() {
  const projection = useCashflowProjection()
  const overdue = useOverduePayables({ page: 1, per_page: 10 })

  const columns: Array<Column<Payable>> = [
    {
      key: 'description',
      header: 'Descrição',
      render: (payable) => <p className="font-medium text-foreground">{payable.description}</p>,
    },
    {
      key: 'value',
      header: 'Valor',
      render: (payable) => <span className="text-foreground">{formatCurrency(payable.value)}</span>,
    },
    {
      key: 'due_date',
      header: 'Vencimento',
      render: (payable) => <span className="text-muted">{formatDate(payable.due_date)}</span>,
    },
    {
      key: 'status',
      header: 'Status',
      render: (payable) => <PayableStatusBadge status={payable.status} />,
    },
  ]

  return (
    <Page>
      <PageHeader
        title="Fluxo de caixa"
        description="Projeção de pagamentos futuros e contas vencidas."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Fluxo de caixa' }]}
      />

      <PageContent>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {projection.data?.windows.map((window) => (
            <Card key={window.horizon_days}>
              <CardContent>
                <p className="text-[13px] text-muted">{window.label}</p>
                <p className="mt-1 text-2xl font-semibold tracking-tight text-foreground">
                  {formatCurrency(window.total)}
                </p>
                <p className="mt-0.5 text-[13px] text-muted">{window.count} conta(s)</p>
              </CardContent>
            </Card>
          ))}
        </div>

        {projection.data && projection.data.overdue.count > 0 && (
          <Card className="border-l-4 border-l-warning">
            <CardContent className="flex items-center gap-4">
              <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-warning-soft text-warning">
                <AlertTriangle className="size-5" aria-hidden="true" />
              </span>
              <div>
                <p className="text-[13px] text-muted">Contas vencidas</p>
                <p className="text-2xl font-semibold tracking-tight text-foreground">
                  {formatCurrency(projection.data.overdue.total)}
                </p>
              </div>
            </CardContent>
          </Card>
        )}

        <section className="space-y-4">
          <div className="flex items-center gap-2">
            <TrendingUp className="size-4 text-muted" />
            <h2 className="text-sm font-semibold text-foreground">Contas vencidas</h2>
          </div>

          <DataTable
            caption="Contas vencidas"
            columns={columns}
            rows={overdue.data?.data ?? []}
            rowKey={(payable) => payable.id}
            loading={overdue.isPending}
            emptyState={
              <EmptyState
                icon={TrendingUp}
                title="Nenhuma conta vencida"
                description="Nenhuma conta a pagar está em atraso."
              />
            }
          />
        </section>
      </PageContent>
    </Page>
  )
}
