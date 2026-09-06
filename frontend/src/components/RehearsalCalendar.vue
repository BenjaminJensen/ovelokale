<script setup lang="ts">
/**
 * RehearsalCalendar.vue — month + week view for a single rehearsal room.
 *
 * Stack-agnostic: it renders whatever bookings you hand it and tells the
 * parent which date range is on screen, so the parent owns fetching.
 */
import { computed, ref, watch } from 'vue'
import type { CalendarBand, CalendarBooking } from '@/types/calendar'

interface Props {
  bookings?: CalendarBooking[]
  bands?: CalendarBand[]
  locale?: string
  dayStartHour?: number
  dayEndHour?: number
  slotMinutes?: number
  initialView?: 'month' | 'week'
  initialDate?: Date
}

const props = withDefaults(defineProps<Props>(), {
  bookings: () => [],
  bands: () => [],
  locale: 'da-DK',
  dayStartHour: 8,
  dayEndHour: 24,
  slotMinutes: 60,
  initialView: 'month',
  initialDate: () => new Date(),
})

interface ParsedBooking extends CalendarBooking {
  startAt: Date
  endAt: Date
}

const emit = defineEmits<{
  'range-change': [range: { from: Date; to: Date }] // parent fetches for this range
  create: [range: { start: Date; end: Date }]
  select: [booking: ParsedBooking]
}>()

/* ---------- date helpers (all local time, Monday-first, ISO weeks) ---------- */

const DAY = 86400000

const startOfDay = (d: Date): Date => new Date(d.getFullYear(), d.getMonth(), d.getDate())
const addDays = (d: Date, n: number): Date =>
  new Date(d.getFullYear(), d.getMonth(), d.getDate() + n)
const addMonths = (d: Date, n: number): Date => new Date(d.getFullYear(), d.getMonth() + n, 1)
const sameDay = (a: Date, b: Date): boolean => a.toDateString() === b.toDateString()
const dayKey = (d: Date): string => `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`

/** Monday = start of week. */
function startOfWeek(d: Date): Date {
  const s = startOfDay(d)
  const shift = (s.getDay() + 6) % 7
  return addDays(s, -shift)
}

/** ISO-8601 week number — the one on Danish wall calendars. */
function isoWeek(d: Date): number {
  const t = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()))
  t.setUTCDate(t.getUTCDate() + 4 - (t.getUTCDay() || 7))
  const yearStart = new Date(Date.UTC(t.getUTCFullYear(), 0, 1))
  return Math.ceil(((t.getTime() - yearStart.getTime()) / DAY + 1) / 7)
}

/* ---------- view state ---------- */

const view = ref(props.initialView)
const cursor = ref(startOfDay(props.initialDate))
const today = startOfDay(new Date())

const dayNames = computed(() => {
  const fmt = new Intl.DateTimeFormat(props.locale, { weekday: 'short' })
  const monday = startOfWeek(new Date(2024, 0, 1))
  return Array.from({ length: 7 }, (_, i) => fmt.format(addDays(monday, i)))
})

const periodLabel = computed(() => {
  if (view.value === 'week') {
    const days = weekDays.value
    const a = days[0]!
    const b = days[6]!
    const month = new Intl.DateTimeFormat(props.locale, { month: 'long', year: 'numeric' })
    return `${a.getDate()}.–${b.getDate()}. ${month.format(b)}`
  }
  return new Intl.DateTimeFormat(props.locale, { month: 'long', year: 'numeric' }).format(
    cursor.value,
  )
})

const weekDays = computed(() => {
  const start = startOfWeek(cursor.value)
  return Array.from({ length: 7 }, (_, i) => addDays(start, i))
})

/** Month grid: whole weeks covering the month, 4–6 rows. */
const monthWeeks = computed(() => {
  const first = new Date(cursor.value.getFullYear(), cursor.value.getMonth(), 1)
  const last = new Date(cursor.value.getFullYear(), cursor.value.getMonth() + 1, 0)
  const weeks = []
  let d = startOfWeek(first)
  while (d <= last) {
    weeks.push(Array.from({ length: 7 }, (_, i) => addDays(d, i)))
    d = addDays(d, 7)
  }
  return weeks
})

const hours = computed(() => {
  const out = []
  for (let h = props.dayStartHour; h < props.dayEndHour; h++) out.push(h)
  return out
})

const visibleRange = computed(() => {
  if (view.value === 'week') {
    const days = weekDays.value
    return { from: days[0]!, to: addDays(days[6]!, 1) }
  }
  const weeks = monthWeeks.value
  const firstWeek = weeks[0]!
  const lastWeek = weeks[weeks.length - 1]!
  return { from: firstWeek[0]!, to: addDays(lastWeek[6]!, 1) }
})

watch(visibleRange, (r) => emit('range-change', r), { immediate: true })

function step(dir: number): void {
  cursor.value =
    view.value === 'week' ? addDays(cursor.value, 7 * dir) : addMonths(cursor.value, dir)
}
function goToday(): void {
  cursor.value = today
}
function switchView(v: 'month' | 'week'): void {
  view.value = v
}
function selectWeek(week: Date[]): void {
  cursor.value = week[0]!
  switchView('week')
}

/* ---------- bookings ---------- */

const PALETTE = ['#3E63DD', '#0E9F6E', '#B4530A', '#8B5CF6', '#0891B2', '#BE185D', '#65A30D']

const bandMap = computed(() => {
  const m = new Map<number, CalendarBand & { color: string }>()
  props.bands.forEach((b, i) => {
    m.set(b.id, { ...b, color: b.color ?? PALETTE[i % PALETTE.length]! })
  })
  return m
})

function band(booking: CalendarBooking): { name: string; color: string } {
  return bandMap.value.get(booking.bandId) ?? { name: booking.title ?? 'Booked', color: '#6B7280' }
}

const parsed = computed<ParsedBooking[]>(() =>
  props.bookings
    .map((b) => ({ ...b, startAt: new Date(b.start), endAt: new Date(b.end) }))
    .sort((a, b) => a.startAt.getTime() - b.startAt.getTime()),
)

/** Bookings keyed by day. A booking spanning midnight lands in both days. */
const byDay = computed(() => {
  const m = new Map<string, ParsedBooking[]>()
  for (const b of parsed.value) {
    let d = startOfDay(b.startAt)
    while (d < b.endAt) {
      const k = dayKey(d)
      const list = m.get(k)
      if (list) {
        list.push(b)
      } else {
        m.set(k, [b])
      }
      d = addDays(d, 1)
    }
  }
  return m
})

const bookingsOn = (d: Date): ParsedBooking[] => byDay.value.get(dayKey(d)) ?? []

/** One room, so any overlap is a data error worth showing. */
const conflictIds = computed(() => {
  const ids = new Set<string>()
  const list = parsed.value
  for (let i = 1; i < list.length; i++) {
    const current = list[i]!
    const previous = list[i - 1]!
    if (current.startAt < previous.endAt) {
      ids.add(current.id)
      ids.add(previous.id)
    }
  }
  return ids
})

const timeFmt = computed(
  () => new Intl.DateTimeFormat(props.locale, { hour: '2-digit', minute: '2-digit' }),
)
const clock = (d: Date): string => timeFmt.value.format(d)

/** Position a booking inside a week-view day column. */
function blockStyle(b: ParsedBooking, day: Date): Record<string, string> {
  const dayStart = new Date(day.getFullYear(), day.getMonth(), day.getDate(), props.dayStartHour)
  const dayEnd = new Date(day.getFullYear(), day.getMonth(), day.getDate(), props.dayEndHour)
  const total = (dayEnd.getTime() - dayStart.getTime()) / 60000
  const from = Math.max(0, (b.startAt.getTime() - dayStart.getTime()) / 60000)
  const to = Math.min(total, (b.endAt.getTime() - dayStart.getTime()) / 60000)
  return {
    top: `${(from / total) * 100}%`,
    height: `${Math.max(((to - from) / total) * 100, 3)}%`,
    '--band': band(b).color,
  }
}

function newBooking(day: Date, hour: number): void {
  const start = new Date(day.getFullYear(), day.getMonth(), day.getDate(), hour)
  const end = new Date(start.getTime() + props.slotMinutes * 60000)
  emit('create', { start, end })
}
</script>

<template>
  <div class="cal">
    <header class="bar">
      <div class="nav">
        <button
          class="icon"
          :aria-label="view === 'week' ? 'Forrige uge' : 'Forrige måned'"
          @click="step(-1)"
        >
          ‹
        </button>
        <button
          class="icon"
          :aria-label="view === 'week' ? 'Næste uge' : 'Næste måned'"
          @click="step(1)"
        >
          ›
        </button>
        <button class="ghost" @click="goToday">I dag</button>
      </div>

      <h2 class="period">
        {{ periodLabel }}
        <span v-if="view === 'week'" class="wk-badge">Uge {{ isoWeek(weekDays[0]!) }}</span>
      </h2>

      <div class="views" role="group" aria-label="Visning">
        <button :class="{ on: view === 'month' }" @click="switchView('month')">Måned</button>
        <button :class="{ on: view === 'week' }" @click="switchView('week')">Uge</button>
      </div>
    </header>

    <!-- ---------------- month ---------------- -->
    <div v-if="view === 'month'" class="month">
      <div class="m-head">
        <div class="wk-col head">Uge</div>
        <div v-for="d in dayNames" :key="d" class="m-head-cell">{{ d }}</div>
      </div>

      <div v-for="(week, wi) in monthWeeks" :key="wi" class="m-row">
        <button class="wk-col" :title="`Vis uge ${isoWeek(week[0]!)}`" @click="selectWeek(week)">
          {{ isoWeek(week[0]!) }}
        </button>

        <div
          v-for="day in week"
          :key="day.toISOString()"
          class="m-cell"
          :class="{
            out: day.getMonth() !== cursor.getMonth(),
            today: sameDay(day, today),
            weekend: day.getDay() === 0 || day.getDay() === 6,
          }"
          @dblclick="newBooking(day, 19)"
        >
          <span class="m-date">{{ day.getDate() }}</span>
          <button
            v-for="b in bookingsOn(day)"
            :key="b.id"
            class="chip"
            :class="{ clash: conflictIds.has(b.id) }"
            :style="{ '--band': band(b).color }"
            @click.stop="emit('select', b)"
          >
            <span class="chip-time">{{ clock(b.startAt) }}</span>
            {{ band(b).name }}
          </button>
        </div>
      </div>
    </div>

    <!-- ---------------- week ---------------- -->
    <div v-else class="week">
      <div class="w-head">
        <div class="t-col wk-num">{{ isoWeek(weekDays[0]!) }}</div>
        <div
          v-for="(day, i) in weekDays"
          :key="day.toISOString()"
          class="w-head-cell"
          :class="{ today: sameDay(day, today) }"
        >
          <span class="w-day">{{ dayNames[i] }}</span>
          <span class="w-date">{{ day.getDate() }}</span>
        </div>
      </div>

      <div class="w-body">
        <div class="t-col">
          <div v-for="h in hours" :key="h" class="t-label">{{ String(h).padStart(2, '0') }}:00</div>
        </div>

        <div
          v-for="day in weekDays"
          :key="day.toISOString()"
          class="w-col"
          :class="{ today: sameDay(day, today) }"
        >
          <button
            v-for="h in hours"
            :key="h"
            class="slot"
            :aria-label="`Book ${day.getDate()}. kl. ${h}`"
            @click="newBooking(day, h)"
          />
          <button
            v-for="b in bookingsOn(day)"
            :key="b.id"
            class="block"
            :class="{ clash: conflictIds.has(b.id) }"
            :style="blockStyle(b, day)"
            @click.stop="emit('select', b)"
          >
            <span class="b-band">{{ band(b).name }}</span>
            <span class="b-time">{{ clock(b.startAt) }}–{{ clock(b.endAt) }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.cal {
  --line: #dce0e6;
  --line-soft: #eef0f3;
  --ink: #16181d;
  --muted: #6b7280;
  --rail: #f5f6f8;
  --today: #b4530a;
  color: var(--ink);
  font-family: ui-sans-serif, system-ui, 'Segoe UI', Roboto, sans-serif;
  font-size: 14px;
  border: 1px solid var(--line);
  border-radius: 6px;
  overflow: hidden;
  background: #fff;
}

/* toolbar */
.bar {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 10px 12px;
  border-bottom: 1px solid var(--line);
}
.nav {
  display: flex;
  gap: 4px;
}
.period {
  flex: 1;
  margin: 0;
  font-size: 17px;
  font-weight: 600;
  letter-spacing: -0.01em;
  text-transform: capitalize;
  display: flex;
  align-items: baseline;
  gap: 10px;
}
.wk-badge {
  font-size: 12px;
  font-weight: 500;
  color: var(--muted);
  text-transform: none;
}
button {
  font: inherit;
  color: inherit;
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 4px;
  padding: 5px 10px;
  cursor: pointer;
}
button:hover {
  background: var(--rail);
}
button:focus-visible {
  outline: 2px solid var(--today);
  outline-offset: 1px;
}
.icon {
  width: 30px;
  padding: 4px 0;
  line-height: 1;
  font-size: 17px;
}
.views {
  display: flex;
}
.views button {
  border-radius: 0;
  margin-left: -1px;
}
.views button:first-child {
  border-radius: 4px 0 0 4px;
}
.views button:last-child {
  border-radius: 0 4px 4px 0;
}
.views .on {
  background: var(--ink);
  border-color: var(--ink);
  color: #fff;
}

/* month */
.m-head,
.m-row {
  display: grid;
  grid-template-columns: 42px repeat(7, 1fr);
}
.m-head {
  border-bottom: 1px solid var(--line);
  background: var(--rail);
}
.m-head-cell,
.wk-col.head {
  padding: 6px 8px;
  font-size: 12px;
  font-weight: 600;
  color: var(--muted);
  text-transform: capitalize;
}
.wk-col {
  border: 0;
  border-right: 1px solid var(--line);
  background: var(--rail);
  color: var(--muted);
  font-variant-numeric: tabular-nums;
  font-size: 12px;
  border-radius: 0;
  padding: 8px 0;
}
.wk-col.head {
  border-right: 1px solid var(--line);
  text-align: left;
}
.m-row + .m-row {
  border-top: 1px solid var(--line-soft);
}
.m-cell {
  min-height: 96px;
  padding: 4px;
  border-right: 1px solid var(--line-soft);
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.m-cell:last-child {
  border-right: 0;
}
.m-cell.weekend {
  background: #fcfcfd;
}
.m-cell.out {
  color: #b6bcc5;
  background: #fafbfc;
}
.m-date {
  font-variant-numeric: tabular-nums;
  padding: 2px 4px;
}
.m-cell.today .m-date {
  background: var(--today);
  color: #fff;
  border-radius: 3px;
  align-self: flex-start;
  font-weight: 600;
}
.chip {
  border: 0;
  border-left: 3px solid var(--band);
  background: color-mix(in srgb, var(--band) 10%, #fff);
  border-radius: 2px;
  padding: 2px 5px;
  text-align: left;
  font-size: 12px;
  line-height: 1.35;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.chip-time {
  color: var(--muted);
  font-variant-numeric: tabular-nums;
  margin-right: 4px;
}
.chip.clash,
.block.clash {
  outline: 2px solid #dc2626;
  outline-offset: -2px;
}

/* week */
.w-head {
  display: grid;
  grid-template-columns: 56px repeat(7, 1fr);
  border-bottom: 1px solid var(--line);
  background: var(--rail);
}
.w-head-cell {
  padding: 6px 8px;
  display: flex;
  align-items: baseline;
  gap: 6px;
  border-left: 1px solid var(--line-soft);
}
.w-day {
  font-size: 12px;
  color: var(--muted);
  text-transform: capitalize;
}
.w-date {
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}
.w-head-cell.today .w-date {
  color: var(--today);
}
.wk-num {
  display: flex;
  align-items: center;
  justify-content: center;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
  color: var(--muted);
}
.w-body {
  display: grid;
  grid-template-columns: 56px repeat(7, 1fr);
  max-height: 620px;
  overflow-y: auto;
}
.t-col {
  background: var(--rail);
  border-right: 1px solid var(--line);
}
.t-label {
  height: 48px;
  padding: 2px 6px 0 0;
  text-align: right;
  font-size: 11px;
  color: var(--muted);
  font-variant-numeric: tabular-nums;
  box-sizing: border-box;
}
.w-col {
  position: relative;
  border-left: 1px solid var(--line-soft);
}
.w-col.today {
  background: #fffaf5;
}
.slot {
  display: block;
  width: 100%;
  height: 48px;
  border: 0;
  border-bottom: 1px solid var(--line-soft);
  border-radius: 0;
  background: transparent;
  box-sizing: border-box;
}
.slot:hover {
  background: var(--line-soft);
}
.block {
  position: absolute;
  left: 2px;
  right: 2px;
  border: 0;
  border-left: 3px solid var(--band);
  background: color-mix(in srgb, var(--band) 14%, #fff);
  border-radius: 3px;
  padding: 3px 6px;
  text-align: left;
  display: flex;
  flex-direction: column;
  gap: 1px;
  overflow: hidden;
}
.b-band {
  font-weight: 600;
  font-size: 12px;
}
.b-time {
  font-size: 11px;
  color: var(--muted);
  font-variant-numeric: tabular-nums;
}

@media (max-width: 720px) {
  .m-cell {
    min-height: 68px;
  }
  .m-head-cell,
  .w-day {
    font-size: 11px;
  }
  .chip-time {
    display: none;
  }
}
</style>
