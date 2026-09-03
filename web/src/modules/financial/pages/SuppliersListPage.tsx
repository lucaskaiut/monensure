import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { Pencil, Plus, Trash2, Truck } from 'lucide-react'
import {
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
import { formatDate } from '@/shared/utils/format'
import { formatDocument } from '@/shared/utils/document'
import type { Supplier } from '@/shared/types/models'
import { useDeleteSupplier, useSuppliersQuery } from '../hooks/useSuppliers'

const PER_PAGE = 10

export default function SuppliersListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)

  const navigate = useNavigate()
  const { can } = usePermissions()

  const [supplierToDelete, setSupplierToDelete] = useState<Supplier | null>(null)
  const deleteSupplier = useDeleteSupplier()

  const query = useSuppliersQuery({ page, per_page: PER_PAGE, search: debouncedSearch || undefined })

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
    if (!supplierToDelete) return

    deleteSupplier.mutate(supplierToDelete.id, { onSettled: () => setSupplierToDelete(null) })
  }

  const columns: Array<Column<Supplier>> = [
    {
      key: 'name',
      header: 'Fornecedor',
      render: (supplier) => (
        <div className="min-w-0">
          <p className="truncate font-medium text-foreground">{supplier.name}</p>
          {supplier.email && <p className="truncate text-[13px] text-muted">{supplier.email}</p>}
        </div>
      ),
    },
    {
      key: 'document',
      header: 'Documento',
      render: (supplier) => <span className="text-muted">{formatDocument(supplier.document)}</span>,
    },
    {
      key: 'phone',
      header: 'Telefone',
      render: (supplier) => <span className="text-muted">{supplier.phone ?? '—'}</span>,
    },
    {
      key: 'created_at',
      header: 'Criado em',
      render: (supplier) => <span className="text-muted">{formatDate(supplier.created_at)}</span>,
    },
    ...(can(Permission.SUPPLIER_UPDATE) || can(Permission.SUPPLIER_DELETE)
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-24 text-right',
            render: (supplier: Supplier) => (
              <div className="flex items-center justify-end gap-1">
                {can(Permission.SUPPLIER_UPDATE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => navigate(`/financial/suppliers/${supplier.id}/edit`)}
                    aria-label={`Editar ${supplier.name}`}
                  >
                    <Pencil className="size-4" />
                  </Button>
                )}
                {can(Permission.SUPPLIER_DELETE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setSupplierToDelete(supplier)}
                    aria-label={`Excluir ${supplier.name}`}
                    className="text-danger hover:bg-danger-soft hover:text-danger"
                  >
                    <Trash2 className="size-4" />
                  </Button>
                )}
              </div>
            ),
          } satisfies Column<Supplier>,
        ]
      : []),
  ]

  return (
    <Page>
      <PageHeader
        title="Fornecedores"
        description="Gerencie os fornecedores das suas contas a pagar."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Fornecedores' }]}
        actions={
          <Can permission={Permission.SUPPLIER_CREATE}>
            <ButtonLink to="/financial/suppliers/create">
              <Plus className="size-4" />
              Novo fornecedor
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar por nome, documento ou e-mail..."
            aria-label="Buscar fornecedores"
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              updateParams({ search: event.target.value })
            }}
          />
        </FilterBar>

        <DataTable
          caption="Lista de fornecedores"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(supplier) => supplier.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={Truck}
              title={debouncedSearch ? 'Nenhum resultado encontrado' : 'Nenhum fornecedor cadastrado'}
              description={
                debouncedSearch
                  ? 'Tente ajustar os termos da busca.'
                  : 'Comece cadastrando o primeiro fornecedor.'
              }
            />
          }
        />

        {query.data && (
          <Pagination meta={query.data.meta} onPageChange={(next) => updateParams({ page: next })} />
        )}
      </PageContent>

      <ConfirmDialog
        open={supplierToDelete !== null}
        onClose={() => setSupplierToDelete(null)}
        onConfirm={confirmDelete}
        loading={deleteSupplier.isPending}
        title="Excluir fornecedor"
        description={
          <>
            Tem certeza que deseja excluir <strong>{supplierToDelete?.name}</strong>? Esta ação não pode
            ser desfeita.
          </>
        }
        confirmLabel="Excluir"
      />
    </Page>
  )
}
