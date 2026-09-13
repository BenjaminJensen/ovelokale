<script setup lang="ts">
/**
 * Marks an occurrence as coming from a recurring slot rather than an ad-hoc
 * booking.
 *
 * The glyph is Material Design Icons' `repeat` path, inlined: MDI ships ~7000
 * paths as a dependency and the calendar needs exactly this one, so the path
 * lives here instead. It carries its own accessible name — the icon *is* the
 * message, and the surrounding chip has no room to spell it out.
 */
</script>

<template>
  <svg class="recurring-icon" viewBox="0 0 24 24" role="img" focusable="false">
    <title>Ugentlig gentagelse</title>
    <path
      fill="currentColor"
      d="M17,17H7V14L3,18L7,22V19H19V13H17M7,7H17V10L21,6L17,2V5H5V11H7V7Z"
    />
  </svg>
</template>

<style scoped>
/*
 * Sized in `em` so one component fits both the month chip and the week block
 * without either passing a size down. It stays at 1em rather than growing to
 * match the cap height: a month chip truncates its band name at tablet width
 * already, and every pixel the marker takes is a character it loses.
 */
.recurring-icon {
  width: 1em;
  height: 1em;
  flex: none;
  vertical-align: -0.15em;
}
</style>
