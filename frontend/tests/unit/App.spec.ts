import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import App from '../../src/App.vue'

describe('App', () => {
  beforeEach(() => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => ({
        json: async () => ({ status: 'ok' }),
      })),
    )
  })

  it('renders the API health status once the fetch resolves', async () => {
    const wrapper = mount(App)
    await flushPromises()

    expect(wrapper.text()).toContain('API health: ok')
  })

  it('renders an error message when the fetch fails', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => {
        throw new Error('network down')
      }),
    )

    const wrapper = mount(App)
    await flushPromises()

    expect(wrapper.text()).toContain('error: network down')
  })
})
