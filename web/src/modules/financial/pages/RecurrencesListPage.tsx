import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { CalendarClock, Pencil, Plus, Trash2 } from 'lucide-react'
import {
  Badge,
  Button,
  ButtonLink,
  ConfirmDialog,
  DataTable,
  EmptyState,
  FilterBar,
  Page,
  PageContent,
  PageHeader,
  Pagination,
  SearchInput,
  type Column,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { formatCurrency } from '@/shared/utils/format'
import type { FinancialRecurrence } from '@/shared/types/models'
import { useDeleteRecurrence, useRecurrencesQuery } from '../hooks/useRecurrences'

const PER_PAGE = 10

const FREQUENCY_LABELS: Record<string, string> = {
  mensal: 'Mensal',
  bimestral: 'Bimestral',
  trimestral: 'Trimestral',
  semestral: 'Semestral',
  anual: 'Anual',
}

export default function RecurrencesListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)

  const navigate = useNavigate()
  const { can } = usePermissions()

  const [recurrenceToDelete, setRecurrenceToDelete] = useState<FinancialRecurrence | null>(null)
  const deleteRecurrence = useDeleteRecurrence()

  const query = useRecurrencesQuery({ page, per_page: PER_PAGE, search: debouncedSearch || undefined })

  const updateParams = (next: { page?: number; search?: string }) => {
    setSearchParams(
      (params) => {
        if (next.search !== undefined) {
          next.search ? params.set('search', next.search) : params.delete('search')
          params.delete('page')
        }
        if (next.page !== undefined) {
          next.page > 1 ? params.set('page', String(next.page)) : params.delete('page')
        }
        return params
      },
      { replace: true },
    )
  }

  const confirmDelete = () => {
    if (!recurrenceToDelete) return

    deleteRecurrence.mutate(recurrenceToDelete.id, { onSettled: () => setRecurrenceToDelete(null) })
  }

  const columns: Array<Column<FinancialRecurrence>> = [
    {
      key: 'description',
      header: 'Descrição',
      render: (recurrence) => (
        <p className="truncate font-medium text-foreground">{recurrence.description}</p>
      ),
    },
    {
      key: 'default_value',
      header: 'Valor',
      render: (recurrence) => (
        <span className="text-foreground">{formatCurrency(recurrence.default_value)}</span>
      ),
    },
    {
      key: 'due_day',
      header: 'Vencimento',
      render: (recurrence) => <span className="text-muted">Dia {recurrence.due_day}</span>,
    },
    {
      key: 'frequency',
      header: 'Frequência',
      render: (recurrence) => (
        <span className="text-muted">{FREQUENCY_LABELS[recurrence.frequency] ?? recurrence.frequency}</span>
      ),
    },
    {
      key: 'active',
      header: 'Status',
      render: (recurrence) =>
        recurrence.active ? (
          <Badge variant="success">Ativa</Badge>
        ) : (
          <Badge variant="neutral">Inativa</Badge>
        ),
    },
    ...(can(Permission.RECURRENCE_UPDATE) || can(Permission.RECURRENCE_DELETE)
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-24 text-right',
            render: (recurrence: FinancialRecurrence) => (
              <div className="flex items-center justify-end gap-1">
                {can(Permission.RECURRENCE_UPDATE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => navigate(`/financial/recurrences/${recurrence.id}/edit`)}
                    aria-label={`Editar ${recurrence.description}`}
                  >
                    <Pencil className="size-4" />
                  </Button>
                )}
                {can(Permission.RECURRENCE_DELETE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setRecurrenceToDelete(recurrence)}
                    aria-label={`Excluir ${recurrence.description}`}
                    className="text-danger hover:bg-danger-soft hover:text-danger"
                  >
                    <Trash2 className="size-4" />
                  </Button>
                )}
              </div>
            ),
          } satisfies Column<FinancialRecurrence>,
        ]
      : []),
  ]

  return (
    <Page>
      <PageHeader
        title="Recorrências"
        description="Contas fixas geradas automaticamente todo mês."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Recorrências' }]}
        actions={
          <Can permission={Permission.RECURRENCE_CREATE}>
            <ButtonLink to="/financial/recurrences/create">
              <Plus className="size-4" />
              Nova recorrência
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar por descrição..."
            aria-label="Buscar recorrências"
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              updateParams({ search: event.target.value })
            }}
          />
        </FilterBar>

        <DataTable
          caption="Lista de recorrências"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(recurrence) => recurrence.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={CalendarClock}
              title={debouncedSearch ? 'Nenhum resultado encontrado' : 'Nenhuma recorrência cadastrada'}
              description={
                debouncedSearch
                  ? 'Tente ajustar os termos da busca.'
                  : 'Cadastre contas fixas como água, luz e internet.'
              }
            />
          }
        />

        {query.data && (
          <Pagination meta={query.data.meta} onPageChange={(next) => updateParams({ page: next })} />
        )}
      </PageContent>

      <ConfirmDialog
        open={recurrenceToDelete !== null}
        onClose={() => setRecurrenceToDelete(null)}
        onConfirm={confirmDelete}
        loading={deleteRecurrence.isPending}
        title="Excluir recorrência"
        description={
          <>
            Tem certeza que deseja excluir <strong>{recurrenceToDelete?.description}</strong>?
          </>
        }
        confirmLabel="Excluir"
      />
    </Page>
  )
}
