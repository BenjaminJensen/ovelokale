import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import CalendarPage from '../../src/components/CalendarPage.vue'

describe('CalendarPage', () => {
  it('fetches every visible week and renders bookings with the fallback band label', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async (url: string) => {
        const weekStart = new URL(url, 'http://localhost').searchParams.get('week_start')!
        return {
          ok: true,
          json: async () => ({
            week_start: weekStart,
            week_end: weekStart,
            iso_week_number: 1,
            iso_year: 2026,
            week_parity: 'odd',
            bookings: [
              {
                source: 'ad_hoc',
                id: Number(weekStart.replace(/-/g, '')),
                band_id: 7,
                start_time: `${weekStart} 19:00:00`,
                end_time: `${weekStart} 21:00:00`,
                booked_by_user_id: 1,
              },
            ],
          }),
        }
      }),
    )

    const wrapper = mount(CalendarPage)
    await flushPromises()

    // No bands supplied (band names aren't available from the API yet), so
    // occurrences fall back to the generic "Booked" chip label.
    expect(wrapper.text()).toContain('Booked')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('shows an error message when a week fetch fails', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => {
        throw new Error('network down')
      }),
    )

    const wrapper = mount(CalendarPage)
    await flushPromises()

    expect(wrapper.find('[role="alert"]').text()).toContain('network down')
  })

  it('opens the booking dialog on an empty-slot click and adds the booking to the calendar on success, without a page reload', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async (url: string, init?: RequestInit) => {
        if (init?.method === 'POST') {
          const body = JSON.parse(String(init.body)) as {
            band_id: number
            start_time: string
            end_time: string
          }
          return {
            ok: true,
            status: 201,
            json: async () => ({
              booking: {
                source: 'ad_hoc',
                id: 42,
                band_id: body.band_id,
                band_name: 'The Wailers',
                start_time: body.start_time,
                end_time: body.end_time,
                booked_by_user_id: 1,
              },
            }),
          }
        }

        if (url.startsWith('/api/bookings/conflicts')) {
          return { ok: true, status: 200, json: async () => ({ conflicts: [] }) }
        }

        if (url.startsWith('/api/bands')) {
          return {
            ok: true,
            status: 200,
            json: async () => ({ bands: [{ id: 1, name: 'The Wailers' }] }),
          }
        }

        const weekStart = new URL(url, 'http://localhost').searchParams.get('week_start')!
        return {
          ok: true,
          status: 200,
          json: async () => ({
            week_start: weekStart,
            week_end: weekStart,
            iso_week_number: 1,
            iso_year: 2026,
            week_parity: 'odd',
            bookings: [],
          }),
        }
      }),
    )

    const wrapper = mount(CalendarPage)
    await flushPromises()

    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)

    await wrapper.find('.m-cell:not(.out)').trigger('dblclick')
    await flushPromises()

    expect(wrapper.find('[role="dialog"]').exists()).toBe(true)

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('The Wailers')
  })
})
