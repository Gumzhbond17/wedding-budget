/**
 * The ?redirect= target after login, limited to paths inside this app. Anything else
 * ("https://evil.example", "//evil.example", "/\evil.example") falls back to the default,
 * so the login page cannot be used as an open redirect.
 */
export function safeRedirect(target: unknown, fallback = '/weddings'): string {
  if (typeof target !== 'string' || !target.startsWith('/') || target.startsWith('//') || target.includes('\\')) {
    return fallback
  }

  return target
}
