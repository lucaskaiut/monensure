import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { CategoryForm } from '../forms/CategoryForm'
import { useCreateCategory } from '../hooks/useCategories'

export default function CategoryCreatePage() {
  const navigate = useNavigate()
  const createCategory = useCreateCategory()

  return (
    <Page>
      <PageHeader
        title="Nova categoria"
        description="Cadastre uma nova categoria."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Categorias', to: '/financial/categories' },
          { label: 'Nova categoria' },
        ]}
      />

      <PageContent>
        <CategoryForm
          mode="create"
          submitting={createCategory.isPending}
          onSubmit={async (payload) => {
            await createCategory.mutateAsync(payload)
            navigate('/financial/categories')
          }}
        />
      </PageContent>
    </Page>
  )
}
