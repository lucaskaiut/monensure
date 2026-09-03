import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router'
import { FolderTree, Pencil, Plus, Trash2 } from 'lucide-react'
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
import type { Category } from '@/shared/types/models'
import { useCategoriesQuery, useDeleteCategory } from '../hooks/useCategories'

const PER_PAGE = 20

export default function CategoriesListPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [search, setSearch] = useState(searchParams.get('search') ?? '')
  const debouncedSearch = useDebounce(search)
  const page = Number(searchParams.get('page') ?? 1)

  const navigate = useNavigate()
  const { can } = usePermissions()

  const [categoryToDelete, setCategoryToDelete] = useState<Category | null>(null)
  const deleteCategory = useDeleteCategory()

  const query = useCategoriesQuery({ page, per_page: PER_PAGE, search: debouncedSearch || undefined })

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
    if (!categoryToDelete) return

    deleteCategory.mutate(categoryToDelete.id, { onSettled: () => setCategoryToDelete(null) })
  }

  const columns: Array<Column<Category>> = [
    {
      key: 'name',
      header: 'Categoria',
      render: (category) => <p className="font-medium text-foreground">{category.name}</p>,
    },
    {
      key: 'parent',
      header: 'Categoria pai',
      render: (category) => (
        <span className="text-muted">{category.parent?.name ?? '—'}</span>
      ),
    },
    ...(can(Permission.CATEGORY_UPDATE) || can(Permission.CATEGORY_DELETE)
      ? [
          {
            key: 'actions',
            header: <span className="sr-only">Ações</span>,
            className: 'w-24 text-right',
            render: (category: Category) => (
              <div className="flex items-center justify-end gap-1">
                {can(Permission.CATEGORY_UPDATE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => navigate(`/financial/categories/${category.id}/edit`)}
                    aria-label={`Editar ${category.name}`}
                  >
                    <Pencil className="size-4" />
                  </Button>
                )}
                {can(Permission.CATEGORY_DELETE) && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setCategoryToDelete(category)}
                    aria-label={`Excluir ${category.name}`}
                    className="text-danger hover:bg-danger-soft hover:text-danger"
                  >
                    <Trash2 className="size-4" />
                  </Button>
                )}
              </div>
            ),
          } satisfies Column<Category>,
        ]
      : []),
  ]

  return (
    <Page>
      <PageHeader
        title="Categorias"
        description="Organize as contas em categorias e subcategorias."
        breadcrumb={[{ label: 'Dashboard', to: '/dashboard' }, { label: 'Categorias' }]}
        actions={
          <Can permission={Permission.CATEGORY_CREATE}>
            <ButtonLink to="/financial/categories/create">
              <Plus className="size-4" />
              Nova categoria
            </ButtonLink>
          </Can>
        }
      />

      <PageContent>
        <FilterBar>
          <SearchInput
            placeholder="Buscar por nome..."
            aria-label="Buscar categorias"
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              updateParams({ search: event.target.value })
            }}
          />
        </FilterBar>

        <DataTable
          caption="Lista de categorias"
          columns={columns}
          rows={query.data?.data ?? []}
          rowKey={(category) => category.id}
          loading={query.isPending}
          emptyState={
            <EmptyState
              icon={FolderTree}
              title={debouncedSearch ? 'Nenhum resultado encontrado' : 'Nenhuma categoria cadastrada'}
              description={
                debouncedSearch
                  ? 'Tente ajustar os termos da busca.'
                  : 'Crie categorias para organizar suas contas.'
              }
            />
          }
        />

        {query.data && (
          <Pagination meta={query.data.meta} onPageChange={(next) => updateParams({ page: next })} />
        )}
      </PageContent>

      <ConfirmDialog
        open={categoryToDelete !== null}
        onClose={() => setCategoryToDelete(null)}
        onConfirm={confirmDelete}
        loading={deleteCategory.isPending}
        title="Excluir categoria"
        description={
          <>
            Tem certeza que deseja excluir <strong>{categoryToDelete?.name}</strong>?
          </>
        }
        confirmLabel="Excluir"
      />
    </Page>
  )
}
