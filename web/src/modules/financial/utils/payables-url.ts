import { resolvePresetRange, type DateRangePreset } from '@/shared/utils/date-range'

export function buildPayablesListUrl(preset: DateRangePreset = 'this_month'): string {
  const { from, to } = resolvePresetRange(preset)
  const params = new URLSearchParams({ from, to })

  return `/financial/payables?${params.toString()}`
}
