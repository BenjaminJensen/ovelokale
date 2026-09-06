import type { AdHocOccurrence, WeekParity } from '@/types/calendar'

export interface CreateRecurringSlotPayload {
  bandId: number
  /** `Y-m-d`; its weekday becomes the slot's day_of_week (per ADR 0001). */
  startDate: string
  /** `Y-m-d`, or null for an indefinite slot. */
  endDate: string | null
  /** `H:i`. */
  startTime: string
  /** `H:i`. */
  endTime: string
  weekParity: WeekParity
}

export interface RecurringSlot {
  id: number
  band_id: number
  band_name: string | null
  day_of_week: number
  start_time: string
  end_time: string
  week_parity: WeekParity
  start_date: string
  end_date: string | null
}

/**
 * A conflict against an *existing recurring slot's pattern* (as opposed to
 * one dated occurrence of it) — reported when creating a new recurring
 * slot, since the check spans every week the pattern would ever apply.
 */
export interface RecurringPatternConflict {
  source: 'recurring_pattern'
  id: number
  band_id: number
  band_name: string | null
  day_of_week: number
  /** `H:i:s`, no date — the conflict isn't tied to one occurrence. */
  start_time: string
  /** `H:i:s`. */
  end_time: string
  week_parity: WeekParity
  start_date: string
  end_date: string | null
}

export type RecurringSlotConflict = AdHocOccurrence | RecurringPatternConflict

/** Thrown when the server rejects a recurring slot because it conflicts with an existing occurrence (per ADR 0002). */
export class RecurringSlotConflictError extends Error {
  conflicts: RecurringSlotConflict[]

  constructor(message: string, conflicts: RecurringSlotConflict[]) {
    super(message)
    this.name = 'RecurringSlotConflictError'
    this.conflicts = conflicts
  }
}

export async function createRecurringSlot(
  payload: CreateRecurringSlotPayload,
): Promise<RecurringSlot> {
  const res = await fetch('/api/recurring-slots', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      band_id: payload.bandId,
      start_date: payload.startDate,
      end_date: payload.endDate,
      start_time: payload.startTime,
      end_time: payload.endTime,
      week_parity: payload.weekParity,
    }),
  })

  const data = (await res.json()) as {
    error?: string
    conflicts?: RecurringSlotConflict[]
    recurring_slot?: RecurringSlot
  }

  if (res.status === 409) {
    throw new RecurringSlotConflictError(
      data.error ?? 'This pattern conflicts with an existing occurrence',
      data.conflicts ?? [],
    )
  }

  if (!res.ok || !data.recurring_slot) {
    throw new Error(data.error ?? `POST /api/recurring-slots failed with status ${res.status}`)
  }

  return data.recurring_slot
}

export interface FetchRecurringSlotConflictsParams {
  startDate: string
  endDate: string | null
  startTime: string
  endTime: string
  weekParity: WeekParity
}

export async function fetchRecurringSlotConflicts(
  params: FetchRecurringSlotConflictsParams,
): Promise<RecurringSlotConflict[]> {
  const query = new URLSearchParams({
    start_date: params.startDate,
    start_time: params.startTime,
    end_time: params.endTime,
    week_parity: params.weekParity,
  })
  if (params.endDate !== null) query.set('end_date', params.endDate)

  const res = await fetch(`/api/recurring-slots/conflicts?${query.toString()}`)

  if (!res.ok) {
    throw new Error(`GET /api/recurring-slots/conflicts failed with status ${res.status}`)
  }

  const data = (await res.json()) as { conflicts: RecurringSlotConflict[] }
  return data.conflicts
}
