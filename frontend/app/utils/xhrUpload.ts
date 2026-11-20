import type { Uploader } from '~/utils/apiClient'

/**
 * Uploader for apiClient over XMLHttpRequest, the only browser API that reports upload
 * progress. Failures are shaped like ofetch's FetchError ({ response: { status }, data }),
 * so the client's 401 / 419-retry / 422 handling applies unchanged.
 */
export const xhrUpload: Uploader = <T>(url: string, options: Parameters<Uploader>[1]) => new Promise<T>((resolve, reject) => {
  const xhr = new XMLHttpRequest()

  xhr.open(options.method, options.baseURL + url)
  xhr.withCredentials = options.credentials === 'include'

  for (const [name, value] of Object.entries(options.headers)) {
    xhr.setRequestHeader(name, value)
  }

  xhr.upload.addEventListener('progress', (event) => {
    if (event.lengthComputable) {
      options.onProgress(event.loaded / event.total)
    }
  })

  xhr.addEventListener('load', () => {
    let data: unknown = null

    try {
      data = xhr.responseText === '' ? null : JSON.parse(xhr.responseText)
    } catch {
      // Non-JSON error page: keep data null.
    }

    if (xhr.status >= 200 && xhr.status < 300) {
      resolve(data as T)
    } else {
      reject(Object.assign(new Error(`HTTP ${xhr.status}`), { response: { status: xhr.status }, data }))
    }
  })

  // No response at all (network down, CORS refusal): no status, like a failed fetch.
  xhr.addEventListener('error', () => reject(new TypeError('Network error')))
  xhr.addEventListener('abort', () => reject(new TypeError('Upload aborted')))

  // The browser sets the multipart Content-Type with its boundary.
  xhr.send(options.body)
})
