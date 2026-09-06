import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import BookingDialog from '../../src/components/BookingDialog.vue'

const bands = [
  { id: 1, name: 'The Wailers' },
  { id: 2, name: 'ABBA' },
]

function stubFetch(
  impl: (
    url: string,
    init?: RequestInit,
  ) => Promise<{ ok: boolean; status: number; json: () => Promise<unknown> }>,
) {
  vi.stubGlobal('fetch', vi.fn(impl))
}

describe('BookingDialog', () => {
  it('pre-fills date/time from the clicked slot and bolds the current user’s own bands', async () => {
    stubFetch(async () => ({ ok: true, status: 200, json: async () => ({ conflicts: [] }) }))

    const wrapper = mount(BookingDialog, {
      props: {
        bands,
        ownBandIds: [2],
        initialStart: new Date(2026, 8, 9, 19, 0),
        initialEnd: new Date(2026, 8, 9, 20, 0),
      },
    })
    await flushPromises()

    expect((wrapper.find('input[type="date"]').element as HTMLInputElement).value).toBe(
      '2026-09-09',
    )

    const timeInputs = wrapper.findAll('input[type="time"]')
    expect((timeInputs[0]!.element as HTMLInputElement).value).toBe('19:00')
    expect((timeInputs[1]!.element as HTMLInputElement).value).toBe('20:00')

    const abbaOption = wrapper.findAll('option').find((o) => o.text() === 'ABBA')!
    expect(abbaOption.classes()).toContain('own')
  })

  it('lists live conflicts and hard-disables Book while one exists (ADR 0002)', async () => {
    stubFetch(async () => ({
      ok: true,
      status: 200,
      json: async () => ({
        conflicts: [
          {
            source: 'ad_hoc',
            id: 1,
            band_id: 1,
            band_name: 'The Wailers',
            start_time: '2026-09-09 19:00:00',
            end_time: '2026-09-09 21:00:00',
            booked_by_user_id: 1,
          },
        ],
      }),
    }))

    const wrapper = mount(BookingDialog, {
      props: {
        bands,
        ownBandIds: [],
        initialStart: new Date(2026, 8, 9, 19, 0),
        initialEnd: new Date(2026, 8, 9, 20, 0),
      },
    })
    await flushPromises()

    expect(wrapper.find('[role="alert"]').text()).toContain('The Wailers')
    expect((wrapper.find('button[type="submit"]').element as HTMLButtonElement).disabled).toBe(true)
  })

  it('submits and emits created with the resulting occurrence on success', async () => {
    stubFetch(async (_url: string, init?: RequestInit) => {
      if (init?.method === 'POST') {
        return {
          ok: true,
          status: 201,
          json: async () => ({
            booking: {
              source: 'ad_hoc',
              id: 9,
              band_id: 1,
              band_name: 'The Wailers',
              start_time: '2026-09-09 19:00:00',
              end_time: '2026-09-09 20:00:00',
              booked_by_user_id: 1,
            },
          }),
        }
      }
      return { ok: true, status: 200, json: async () => ({ conflicts: [] }) }
    })

    const wrapper = mount(BookingDialog, {
      props: {
        bands,
        ownBandIds: [1],
        initialStart: new Date(2026, 8, 9, 19, 0),
        initialEnd: new Date(2026, 8, 9, 20, 0),
      },
    })
    await flushPromises()

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.emitted('created')).toHaveLength(1)
    const [occurrence] = wrapper.emitted('created')![0] as [{ id: number; band_id: number }]
    expect(occurrence).toMatchObject({ id: 9, band_id: 1 })
  })

  it('shows a server-side conflict rejected at submit time, without emitting created', async () => {
    stubFetch(async (_url: string, init?: RequestInit) => {
      if (init?.method === 'POST') {
        return {
          ok: false,
          status: 409,
          json: async () => ({
            error: 'This slot conflicts with an existing booking',
            conflicts: [
              {
                source: 'ad_hoc',
                id: 2,
                band_id: 2,
                band_name: 'ABBA',
                start_time: '2026-09-09 19:00:00',
                end_time: '2026-09-09 20:00:00',
                booked_by_user_id: 1,
              },
            ],
          }),
        }
      }
      // The live client-side check saw no conflict — the server independently
      // found one anyway (e.g. a race with another submission).
      return { ok: true, status: 200, json: async () => ({ conflicts: [] }) }
    })

    const wrapper = mount(BookingDialog, {
      props: {
        bands,
        ownBandIds: [1],
        initialStart: new Date(2026, 8, 9, 19, 0),
        initialEnd: new Date(2026, 8, 9, 20, 0),
      },
    })
    await flushPromises()

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.emitted('created')).toBeUndefined()
    expect(wrapper.text()).toContain('ABBA')
    expect((wrapper.find('button[type="submit"]').element as HTMLButtonElement).disabled).toBe(true)
  })

  it('disables Book when Til is not after Fra', async () => {
    stubFetch(async () => ({ ok: true, status: 200, json: async () => ({ conflicts: [] }) }))

    const wrapper = mount(BookingDialog, {
      props: {
        bands,
        ownBandIds: [1],
        initialStart: new Date(2026, 8, 9, 19, 0),
        initialEnd: new Date(2026, 8, 9, 19, 0),
      },
    })
    await flushPromises()

    expect((wrapper.find('button[type="submit"]').element as HTMLButtonElement).disabled).toBe(true)
  })

  it('switches to recurring mode, checks pattern conflicts, and submits a recurring slot', async () => {
    const calls: { url: string; method?: string }[] = []
    stubFetch(async (url: string, init?: RequestInit) => {
      calls.push({ url, method: init?.method })
      if (init?.method === 'POST') {
        return {
          ok: true,
          status: 201,
          json: async () => ({
            recurring_slot: {
              id: 5,
              band_id: 1,
              band_name: 'The Wailers',
              day_of_week: 3,
              start_time: '19:00:00',
              end_time: '20:00:00',
              week_parity: 'all',
              start_date: '2026-09-09',
              end_date: null,
            },
          }),
        }
      }
      return { ok: true, status: 200, json: async () => ({ conflicts: [] }) }
    })

    const wrapper = mount(BookingDialog, {
      props: {
        bands,
        ownBandIds: [1],
        initialStart: new Date(2026, 8, 9, 19, 0),
        initialEnd: new Date(2026, 8, 9, 20, 0),
      },
    })
    await flushPromises()

    const toggleButtons = wrapper.findAll('.mode-toggle button')
    await toggleButtons[1]!.trigger('click')
    await flushPromises()

    expect(calls.some((c) => c.url.startsWith('/api/recurring-slots/conflicts'))).toBe(true)
    expect(wrapper.findAll('select').length).toBe(2) // band + week parity

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(calls.some((c) => c.url === '/api/recurring-slots' && c.method === 'POST')).toBe(true)
    expect(wrapper.emitted('recurring-slot-created')).toHaveLength(1)
  })

  it('shows a recurring pattern conflict and hard-disables Book', async () => {
    stubFetch(async () => ({
      ok: true,
      status: 200,
      json: async () => ({
        conflicts: [
          {
            source: 'recurring_pattern',
            id: 3,
            band_id: 2,
            band_name: 'ABBA',
            day_of_week: 3,
            start_time: '19:00:00',
            end_time: '21:00:00',
            week_parity: 'all',
            start_date: '2020-01-01',
            end_date: null,
          },
        ],
      }),
    }))

    const wrapper = mount(BookingDialog, {
      props: {
        bands,
        ownBandIds: [],
        initialStart: new Date(2026, 8, 9, 19, 0),
        initialEnd: new Date(2026, 8, 9, 20, 0),
      },
    })
    await flushPromises()

    await wrapper.findAll('.mode-toggle button')[1]!.trigger('click')
    await flushPromises()

    expect(wrapper.find('[role="alert"]').text()).toContain('ABBA')
    expect((wrapper.find('button[type="submit"]').element as HTMLButtonElement).disabled).toBe(true)
  })
})
