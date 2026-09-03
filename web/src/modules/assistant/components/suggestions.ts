import {
  BookOpenCheck,
  Compass,
  Lightbulb,
  Sparkles,
  type LucideIcon,
} from 'lucide-react'

export interface SuggestionCard {
  title: string
  description: string
  prompt: string
  icon: LucideIcon
  tint: 'primary' | 'success' | 'warning' | 'danger'
}

/**
 * Exemplos exibidos na tela inicial do assistente.
 * Cada projeto pode substituir estes prompts pelos do seu domínio.
 */
export const SUGGESTIONS: SuggestionCard[] = [
  {
    title: 'Cadastrar conta recorrente',
    description: 'Crie uma conta fixa que se repete todo mês',
    prompt: 'Cadastre uma conta de internet de R$ 120 que vence todo dia 10.',
    icon: Sparkles,
    tint: 'primary',
  },
  {
    title: 'Cadastrar parcelamento',
    description: 'Gere várias parcelas de uma só vez',
    prompt: 'Cadastre o financiamento do carro em 36 parcelas de R$ 3.495.',
    icon: BookOpenCheck,
    tint: 'success',
  },
  {
    title: 'Quanto tenho para pagar?',
    description: 'Consulte o resumo e o fluxo de caixa',
    prompt: 'Quanto tenho para pagar este mês?',
    icon: Compass,
    tint: 'warning',
  },
  {
    title: 'Contas vencidas',
    description: 'Veja o que está em atraso',
    prompt: 'Quais contas estão vencidas?',
    icon: Lightbulb,
    tint: 'danger',
  },
]

export const TINT_CLASSES: Record<SuggestionCard['tint'], { icon: string; iconBg: string }> = {
  primary: { icon: 'text-primary', iconBg: 'bg-primary-soft' },
  success: { icon: 'text-success', iconBg: 'bg-success-soft' },
  warning: { icon: 'text-warning', iconBg: 'bg-warning-soft' },
  danger: { icon: 'text-danger', iconBg: 'bg-danger-soft' },
}
