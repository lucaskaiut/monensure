import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader } from '@/shared/design-system'
import { RecurrenceForm } from '../forms/RecurrenceForm'
import { useCreateRecurrence } from '../hooks/useRecurrences'

export default function RecurrenceCreatePage() {
  const navigate = useNavigate()
  const createRecurrence = useCreateRecurrence()

  return (
    <Page>
      <PageHeader
        title="Nova recorrência"
        description="Cadastre uma conta fixa com geração automática."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Recorrências', to: '/financial/recurrences' },
          { label: 'Nova recorrência' },
        ]}
      />

      <PageContent>
        <RecurrenceForm
          mode="create"
          submitting={createRecurrence.isPending}
          onSubmit={async (payload) => {
            await createRecurrence.mutateAsync(payload)
            navigate('/financial/recurrences')
          }}
        />
      </PageContent>
    </Page>
  )
}
