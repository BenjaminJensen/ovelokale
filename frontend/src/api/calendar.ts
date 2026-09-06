import type { CalendarWeekResponse } from '@/types/calendar'

export async function fetchCalendarWeek(
  weekStart: string,
  opts?: { bandId?: number },
): Promise<CalendarWeekResponse> {
  const params = new URLSearchParams({ week_start: weekStart })
  if (opts?.bandId !== undefined) {
    params.set('band_id', String(opts.bandId))
  }

  const res = await fetch(`/api/calendar?${params.toString()}`)
  if (!res.ok) {
    throw new Error(`GET /api/calendar failed with status ${res.status}`)
  }

  return (await res.json()) as CalendarWeekResponse
}
