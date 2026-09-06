<script setup lang="ts">
import { ref, onMounted } from 'vue'

interface HealthResponse {
  status: string
}

const status = ref('checking...')

onMounted(async () => {
  try {
    const res = await fetch('/api/health')
    const data: HealthResponse = await res.json()
    status.value = data.status
  } catch (e) {
    status.value = `error: ${e instanceof Error ? e.message : String(e)}`
  }
})
</script>

<template>
  <main>
    <h1>Ovelokale</h1>
    <p>API health: {{ status }}</p>
  </main>
</template>
