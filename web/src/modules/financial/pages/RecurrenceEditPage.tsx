import { useNavigate, useParams } from 'react-router'
import { CalendarClock } from 'lucide-react'
import {
  ButtonLink,
  Card,
  EmptyState,
  Page,
  PageContent,
  PageHeader,
  Skeleton,
} from '@/shared/design-system'
import { RecurrenceForm } from '../forms/RecurrenceForm'
import { useRecurrenceQuery, useUpdateRecurrence } from '../hooks/useRecurrences'

export default function RecurrenceEditPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const query = useRecurrenceQuery(id)
  const updateRecurrence = useUpdateRecurrence(id ?? '')

  return (
    <Page>
      <PageHeader
        title="Editar recorrência"
        description={query.data ? `Atualize os dados de ${query.data.description}.` : undefined}
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Recorrências', to: '/financial/recurrences' },
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
              icon={CalendarClock}
              title="Recorrência não encontrada"
              description="A recorrência pode ter sido removida ou você não possui acesso a ela."
              action={
                <ButtonLink to="/financial/recurrences" variant="secondary">
                  Voltar para a listagem
                </ButtonLink>
              }
            />
          </Card>
        )}

        {query.data && (
          <RecurrenceForm
            mode="edit"
            defaultValues={{
              description: query.data.description,
              default_value: query.data.default_value,
              due_day: String(query.data.due_day),
              frequency: query.data.frequency,
              supplier_id: query.data.supplier?.id ?? null,
              category_id: query.data.category?.id ?? null,
              active: query.data.active,
              generate_automatically: query.data.generate_automatically,
            }}
            submitting={updateRecurrence.isPending}
            onSubmit={async (payload) => {
              await updateRecurrence.mutateAsync(payload)
              navigate('/financial/recurrences')
            }}
          />
        )}
      </PageContent>
    </Page>
  )
}
