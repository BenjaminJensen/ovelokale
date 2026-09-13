import { onMounted, onUnmounted, ref, type Ref } from 'vue'

/**
 * The one breakpoint in the app: below this width is a phone, at or above it is
 * the desktop layout (an iPad in portrait is 768px and gets the desktop one).
 *
 * Duplicated by the `@media (max-width: 639px)` blocks in `App.vue`,
 * `RehearsalCalendar.vue` and `BookingDialog.vue` — CSS cannot read this
 * constant, so the four must be changed together.
 */
export const PHONE_QUERY = '(max-width: 639px)'

/**
 * Tracks whether the viewport is phone-sized. Only for behaviour CSS cannot
 * express — which day columns the calendar renders, which view it opens on.
 * Anything that is purely visual belongs in a media query instead.
 */
export function useIsPhone(): Ref<boolean> {
  const query = window.matchMedia(PHONE_QUERY)
  const isPhone = ref(query.matches)

  const update = (event: MediaQueryListEvent): void => {
    isPhone.value = event.matches
  }

  onMounted(() => query.addEventListener('change', update))
  onUnmounted(() => query.removeEventListener('change', update))

  return isPhone
}
