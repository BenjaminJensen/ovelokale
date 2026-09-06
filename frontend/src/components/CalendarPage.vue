<script setup lang="ts">
import { onMounted, ref } from 'vue'
import RehearsalCalendar from '@/components/RehearsalCalendar.vue'
import BookingDialog from '@/components/BookingDialog.vue'
import { fetchCalendarWeek } from '@/api/calendar'
import { fetchBands } from '@/api/bands'
import { mondaysInRange, toDateParam } from '@/utils/weeks'
import { CURRENT_USER_ID } from '@/currentUser'
import type { CalendarBand, CalendarBooking, CalendarOccurrence } from '@/types/calendar'

const bookings = ref<CalendarBooking[]>([])
const bands = ref<CalendarBand[]>([])
const ownBandIds = ref<number[]>([])
const error = ref<string | null>(null)
const dialogRange = ref<{ start: Date; end: Date } | null>(null)

onMounted(async () => {
  try {
    bands.value = await fetchBands()
  } catch (e) {
    // Band names/colors are a display nicety, not required for the calendar
    // to function — fall back to the "Booked" label rather than surfacing a
    // misleading "Could not load the calendar" error.
    console.error('Could not load bands:', e)
  }

  try {
    ownBandIds.value = (await fetchBands({ userId: CURRENT_USER_ID })).map((b) => b.id)
  } catch (e) {
    // Only affects which bands are bolded in the booking dialog.
    console.error("Could not load the current user's bands:", e)
  }
})

/** Guards against a slower, stale fetch (e.g. from a wider month view) overwriting a newer one. */
let latestRequestId = 0

function occurrenceId(occurrence: CalendarOccurrence): string {
  return occurrence.source === 'ad_hoc'
    ? `ad_hoc:${occurrence.id}`
    : `recurring:${occurrence.recurring_slot_id}:${occurrence.date}`
}

/** Backend datetimes are naive local timestamps (`Y-m-d H:i:s`); swapping in `T` keeps `new Date(...)` parsing them as local time. */
function toLocalIso(datetime: string): string {
  return datetime.replace(' ', 'T')
}

function toCalendarBooking(occurrence: CalendarOccurrence): CalendarBooking {
  return {
    id: occurrenceId(occurrence),
    start: toLocalIso(occurrence.start_time),
    end: toLocalIso(occurrence.end_time),
    bandId: occurrence.band_id,
  }
}

async function handleRangeChange({ from, to }: { from: Date; to: Date }): Promise<void> {
  const requestId = ++latestRequestId
  const weekStarts = mondaysInRange(from, to).map(toDateParam)

  try {
    const weeks = await Promise.all(weekStarts.map((weekStart) => fetchCalendarWeek(weekStart)))
    if (requestId !== latestRequestId) return

    const merged = new Map<string, CalendarBooking>()
    for (const week of weeks) {
      for (const occurrence of week.bookings) {
        const booking = toCalendarBooking(occurrence)
        merged.set(booking.id, booking)
      }
    }

    bookings.value = [...merged.values()]
    error.value = null
  } catch (e) {
    if (requestId !== latestRequestId) return
    error.value = e instanceof Error ? e.message : String(e)
  }
}

function handleCreate({ start, end }: { start: Date; end: Date }): void {
  dialogRange.value = { start, end }
}

function closeDialog(): void {
  dialogRange.value = null
}

function handleCreated(occurrence: CalendarOccurrence): void {
  const booking = toCalendarBooking(occurrence)
  bookings.value = [...bookings.value.filter((b) => b.id !== booking.id), booking]
  dialogRange.value = null
}
</script>

<template>
  <div>
    <p v-if="error" role="alert" class="calendar-error">Could not load the calendar: {{ error }}</p>
    <RehearsalCalendar
      :bookings="bookings"
      :bands="bands"
      @range-change="handleRangeChange"
      @create="handleCreate"
    />
    <BookingDialog
      v-if="dialogRange"
      :bands="bands"
      :own-band-ids="ownBandIds"
      :initial-start="dialogRange.start"
      :initial-end="dialogRange.end"
      @close="closeDialog"
      @created="handleCreated"
    />
  </div>
</template>

<style scoped>
.calendar-error {
  color: #b91c1c;
  margin: 0 0 12px;
}
</style>
