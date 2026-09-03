import { useCallback, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { Ban, CircleDollarSign, Pencil, Plus, Receipt, Trash2 } from 'lucide-react'
import {
  Button,
  ButtonLink,
  ConfirmDialog,
  DataTable,
  DateRangeFilter,
  EmptyState,
  FilterBar,
  Page,
  PageContent,
  PageHeader,
  Pagination,
  SearchInput,
  SearchSelect,
  Select,
  type Column,
  type SearchSelectOption,
} from '@/shared/design-system'
import { Can } from '@/app/guards/PermissionGuard'
import { Permission } from '@/shared/constants/permissions'
import { usePermissions } from '@/shared/hooks/usePermissions'
import { useDebounce } from '@/shared/hooks/useDebounce'
import { formatCurrency, formatDate } from '@/shared/utils/format'
import type { Payable, PayableStatus } from '@/shared/types/models'
import { categoriesService } from '../services/categories.service'
import { suppliersService } from '../services/suppliers.service'
import {
  useCancelPayable,
  useDeletePayable,
  usePayPayable,
  usePayablesQuery,
} from '../hooks/usePayables'
import { PayableStatusBadge } from '../components/PayableStatusBadge'
import { PayPayableDialog } from '../components/PayPayableDialog'
import { CancelPayableDialog } from '../components/CancelPayableDialog'

const PER_PAGE = 10

const STATUS_OPTIONS: Array<{ value: string; label: string }> = [
  { value: 'pendente', label: 'Pendente' },
  { value: 'pago', label: 'Pago' },
  { value: 'cancelado', label: 'Cancelado' },
]

export default function PayablesListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)
  const status = searchParams.get('status') ?? ''
  const supplierId = searchParams.get('supplier_id') ?? ''
  const categoryId = searchParams.get('category_id') ?? ''
  const from = searchParams.get('from') ?? ''
  const to = searchParams.get('to') ?? ''

  const navigate = useNavigate()
  const { can } = usePermissions()

  const loadSuppliers = useCallback(async (search: string): Promise<SearchSelectOption[]> => {
    const result = await suppliersService.list({ search: search || undefined, per_page: 20 })

    return result.data.map((supplier) => ({ value: supplier.id, label: supplier.name }))
  }, [])

  const resolveSupplierLabel = useCallback(async (id: string): Promise<SearchSelectOption | null> => {
    try {
      const supplier = await suppliersService.get(id)

      return { value: supplier.id, label: supplier.name }
    } catch {
      return null
    }
  }, [])

  const loadCategories = useCallback(async (search: string): Promise<SearchSelectOption[]> => {
    const result = await categoriesService.list({ search: search || undefined, per_page: 20 })

    return result.data.map((category) => ({ value: category.id, label: category.name }))
  }, [])

  const resolveCategoryLabel = useCallback(async (id: string): Promise<SearchSelectOption | null> => {
    try {
      const category = await categoriesService.get(id)

      return { value: category.id, label: category.name }
    } catch {
      return null
    }
  }, [])

  const [payableToPay, setPayableToPay] = useState<Payable | null>(null)
  const [payableToCancel, setPayableToCancel] = useState<Payable | null>(null)
  const [payableToDelete, setPayableToDelete] = useState<Payable | null>(null)

  const payPayable = usePayPayable(payableToPay?.id ?? '')
  const cancelPayable = useCancelPayable()
  const deletePayable = useDeletePayable()

  const query = usePayablesQuery({
    page,
    per_page: PER_PAGE,
    search: debouncedSearch || undefined,
    status: status || undefined,
    supplier_id: supplierId || undefined,
    category_id: categoryId || undefined,
    from: from || undefined,
    to: to || undefined,
  })

  const setParam = (key: string, value: string) => {
    setSearchParams(
      (params) => {
        value ? params.set(key, value) : params.delete(key)
        if (key !== 'page') params.delete('page')
        return params
      },
      { replace: true },
    )
  }

  const confirmDelete = () => {
    if (!payableToDelete) return

    deletePayable.mutate(payableToDelete.id, { onSettled: () => setPayableToDelete(null) })
  }

  const columns: Array<Column<Payable>> = [
    {
      key: 'description',
      header: 'Descrição',
      render: (payable) => (
        <div className="min-w-0">
          <p className="truncate font-medium text-foreground">
            {payable.description}
            {payable.installment_label && (
              <span className="ml-2 text-[13px] font-normal text-muted">
                {payable.installment_label}
              </span>
            )}
          </p>
          <p className="truncate text-[13px] text-muted">{payable.supplier?.name ?? '—'}</p>
        </div>
      ),
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
      key: 'category',
      header: 'Categoria',
      render: (payable) => <span className="text-muted">{payable.category?.name ?? '—'}</span>,
    },
    {
      key: 'status',
      header: 'Status',
      render: (payable) => <PayableStatusBadge status={payable.status as PayableStatus} />,
    },
    {
      key: 'actions',
      header: <span className="sr-only">Ações</span>,
      className: 'w-32 text-right',
      render: (payable: Payable) => (
        <div className="flex items-center justify-end gap-1">
          {payable.status === 'pendente' && can(Permission.PAYABLE_PAY) && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setPayableToPay(payable)}
              aria-label={`Pagar ${payable.description}`}
            >
              <CircleDollarSign className="size-4" />
            </Button>
          )}
          {can(Permission.PAYABLE_UPDATE) && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => navigate(`/financial/payables/${payable.id}/edit`)}
              aria-label={`Editar ${payable.description}`}
            >
              <Pencil className="size-4" />
            </Button>
          )}
          {payable.status === 'pendente' && can(Permission.PAYABLE_UPDATE) && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setPayableToCancel(payable)}
              aria-label={`Cancelar ${payable.description}`}
            >
              <Ban className="size-4" />
            </Button>
          )}
          {can(Permission.PAYABLE_DELETE) && (
            <Button
              variant="ghost"
              size="sm"
              onClick={() => setPayableToDelete(payable)}
              aria-label={`Excluir ${payable.description}`}
              className="text-danger hover:bg-danger-soft hover:text-danger"
            >
              <Trash2 className="size-4" />
            </Button>
          )}
        </div>
      ),
    },
  ]

  return (
    <Page>
      <PageHeader
        title="Contas a pagar"
        description="Gerencie contas, parcelamentos e pagamentos."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Contas a pagar' }]}
        actions={
          <Can permission={Permission.PAYABLE_CREATE}>
            <ButtonLink to="/financial/payables/create">
              <Plus className="size-4" />
              Nova conta
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar por descrição..."
            aria-label="Buscar contas"
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              setParam('search', event.target.value)
            }}
          />
          <div className="flex flex-wrap items-center gap-2">
            <Select
              className="w-40"
              value={status}
              onChange={(event) => setParam('status', event.target.value)}
              options={STATUS_OPTIONS}
              placeholder="Status"
              aria-label="Filtrar por status"
            />
            <SearchSelect
              className="w-48"
              value={supplierId}
              onChange={(value) => setParam('supplier_id', value)}
              loadOptions={loadSuppliers}
              resolveLabel={resolveSupplierLabel}
              placeholder="Fornecedor"
            />
            <SearchSelect
              className="w-48"
              value={categoryId}
              onChange={(value) => setParam('category_id', value)}
              loadOptions={loadCategories}
              resolveLabel={resolveCategoryLabel}
              placeholder="Categoria"
            />
            <DateRangeFilter
              className="min-w-64 flex-1"
              label="Vencimento:"
              from={from}
              to={to}
              showClear
              onChange={({ from: nextFrom, to: nextTo }) => {
                setParam('from', nextFrom)
                setParam('to', nextTo)
              }}
            />
          </div>
        </FilterBar>

        <DataTable
          caption="Lista de contas a pagar"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(payable) => payable.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={Receipt}
              title={debouncedSearch || status ? 'Nenhum resultado encontrado' : 'Nenhuma conta a pagar'}
              description={
                debouncedSearch || status
                  ? 'Tente ajustar os filtros.'
                  : 'Comece cadastrando sua primeira conta a pagar.'
              }
            />
          }
        />

        {query.data && (
          <Pagination meta={query.data.meta} onPageChange={(next) => setParam('page', String(next))} />
        )}
      </PageContent>

      <PayPayableDialog
        key={`pay-${payableToPay?.id ?? 'none'}`}
        payable={payableToPay}
        open={payableToPay !== null}
        loading={payPayable.isPending}
        onClose={() => setPayableToPay(null)}
        onConfirm={(payload) =>
          payPayable.mutate(payload, { onSettled: () => setPayableToPay(null) })
        }
      />

      <CancelPayableDialog
        key={`cancel-${payableToCancel?.id ?? 'none'}`}
        payable={payableToCancel}
        open={payableToCancel !== null}
        loading={cancelPayable.isPending}
        onClose={() => setPayableToCancel(null)}
        onConfirm={(scope) =>
          cancelPayable.mutate(
            { id: payableToCancel!.id, scope },
            { onSettled: () => setPayableToCancel(null) },
          )
        }
      />

      <ConfirmDialog
        open={payableToDelete !== null}
        onClose={() => setPayableToDelete(null)}
        onConfirm={confirmDelete}
        loading={deletePayable.isPending}
        title="Excluir conta"
        description={
          <>
            Tem certeza que deseja excluir <strong>{payableToDelete?.description}</strong>? Esta ação
            não pode ser desfeita.
          </>
        }
        confirmLabel="Excluir"
      />
    </Page>
  )
}
