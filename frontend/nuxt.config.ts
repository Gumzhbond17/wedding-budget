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
       * Google Sans comes from the Google Fonts @import in main.css. Without this entry,
       * @nuxt/fonts (bundled with Nuxt UI) would see the family in --font-sans and also
       * download and self-host it, so the browser would get two sets of @font-face rules.
       */
      { name: 'Google Sans', provider: 'none' }
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
