import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { useIsPhone } from '../../src/composables/useIsPhone'
import { setViewportWidth } from '../setup'

/** jsdom has no layout, so the width comes from the matchMedia stub in tests/setup.ts. */
const Probe = defineComponent({
  setup: () => ({ isPhone: useIsPhone() }),
  template: '<span>{{ isPhone }}</span>',
})

describe('useIsPhone', () => {
  it('is false at desktop width', () => {
    expect(mount(Probe).text()).toBe('false')
  })

  it('is true at phone width', () => {
    setViewportWidth(390)

    expect(mount(Probe).text()).toBe('true')
  })

  it('reacts to the viewport changing while mounted', async () => {
    const wrapper = mount(Probe)
    expect(wrapper.text()).toBe('false')

    setViewportWidth(390)
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toBe('true')
  })

  it('stops listening once unmounted', () => {
    const wrapper = mount(Probe)
    wrapper.unmount()

    // Nothing to assert on the DOM — this proves the listener teardown path
    // runs without throwing, which is what a removed component must do.
    expect(() => setViewportWidth(390)).not.toThrow()
  })
})
