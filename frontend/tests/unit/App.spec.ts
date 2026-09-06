import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import App from '../../src/App.vue'

describe('App', () => {
  beforeEach(() => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => ({
        ok: true,
        json: async () => ({
          week_start: '2026-01-01',
          week_end: '2026-01-07',
          iso_week_number: 1,
          iso_year: 2026,
          week_parity: 'odd',
          bookings: [],
        }),
      })),
    )
  })

  it('renders the heading and mounts the calendar', async () => {
    const wrapper = mount(App)
    await flushPromises()

    expect(wrapper.text()).toContain('Ovelokale')
    expect(wrapper.find('.cal').exists()).toBe(true)
  })
})
