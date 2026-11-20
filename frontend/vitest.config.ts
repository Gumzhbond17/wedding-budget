import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vitest/config'

/*
 * Unit tests for framework-free code (utils/). They run in plain Node, so they stay fast and
 * need no Nuxt build. Code that needs the Nuxt runtime is kept thin on purpose (useApi only
 * wires utils/apiClient.ts to $fetch, cookies, the router and toasts).
 */
export default defineConfig({
  resolve: {
    alias: {
      '~': fileURLToPath(new URL('./app', import.meta.url))
    }
  },
  test: {
    include: ['test/unit/**/*.test.ts'],
    environment: 'node',
    // pnpm coverage: the framework-free code the unit tests target.
    coverage: {
      provider: 'v8',
      include: ['app/utils/**/*.ts'],
      reporter: ['text', 'html'],
      reportsDirectory: 'coverage'
    }
  }
})
