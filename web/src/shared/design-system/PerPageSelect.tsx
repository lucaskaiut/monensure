import { useId } from 'react'
import { cn } from '@/shared/utils/cn'
import { Select } from './Select'

export const DEFAULT_PER_PAGE_OPTIONS = [10, 25, 50, 100] as const

export function PerPageSelect({
  value,
  onChange,
  options = DEFAULT_PER_PAGE_OPTIONS,
  className,
}: {
  value: number
  onChange: (perPage: number) => void
  options?: readonly number[]
  className?: string
}) {
  const id = useId()

  return (
    <div className={cn('flex items-center gap-2', className)}>
      <label htmlFor={id} className="text-[13px] whitespace-nowrap text-muted">
        Por página
      </label>
      <Select
        id={id}
        className="h-8 w-20 pl-3 text-[13px]"
        value={String(value)}
        onChange={(event) => onChange(Number(event.target.value))}
        options={options.map((option) => ({ value: String(option), label: String(option) }))}
      />
    </div>
  )
}

/**
 * Lê `per_page` de um valor de query string, aceitando apenas as opções permitidas.
 */
export function parsePerPage(
  raw: string | null,
  options: readonly number[] = DEFAULT_PER_PAGE_OPTIONS,
  fallback: number = options[0],
): number {
  const value = Number(raw)

  return options.includes(value) ? value : fallback
}
