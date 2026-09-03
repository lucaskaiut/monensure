import { useNavigate, useParams } from 'react-router'
import { Truck } from 'lucide-react'
import {
  ButtonLink,
  Card,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Skeleton,
} from '@/shared/design-system'
import { SupplierForm } from '../forms/SupplierForm'
import { useSupplierQuery, useUpdateSupplier } from '../hooks/useSuppliers'

export default function SupplierEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const query = useSupplierQuery(id)
  const updateSupplier = useUpdateSupplier(id ?? '')

  return (
    <Page>
      <PageHeader
        title="Editar fornecedor"
        description={query.data ? `Atualize os dados de ${query.data.name}.` : undefined}
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Fornecedores', to: '/financial/suppliers' },
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
              icon={Truck}
              title="Fornecedor não encontrado"
              description="O fornecedor pode ter sido removido ou você não possui acesso a ele."
              action={
                <ButtonLink to="/financial/suppliers" variant="secondary">
                  Voltar para a listagem
                </ButtonLink>
              }
            />
          </Card>
        )}

        {query.data && (
          <SupplierForm
            mode="edit"
            defaultValues={{
              name: query.data.name,
              document: query.data.document ?? '',
              phone: query.data.phone ?? '',
              email: query.data.email ?? '',
              observations: query.data.observations ?? '',
            }}
            submitting={updateSupplier.isPending}
            onSubmit={async (payload) => {
              await updateSupplier.mutateAsync(payload)
              navigate('/financial/suppliers')
            }}
          />
        )}
      </PageContent>
    </Page>
  )
}
