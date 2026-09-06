import type { CalendarBand } from '@/types/calendar'

export async function fetchBands(opts?: { userId?: number }): Promise<CalendarBand[]> {
  const params = new URLSearchParams()
  if (opts?.userId !== undefined) {
    params.set('user_id', String(opts.userId))
  }

  const query = params.toString()
  const res = await fetch(`/api/bands${query ? `?${query}` : ''}`)
  if (!res.ok) {
    throw new Error(`GET /api/bands failed with status ${res.status}`)
  }

  const data = (await res.json()) as { bands: CalendarBand[] }
  return data.bands
}
