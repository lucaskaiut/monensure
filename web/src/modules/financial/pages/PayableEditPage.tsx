import { useNavigate, useParams } from 'react-router'
import { Receipt } from 'lucide-react'
import {
  ButtonLink,
  Card,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Skeleton,
} from '@/shared/design-system'
import { PayableEditForm } from '../forms/PayableEditForm'
import { usePayableQuery, useUpdatePayable } from '../hooks/usePayables'

export default function PayableEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const query = usePayableQuery(id)
  const updatePayable = useUpdatePayable(id ?? '')

  return (
    <Page>
      <PageHeader
        title="Editar conta"
        description={query.data ? `Atualize os dados de ${query.data.description}.` : undefined}
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Contas a pagar', to: '/financial/payables' },
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
              icon={Receipt}
              title="Conta não encontrada"
              description="A conta pode ter sido removida ou você não possui acesso a ela."
              action={
                <ButtonLink to="/financial/payables" variant="secondary">
                  Voltar para a listagem
                </ButtonLink>
              }
            />
          </Card>
        )}

        {query.data && (
          <PayableEditForm
            payable={query.data}
            submitting={updatePayable.isPending}
            onSubmit={async (payload) => {
              await updatePayable.mutateAsync(payload)
              navigate('/financial/payables')
            }}
          />
        )}
      </PageContent>
    </Page>
  )
}
