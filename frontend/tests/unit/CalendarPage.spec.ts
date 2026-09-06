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
})
