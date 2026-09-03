/**
 * Rótulos amigáveis exibidos enquanto as ferramentas do agente são executadas.
 * Registre aqui o texto de cada ferramenta do seu projeto; o fallback genérico
 * é usado quando o nome não é mapeado.
 */
export const TOOL_RUNNING_LABELS: Record<string, string> = {
  create_payable: 'Criando conta a pagar...',
  create_installment_plan: 'Gerando parcelamento...',
  create_recurrence: 'Criando recorrência...',
  list_payables: 'Consultando contas a pagar...',
  mark_payable_paid: 'Registrando pagamento...',
  get_cashflow_projection: 'Calculando fluxo de caixa...',
  get_financial_summary: 'Consultando resumo financeiro...',
}

export const TOOL_DONE_LABELS: Record<string, string> = {
  create_payable: 'Conta criada',
  create_installment_plan: 'Parcelamento gerado',
  create_recurrence: 'Recorrência criada',
  list_payables: 'Contas consultadas',
  mark_payable_paid: 'Pagamento registrado',
  get_cashflow_projection: 'Fluxo de caixa calculado',
  get_financial_summary: 'Resumo financeiro consultado',
}

export function toolRunningLabel(name: string): string {
  return TOOL_RUNNING_LABELS[name] ?? 'Executando ferramenta...'
}

export function toolDoneLabel(name: string): string {
  return TOOL_DONE_LABELS[name] ?? 'Operação concluída'
}
