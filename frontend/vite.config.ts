/// <reference types="vitest/config" />
import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    host: true,
    port: 5173,
    // The `playwright` container reaches the dev server by its compose service
    // name, and Vite answers 403 to any Host header it doesn't recognise.
    allowedHosts: ['frontend'],
    proxy: {
      '/api': {
        target: 'http://web:80',
        changeOrigin: true,
      },
    },
  },
  test: {
    environment: 'jsdom',
    // Unit tests only. The Playwright specs under `e2e/` share the `.spec.ts`
    // suffix but need a real browser, and Vitest would otherwise collect them.
    include: ['tests/**/*.spec.ts'],
  },
})
