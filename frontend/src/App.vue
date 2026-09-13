<script setup lang="ts">
import CalendarPage from '@/components/CalendarPage.vue'
</script>

<template>
  <main>
    <h1>Ovelokale</h1>
    <CalendarPage />
  </main>
</template>

<style scoped>
main {
  padding: 24px;
}

h1 {
  margin: 0 0 16px;
  font-size: 24px;
  letter-spacing: -0.02em;
}

/* Phone — see PHONE_QUERY in src/composables/useIsPhone.ts. */
@media (max-width: 639px) {
  main {
    padding: 12px;
  }

  h1 {
    margin: 0 0 10px;
    font-size: 20px;
  }
}
</style>
