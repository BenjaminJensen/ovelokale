import type { CalendarBand } from '@/types/calendar'

export async function fetchBands(): Promise<CalendarBand[]> {
  const res = await fetch('/api/bands')
  if (!res.ok) {
    throw new Error(`GET /api/bands failed with status ${res.status}`)
  }

  const data = (await res.json()) as { bands: CalendarBand[] }
  return data.bands
}
