import type { AdHocOccurrence, CalendarOccurrence } from '@/types/calendar'

export interface CreateBookingPayload {
  bandId: number
  /** Naive local datetime, `Y-m-d H:i:s`. */
  startTime: string
  /** Naive local datetime, `Y-m-d H:i:s`. */
  endTime: string
}

/** Thrown when the server rejects a booking because it conflicts with an existing occurrence (per ADR 0002). */
export class BookingConflictError extends Error {
  conflicts: CalendarOccurrence[]

  constructor(message: string, conflicts: CalendarOccurrence[]) {
    super(message)
    this.name = 'BookingConflictError'
    this.conflicts = conflicts
  }
}

export async function createBooking(payload: CreateBookingPayload): Promise<AdHocOccurrence> {
  const res = await fetch('/api/bookings', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      band_id: payload.bandId,
      start_time: payload.startTime,
      end_time: payload.endTime,
    }),
  })

  const data = (await res.json()) as {
    error?: string
    conflicts?: CalendarOccurrence[]
    booking?: AdHocOccurrence
  }

  if (res.status === 409) {
    throw new BookingConflictError(
      data.error ?? 'This slot conflicts with an existing booking',
      data.conflicts ?? [],
    )
  }

  if (!res.ok || !data.booking) {
    throw new Error(data.error ?? `POST /api/bookings failed with status ${res.status}`)
  }

  return data.booking
}

/** Naive local datetimes, `Y-m-d H:i:s`. */
export async function fetchConflicts(
  startTime: string,
  endTime: string,
): Promise<CalendarOccurrence[]> {
  const params = new URLSearchParams({ start_time: startTime, end_time: endTime })
  const res = await fetch(`/api/bookings/conflicts?${params.toString()}`)

  if (!res.ok) {
    throw new Error(`GET /api/bookings/conflicts failed with status ${res.status}`)
  }

  const data = (await res.json()) as { conflicts: CalendarOccurrence[] }
  return data.conflicts
}
