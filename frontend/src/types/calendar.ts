export type WeekParity = 'odd' | 'even' | 'all'

export interface AdHocOccurrence {
  source: 'ad_hoc'
  id: number
  band_id: number
  band_name: string | null
  start_time: string
  end_time: string
  booked_by_user_id: number
}

export interface RecurringOccurrence {
  source: 'recurring'
  recurring_slot_id: number
  band_id: number
  band_name: string | null
  day_of_week: number
  date: string
  start_time: string
  end_time: string
  week_parity: WeekParity
}

export type CalendarOccurrence = AdHocOccurrence | RecurringOccurrence

export interface CalendarWeekResponse {
  week_start: string
  week_end: string
  iso_week_number: number
  iso_year: number
  week_parity: WeekParity
  bookings: CalendarOccurrence[]
}

/** Flattened occurrence shape RehearsalCalendar.vue's `bookings` prop expects. */
export interface CalendarBooking {
  id: string
  start: string
  end: string
  bandId: number
  title?: string
}

export interface CalendarBand {
  id: number
  name: string
  color?: string
}
