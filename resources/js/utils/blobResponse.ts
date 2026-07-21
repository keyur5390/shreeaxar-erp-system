import type { AxiosError } from 'axios'

export async function readBlobErrorMessage(data: unknown, fallback = 'Request failed.'): Promise<string> {
  if (!(data instanceof Blob)) {
    return fallback
  }

  try {
    const text = await data.text()
    const json = JSON.parse(text) as { message?: string }
    return json.message ?? fallback
  } catch {
    return fallback
  }
}

export async function extractAxiosBlobError(error: unknown, fallback: string): Promise<Error> {
  if (!error || typeof error !== 'object' || !('isAxiosError' in error)) {
    return error instanceof Error ? error : new Error(fallback)
  }

  const axiosError = error as AxiosError<Blob>
  const message = axiosError.response?.data
    ? await readBlobErrorMessage(axiosError.response.data, fallback)
    : (axiosError.message || fallback)

  return new Error(message)
}
