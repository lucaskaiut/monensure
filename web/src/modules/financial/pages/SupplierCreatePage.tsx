import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { SupplierForm } from '../forms/SupplierForm'
import { useCreateSupplier } from '../hooks/useSuppliers'

export default function SupplierCreatePage() {
  const navigate = useNavigate()
  const createSupplier = useCreateSupplier()

  return (
    <Page>
      <PageHeader
        title="Novo fornecedor"
        description="Cadastre um novo fornecedor."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Fornecedores', to: '/financial/suppliers' },
          { label: 'Novo fornecedor' },
        ]}
      />

      <PageContent>
        <SupplierForm
          mode="create"
          submitting={createSupplier.isPending}
          onSubmit={async (payload) => {
            await createSupplier.mutateAsync(payload)
            navigate('/financial/suppliers')
          }}
        />
      </PageContent>
    </Page>
  )
}
