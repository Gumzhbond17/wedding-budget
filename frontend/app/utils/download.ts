/**
 * File name from a Content-Disposition header. Prefers the RFC 6266 / RFC 5987 `filename*`
 * (UTF-8, percent-encoded), which keeps Lao text, over the ASCII `filename` fallback.
 * Path separators are stripped, so a hostile header cannot name a file outside Downloads.
 * Returns null when the header is missing or names nothing usable.
 */
export function fileNameFromDisposition(header: string | null): string | null {
  if (!header) {
    return null
  }

  let name: string | null = null
  const extended = /filename\*\s*=\s*([\w-]+)'[^']*'([^;]+)/i.exec(header)

  if (extended && extended[1]!.toLowerCase() === 'utf-8') {
    try {
      name = decodeURIComponent(extended[2]!.trim())
    } catch {
      // Malformed percent-encoding: fall back to the plain parameter.
    }
  }

  if (name === null) {
    const plain = /filename\s*=\s*(?:"((?:[^"\\]|\\.)*)"|([^;]+))/i.exec(header)
    name = plain ? (plain[1]?.replace(/\\(.)/g, '$1') ?? plain[2]!.trim()) : null
  }

  name = name?.replace(/[/\\]/g, '').trim() ?? ''

  return name === '' || name === '.' || name === '..' ? null : name
}

/**
 * Hands a Blob to the browser as a download. The object URL is revoked on the next tick:
 * the click has started the download by then, and the Blob would otherwise stay in memory.
 */
export function saveBlob(blob: Blob, fileName: string): void {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = fileName
  link.rel = 'noopener'
  document.body.append(link)
  link.click()
  link.remove()
  setTimeout(() => URL.revokeObjectURL(url), 0)
}
