// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  modules: [
    '@nuxt/eslint',
    '@nuxt/ui',
    '@nuxtjs/i18n',
    '@pinia/nuxt'
  ],

  /*
   * Client-side rendering only. Every page sits behind a login, so server rendering buys no
   * SEO, and it would force the Nuxt server to forward the browser's Sanctum session and
   * XSRF cookies to Laravel on every request. With ssr: false the browser talks to the API
   * directly and is the only holder of the session cookie.
   * Cost: a blank page until the JS bundle loads, and nothing renders without JavaScript.
   */
  ssr: false,

  devtools: {
    enabled: true
  },

  css: ['~/assets/css/main.css'],

  runtimeConfig: {
    public: {
      // Laravel origin, without /api. Override with NUXT_PUBLIC_API_BASE. The static build
      // (nuxt generate, used by Docker) bakes the value in at build time; Docker builds with an
      // empty value, so the SPA calls /api on its own origin.
      apiBase: 'http://localhost:8000'
    }
  },

  devServer: {
    // The API allows exactly this origin (CORS + SANCTUM_STATEFUL_DOMAINS), so pin it.
    host: 'localhost',
    port: 3000
  },

  compatibilityDate: '2026-06-30',

  eslint: {
    config: {
      stylistic: {
        commaDangle: 'never',
        braceStyle: '1tbs'
      }
    }
  },

  fonts: {
    families: [
      /*
       * Lao script fallback for Public Sans (see main.css). Declared here because
       * @nuxt/fonts only auto-resolves the first family of a font stack. "global" emits the
       * @font-face rules up front. Only the Lao subset: Latin comes from Public Sans, and
       * its unicode-range keeps the browser from downloading the file until a Lao
       * character is on screen.
       */
      { name: 'Noto Sans Lao', global: true, weights: [400, 500, 600, 700], subsets: ['lao'] }
    ]
  },

  i18n: {
    defaultLocale: 'en',
    // URLs stay the same in every language; the choice is remembered in a cookie.
    strategy: 'no_prefix',
    locales: [
      { code: 'en', language: 'en-US', name: 'English', file: 'en.json' },
      { code: 'lo', language: 'lo-LA', name: 'ລາວ', file: 'lo.json' }
    ],
    detectBrowserLanguage: {
      useCookie: true,
      cookieKey: 'i18n_locale',
      redirectOn: 'root'
    }
  }
})
