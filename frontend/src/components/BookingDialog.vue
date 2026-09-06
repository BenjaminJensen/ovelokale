<script setup lang="ts">
/**
 * BookingDialog.vue — create an ad-hoc booking for a single day, with a
 * live conflict check that hard-disables "Book" while any conflict exists
 * (per ADR 0002 — there is no submit-anyway path).
 */
import { computed, ref, watch } from 'vue'
import type { CalendarBand, CalendarOccurrence } from '@/types/calendar'
import { BookingConflictError, createBooking, fetchConflicts } from '@/api/bookings'

interface Props {
  bands: CalendarBand[]
  ownBandIds: number[]
  initialStart: Date
  initialEnd: Date
}

const props = defineProps<Props>()

const emit = defineEmits<{
  close: []
  created: [occurrence: CalendarOccurrence]
}>()

function pad(n: number): string {
  return String(n).padStart(2, '0')
}

function toDateParam(d: Date): string {
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

function toTimeParam(d: Date): string {
  return `${pad(d.getHours())}:${pad(d.getMinutes())}`
}

function toNaiveDateTime(d: Date): string {
  return `${toDateParam(d)} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`
}

const ownBandIdSet = computed(() => new Set(props.ownBandIds))

/** Own bands first (alphabetical within each group) — bolded via `.own` below. */
const sortedBands = computed(() =>
  [...props.bands].sort((a, b) => {
    const aOwn = ownBandIdSet.value.has(a.id)
    const bOwn = ownBandIdSet.value.has(b.id)
    if (aOwn !== bOwn) return aOwn ? -1 : 1
    return a.name.localeCompare(b.name)
  }),
)

const bandId = ref<number>(props.ownBandIds[0] ?? props.bands[0]?.id ?? 0)
const date = ref(toDateParam(props.initialStart))
const fromTime = ref(toTimeParam(props.initialStart))
const toTime = ref(toTimeParam(props.initialEnd))

/** A native `date` + two `time` inputs can never span midnight by construction; this only catches Fra >= Til. */
const isValidRange = computed(
  () => fromTime.value !== '' && toTime.value !== '' && fromTime.value < toTime.value,
)

function buildDateTime(time: string): Date | null {
  const dateMatch = /^(\d{4})-(\d{2})-(\d{2})$/.exec(date.value)
  const timeMatch = /^(\d{2}):(\d{2})$/.exec(time)
  if (!dateMatch || !timeMatch) return null

  const [, y, m, d] = dateMatch.map(Number)
  const [, h, min] = timeMatch.map(Number)
  return new Date(y!, m! - 1, d, h, min)
}

const conflicts = ref<CalendarOccurrence[]>([])
const checking = ref(false)
const submitting = ref(false)
const submitError = ref<string | null>(null)

let latestCheckId = 0

watch(
  [date, fromTime, toTime],
  async () => {
    submitError.value = null

    if (!isValidRange.value) {
      conflicts.value = []
      return
    }

    const start = buildDateTime(fromTime.value)
    const end = buildDateTime(toTime.value)
    if (!start || !end) {
      conflicts.value = []
      return
    }

    const checkId = ++latestCheckId
    checking.value = true

    try {
      const result = await fetchConflicts(toNaiveDateTime(start), toNaiveDateTime(end))
      if (checkId !== latestCheckId) return
      conflicts.value = result
    } catch (e) {
      if (checkId !== latestCheckId) return
      submitError.value = e instanceof Error ? e.message : String(e)
    } finally {
      if (checkId === latestCheckId) checking.value = false
    }
  },
  { immediate: true },
)

const canSubmit = computed(
  () =>
    bandId.value > 0 &&
    isValidRange.value &&
    conflicts.value.length === 0 &&
    !checking.value &&
    !submitting.value,
)

function conflictLabel(c: CalendarOccurrence): string {
  const band = c.band_name ?? 'Ukendt band'
  return `${band} ${c.start_time.slice(11, 16)}–${c.end_time.slice(11, 16)}`
}

async function submit(): Promise<void> {
  if (!canSubmit.value) return

  const start = buildDateTime(fromTime.value)
  const end = buildDateTime(toTime.value)
  if (!start || !end) return

  submitting.value = true
  submitError.value = null

  try {
    const occurrence = await createBooking({
      bandId: bandId.value,
      startTime: toNaiveDateTime(start),
      endTime: toNaiveDateTime(end),
    })
    emit('created', occurrence)
  } catch (e) {
    if (e instanceof BookingConflictError) {
      conflicts.value = e.conflicts
    }
    submitError.value = e instanceof Error ? e.message : String(e)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="overlay" @click.self="emit('close')">
    <div class="dialog" role="dialog" aria-modal="true" aria-labelledby="booking-dialog-title">
      <header class="dialog-head">
        <h2 id="booking-dialog-title">Book øvelokale</h2>
        <button type="button" class="icon" aria-label="Luk" @click="emit('close')">×</button>
      </header>

      <form class="dialog-body" @submit.prevent="submit">
        <label class="field">
          <span>Band</span>
          <select v-model.number="bandId" required>
            <option
              v-for="b in sortedBands"
              :key="b.id"
              :value="b.id"
              :class="{ own: ownBandIdSet.has(b.id) }"
            >
              {{ b.name }}
            </option>
          </select>
        </label>

        <label class="field">
          <span>Dato</span>
          <input v-model="date" type="date" required />
        </label>

        <div class="time-row">
          <label class="field">
            <span>Fra</span>
            <input v-model="fromTime" type="time" required />
          </label>
          <label class="field">
            <span>Til</span>
            <input v-model="toTime" type="time" required />
          </label>
        </div>

        <p v-if="!isValidRange" class="hint" role="alert">Til skal være efter Fra.</p>
        <p v-else-if="checking" class="hint">Tjekker ledighed…</p>

        <div v-if="conflicts.length > 0" class="conflicts" role="alert">
          <p>Denne tid er optaget:</p>
          <ul>
            <li v-for="(c, i) in conflicts" :key="i">{{ conflictLabel(c) }}</li>
          </ul>
        </div>

        <p v-if="submitError" class="error" role="alert">{{ submitError }}</p>

        <footer class="dialog-actions">
          <button type="button" class="ghost" @click="emit('close')">Annuller</button>
          <button type="submit" :disabled="!canSubmit">
            {{ submitting ? 'Booker…' : 'Book' }}
          </button>
        </footer>
      </form>
    </div>
  </div>
</template>

<style scoped>
.overlay {
  position: fixed;
  inset: 0;
  background: rgb(0 0 0 / 40%);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
}

.dialog {
  --line: #dce0e6;
  --ink: #16181d;
  --muted: #6b7280;
  --today: #b4530a;
  color: var(--ink);
  font-family: ui-sans-serif, system-ui, 'Segoe UI', Roboto, sans-serif;
  font-size: 14px;
  background: #fff;
  border-radius: 6px;
  border: 1px solid var(--line);
  width: 320px;
  max-width: calc(100vw - 32px);
  box-shadow: 0 12px 32px rgb(0 0 0 / 20%);
}

.dialog-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 14px;
  border-bottom: 1px solid var(--line);
}

.dialog-head h2 {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
}

.dialog-body {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 12px;
  font-weight: 600;
  color: var(--muted);
}

.field select,
.field input {
  font: inherit;
  color: var(--ink);
  border: 1px solid var(--line);
  border-radius: 4px;
  padding: 6px 8px;
}

.own {
  font-weight: 700;
}

.time-row {
  display: flex;
  gap: 10px;
}

.time-row .field {
  flex: 1;
}

.hint {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
}

.conflicts {
  margin: 0;
  padding: 8px 10px;
  border-radius: 4px;
  background: #fef2f2;
  color: #b91c1c;
  font-size: 12px;
}

.conflicts p {
  margin: 0 0 4px;
  font-weight: 600;
}

.conflicts ul {
  margin: 0;
  padding-left: 16px;
}

.error {
  margin: 0;
  font-size: 12px;
  color: #b91c1c;
}

.dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 4px;
}

button {
  font: inherit;
  color: inherit;
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 4px;
  padding: 6px 12px;
  cursor: pointer;
}

button:hover {
  background: #f5f6f8;
}

button[type='submit'] {
  background: var(--ink);
  border-color: var(--ink);
  color: #fff;
}

button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.icon {
  border: 0;
  background: transparent;
  font-size: 18px;
  line-height: 1;
  padding: 2px 6px;
}
</style>
