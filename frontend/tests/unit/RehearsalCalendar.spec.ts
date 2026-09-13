import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import RehearsalCalendar from '../../src/components/RehearsalCalendar.vue'
import { setViewportWidth } from '../setup'
import type { CalendarBooking } from '../../src/types/calendar'

/** Wednesday 16 September 2026 — pinned, so the week and its day picker always have the same shape. */
const WEDNESDAY = new Date(2026, 8, 16)

const PHONE_WIDTH = 390

function mountCalendar() {
  return mount(RehearsalCalendar, { props: { initialDate: WEDNESDAY } })
}

/** The `from` of the most recent `range-change`, which is what the parent fetches for. */
function lastRangeStart(wrapper: ReturnType<typeof mountCalendar>): Date {
  const events = wrapper.emitted('range-change')!
  return (events[events.length - 1]![0] as { from: Date }).from
}

function lastRangeEnd(wrapper: ReturnType<typeof mountCalendar>): Date {
  const events = wrapper.emitted('range-change')!
  return (events[events.length - 1]![0] as { to: Date }).to
}

const dayColumns = (wrapper: ReturnType<typeof mountCalendar>) => wrapper.findAll('.w-col')
const dayPicker = (wrapper: ReturnType<typeof mountCalendar>) => wrapper.findAll('.picker')

describe('RehearsalCalendar at desktop width', () => {
  it('opens on the month view and shows seven day columns in the week view', async () => {
    const wrapper = mountCalendar()
    expect(wrapper.find('.month').exists()).toBe(true)

    await wrapper.find('.views button:last-child').trigger('click')

    expect(dayColumns(wrapper)).toHaveLength(7)
    expect(dayPicker(wrapper)).toHaveLength(0)
  })

  it('labels the toggle "Uge" and steps a whole week', async () => {
    const wrapper = mountCalendar()
    const toggle = wrapper.find('.views button:last-child')
    expect(toggle.text()).toBe('Uge')

    await toggle.trigger('click')
    expect(wrapper.find('[aria-label="Næste uge"]').exists()).toBe(true)

    await wrapper.find('[aria-label="Næste uge"]').trigger('click')

    // Monday of the following week.
    expect(lastRangeStart(wrapper).getDate()).toBe(21)
  })

  it('opens a date in the week view from the month grid, without breaking double-click booking', async () => {
    const wrapper = mountCalendar()

    const dateButton = wrapper.findAll('.m-date').find((b) => b.text() === '16')!
    expect(dateButton.attributes('aria-label')).toBe('Vis 16. september')

    await dateButton.trigger('click')

    expect(dayColumns(wrapper)).toHaveLength(7)
    expect(lastRangeStart(wrapper).getDate()).toBe(14)
  })

  it('still creates a booking at 19:00 when a month cell is double-clicked', async () => {
    const wrapper = mountCalendar()

    await wrapper.findAll('.m-cell')[0]!.trigger('dblclick')

    const [range] = wrapper.emitted('create')![0] as [{ start: Date }]
    expect(range.start.getHours()).toBe(19)
  })
})

describe('RehearsalCalendar at phone width', () => {
  it('opens on the day view with a single day column', () => {
    setViewportWidth(PHONE_WIDTH)
    const wrapper = mountCalendar()

    expect(wrapper.find('.month').exists()).toBe(false)
    expect(dayColumns(wrapper)).toHaveLength(1)
    expect(wrapper.find('.views button:last-child').text()).toBe('Dag')
  })

  it('requests only the shown day, and still names its ISO week', () => {
    setViewportWidth(PHONE_WIDTH)
    const wrapper = mountCalendar()

    expect(lastRangeStart(wrapper).getDate()).toBe(16)
    expect(lastRangeEnd(wrapper).getDate()).toBe(17)
    expect(wrapper.find('.wk-badge').text()).toBe('Uge 38')
  })

  it('steps one day at a time', async () => {
    setViewportWidth(PHONE_WIDTH)
    const wrapper = mountCalendar()
    expect(wrapper.find('[aria-label="Forrige dag"]').exists()).toBe(true)

    await wrapper.find('[aria-label="Næste dag"]').trigger('click')

    expect(lastRangeStart(wrapper).getDate()).toBe(17)
  })

  it('picks another weekday from the day strip', async () => {
    setViewportWidth(PHONE_WIDTH)
    const wrapper = mountCalendar()

    const picker = dayPicker(wrapper)
    expect(picker).toHaveLength(7)
    expect(picker[2]!.attributes('aria-pressed')).toBe('true')

    await picker[0]!.trigger('click')

    // Monday of the same week, not the next one.
    expect(lastRangeStart(wrapper).getDate()).toBe(14)
    expect(dayPicker(wrapper)[0]!.attributes('aria-pressed')).toBe('true')
  })

  it('returns to seven columns when the viewport grows', async () => {
    setViewportWidth(PHONE_WIDTH)
    const wrapper = mountCalendar()
    expect(dayColumns(wrapper)).toHaveLength(1)

    setViewportWidth(1440)
    await wrapper.vm.$nextTick()

    expect(dayColumns(wrapper)).toHaveLength(7)
    expect(wrapper.find('.views button:last-child').text()).toBe('Uge')
  })
})

describe('the recurring marker', () => {
  const bands = [
    { id: 1, name: 'Fast Band' },
    { id: 2, name: 'Løs Band' },
  ]

  /** One of each source on the pinned week, so every assertion has its own control. */
  const bookings: CalendarBooking[] = [
    {
      id: 'recurring:1:2026-09-16',
      start: '2026-09-16T19:00:00',
      end: '2026-09-16T21:00:00',
      bandId: 1,
      recurring: true,
    },
    {
      id: 'ad_hoc:9',
      start: '2026-09-17T19:00:00',
      end: '2026-09-17T21:00:00',
      bandId: 2,
    },
  ]

  const mountWithBookings = () =>
    mount(RehearsalCalendar, { props: { initialDate: WEDNESDAY, bookings, bands } })

  it('marks only the recurring occurrence in the month grid', () => {
    const wrapper = mountWithBookings()
    const chips = wrapper.findAll('.chip')

    expect(
      chips
        .find((c) => c.text().includes('Fast Band'))!
        .find('.recurring-icon')
        .exists(),
    ).toBe(true)
    expect(
      chips
        .find((c) => c.text().includes('Løs Band'))!
        .find('.recurring-icon')
        .exists(),
    ).toBe(false)
  })

  it('marks only the recurring occurrence in the week view, and names the marker', async () => {
    const wrapper = mountWithBookings()
    await wrapper.find('.views button:last-child').trigger('click')
    const blocks = wrapper.findAll('.block')

    const recurring = blocks.find((b) => b.text().includes('Fast Band'))!
    expect(recurring.find('.recurring-icon title').text()).toBe('Ugentlig gentagelse')
    expect(
      blocks
        .find((b) => b.text().includes('Løs Band'))!
        .find('.recurring-icon')
        .exists(),
    ).toBe(false)
  })
})
