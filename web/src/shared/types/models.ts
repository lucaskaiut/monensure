import type { Permission } from '@/shared/constants/permissions'

export interface Role {
  id: number
  name: string
  description: string | null
  permissions?: Permission[]
}

export interface User {
  id: string
  name: string
  email: string
  phone: string | null
  document: string | null
  is_master: boolean
  roles?: Role[]
  created_at: string | null
  updated_at: string | null
}

export interface Tenant {
  id: string
  name: string
  document: string
  email: string
  phone: string | null
  domain: string
  is_umbrella?: boolean
  users_count?: number
  subscription?: Subscription | null
  created_at: string | null
  updated_at: string | null
}

export interface AvailableTenant {
  id: string
  name: string
  is_home?: boolean
  is_umbrella?: boolean
}

export interface ApiToken {
  id: number
  name: string
  permissions: string[] | null
  last_used_at: string | null
  expires_at: string | null
  created_at: string | null
}

export interface Webhook {
  id: number
  name: string
  url: string
  method: string
  event: string
  headers: Record<string, string> | null
  query_params: Record<string, string> | null
  body_template: Record<string, unknown> | null
  is_active: boolean
  description: string | null
  created_at: string | null
  updated_at: string | null
}

export interface WebhookLog {
  id: number
  status_code: number | null
  response_body: string | null
  request_payload: Record<string, unknown> | null
  error_message: string | null
  duration_ms: number | null
  created_at: string | null
}

export interface AuditLog {
  id: number
  action: string
  entity_type: string | null
  entity_id: string | null
  details: Record<string, unknown> | null
  ip: string | null
  user: { id: string; name: string; email: string } | null
  created_at: string | null
}

export interface Session {
  user: User
  tenant: Tenant
  roles: Role[]
  permissions: Permission[]
  is_master: boolean
  available_tenants: AvailableTenant[]
}

export interface Plan {
  id: string
  name: string
  description: string | null
  price: string
  recurrence_value: number
  recurrence_unit: 'days' | 'weeks' | 'months' | 'years'
  free_trial_days: number
  trial_days?: number
  is_trial?: boolean
  requires_immediate_payment?: boolean
  active: boolean
  created_at: string | null
  updated_at: string | null
}

export interface SubscriptionEvent {
  id: number
  event: string
  payload: Record<string, unknown> | null
  created_at: string | null
}

export interface Subscription {
  id: string
  status: 'ACTIVE' | 'TRIALING' | 'PAST_DUE' | 'SUSPENDED' | 'CANCELLED'
  payment_gateway: string | null
  started_at: string | null
  trial_ends_at: string | null
  last_billed_at: string | null
  next_billing_at: string | null
  cancelled_at: string | null
  is_complimentary?: boolean
  is_complimentary_active?: boolean
  complimentary_ends_at?: string | null
  plan?: Plan
  events?: SubscriptionEvent[]
  created_at: string | null
  updated_at: string | null
}

export interface PaymentGatewayOption {
  key: string
  label: string
  payment_method: string
}

export interface Invoice {
  id: string
  gateway: string | null
  amount: string
  status: 'PENDING' | 'PROCESSING' | 'PAID' | 'EXPIRED' | 'FAILED' | 'CANCELLED'
  payment_method: 'pix' | 'credit_card' | 'boleto' | null
  external_id: string | null
  pix_code: string | null
  pix_qrcode: string | null
  invoice_url?: string | null
  awaiting_payment_method?: boolean
  due_date: string | null
  paid_at: string | null
  expires_at: string | null
  subscription?: Subscription
  created_at: string | null
  updated_at: string | null
}

export interface ConversationSummary {
  id: string
  title: string
  message_count: number | null
  last_message: string | null
  created_at: string | null
  updated_at: string | null
}

export interface AssistantMessage {
  id: string
  role: 'user' | 'assistant' | 'tool' | 'system'
  content: string
  tool_calls: Array<{ id: string; name: string; arguments: Record<string, unknown> }> | null
  tool_results: Array<{ id: string; name: string }> | null
  created_at: string | null
}

export interface Conversation {
  id: string
  title: string
  messages: AssistantMessage[]
  created_at: string | null
  updated_at: string | null
}

export interface AssistantSettings {
  enabled: boolean
  endpoint: string | null
  model: string | null
  temperature: number | null
  max_tokens: number | null
  additional_prompt: string | null
  has_api_key: boolean
  effective: {
    endpoint: string
    model: string
    temperature: number
    max_tokens: number | null
    has_api_key: boolean
  }
  defaults: {
    endpoint: string
    model: string
    temperature: number
    max_tokens: number | null
    has_api_key: boolean
  }
  updated_at: string | null
}

export interface AssistantConnectionTestResult {
  ok: boolean
  status: string
  message: string
}

export type PayableStatus = 'pendente' | 'pago' | 'cancelado'

export type RecurrenceFrequency = 'mensal' | 'bimestral' | 'trimestral' | 'semestral' | 'anual'

export interface Supplier {
  id: string
  name: string
  document: string | null
  phone: string | null
  email: string | null
  observations: string | null
  created_at: string | null
  updated_at: string | null
}

export interface Category {
  id: string
  name: string
  parent_id: string | null
  parent?: Category | null
  children?: Category[]
  created_at: string | null
  updated_at: string | null
}

export interface PayablePayment {
  id: string
  paid_at: string | null
  paid_value: string
  difference: string
  notes: string | null
  created_at: string | null
}

export interface Payable {
  id: string
  description: string
  value: string
  due_date: string | null
  issue_date: string | null
  status: PayableStatus
  is_installment: boolean
  installment_group_uuid: string | null
  installment_number: number | null
  installment_total: number | null
  installment_label: string | null
  paid_at: string | null
  paid_value: string | null
  supplier?: Supplier | null
  category?: Category | null
  payments?: PayablePayment[]
  created_at: string | null
  updated_at: string | null
}

export interface FinancialRecurrence {
  id: string
  description: string
  default_value: string
  due_day: number
  frequency: RecurrenceFrequency
  active: boolean
  generate_automatically: boolean
  last_generated_at: string | null
  supplier?: Supplier | null
  category?: Category | null
  created_at: string | null
  updated_at: string | null
}

export interface FinancialMetric {
  count: number
  total: number
}

export interface FinancialSummary {
  overdue: FinancialMetric
  due_today: FinancialMetric
  next_7_days: FinancialMetric
  next_30_days: FinancialMetric
  this_month: FinancialMetric
  open_total: number
}

export interface CashflowWindow {
  horizon_days: number
  label: string
  count: number
  total: number
}

export interface CashflowProjection {
  overdue: FinancialMetric
  windows: CashflowWindow[]
}
