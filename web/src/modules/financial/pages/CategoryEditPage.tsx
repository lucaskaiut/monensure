import { useNavigate, useParams } from 'react-router'
import { FolderTree } from 'lucide-react'
import {
  ButtonLink,
  Card,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Skeleton,
} from '@/shared/design-system'
import { CategoryForm } from '../forms/CategoryForm'
import { useCategoryQuery, useUpdateCategory } from '../hooks/useCategories'

export default function CategoryEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const query = useCategoryQuery(id)
  const updateCategory = useUpdateCategory(id ?? '')

  return (
    <Page>
      <PageHeader
        title="Editar categoria"
        description={query.data ? `Atualize os dados de ${query.data.name}.` : undefined}
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Categorias', to: '/financial/categories' },
          { label: 'Editar' },
        ]}
      />

      <PageContent>
        {query.isPending && (
          <Card>
            <Skeleton className="h-64" />
          </Card>
        )}

        {query.isError && (
          <Card>
            <EmptyState
              icon={FolderTree}
              title="Categoria não encontrada"
              description="A categoria pode ter sido removida ou você não possui acesso a ela."
              action={
                <ButtonLink to="/financial/categories" variant="secondary">
                  Voltar para a listagem
                </ButtonLink>
              }
            />
          </Card>
        )}

        {query.data && (
          <CategoryForm
            mode="edit"
            excludeId={query.data.id}
            defaultValues={{
              name: query.data.name,
              parent_id: query.data.parent_id,
            }}
            submitting={updateCategory.isPending}
            onSubmit={async (payload) => {
              await updateCategory.mutateAsync(payload)
              navigate('/financial/categories')
            }}
          />
        )}
      </PageContent>
    </Page>
  )
}
