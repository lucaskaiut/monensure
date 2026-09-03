import { useState } from 'react'
import { useNavigate } from 'react-router'
import { Page, PageContent, PageHeader, SegmentedControl } from '@/shared/design-system'
import { PayableForm } from '../forms/PayableForm'
import { InstallmentPlanForm } from '../forms/InstallmentPlanForm'
import { useCreateInstallmentPlan, useCreatePayable } from '../hooks/usePayables'

export default function PayableCreatePage() {
  const navigate = useNavigate()
  const [type, setType] = useState<'single' | 'installment'>('single')

  const createPayable = useCreatePayable()
  const createInstallmentPlan = useCreateInstallmentPlan()

  return (
    <Page>
      <PageHeader
        title="Nova conta a pagar"
        description="Cadastre uma conta avulsa ou um parcelamento."
        breadcrumb={[
          { label: 'Dashboard', to: '/dashboard' },
          { label: 'Contas a pagar', to: '/financial/payables' },
          { label: 'Nova conta' },
        ]}
      />

      <PageContent>
        <SegmentedControl
          value={type}
          onChange={setType}
          options={[
            { value: 'single', label: 'Conta avulsa' },
            { value: 'installment', label: 'Parcelamento' },
          ]}
        />

        {type === 'single' ? (
          <PayableForm
            submitting={createPayable.isPending}
            onSubmit={async (payload) => {
              await createPayable.mutateAsync(payload)
              navigate('/financial/payables')
            }}
          />
        ) : (
          <InstallmentPlanForm
            submitting={createInstallmentPlan.isPending}
            onSubmit={async (payload) => {
              await createInstallmentPlan.mutateAsync(payload)
              navigate('/financial/payables')
            }}
          />
        )}
      </PageContent>
    </Page>
  )
}
