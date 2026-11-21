export type ApiErrorKind = 'validation' | 'not_found' | 'rate_limited' | 'unavailable' | 'network' | 'unknown'

interface ApiErrorInit {
  kind: ApiErrorKind
  status: number
  message: string
  code?: string
  details?: Record<string, string[]>
  requestId?: string
  retryAfterSeconds?: number
}

/** The one error type the UI deals with, whatever went wrong underneath. */
export class ApiError extends Error {
  readonly kind: ApiErrorKind
  readonly status: number
  readonly code?: string
  readonly details?: Record<string, string[]>
  readonly requestId?: string
  readonly retryAfterSeconds?: number

  constructor(init: ApiErrorInit) {
    super(init.message)
    this.name = 'ApiError'
    this.kind = init.kind
    this.status = init.status
    this.code = init.code
    this.details = init.details
    this.requestId = init.requestId
    this.retryAfterSeconds = init.retryAfterSeconds
  }
}

const USER_MESSAGES: Record<ApiErrorKind, string> = {
  validation: 'Please check the highlighted fields.',
  not_found: "We couldn't find what you were looking for.",
  rate_limited: 'Too many attempts. Please wait a moment and try again.',
  unavailable: 'The catalogue is temporarily unavailable. Please try again shortly.',
  network: "We couldn't reach the server. Check your connection and try again.",
  unknown: 'Something went wrong. Please try again.',
}

/** Safe, friendly text for any thrown value. */
export function userMessage(error: unknown): string {
  return USER_MESSAGES[error instanceof ApiError ? error.kind : 'unknown']
}

function kindForStatus(status: number): ApiErrorKind {
  if (status === 422) return 'validation'
  if (status === 404) return 'not_found'
  if (status === 429) return 'rate_limited'
  if (status === 502 || status === 503 || status === 504) return 'unavailable'
  return 'unknown'
}

interface ErrorEnvelope {
  error?: { code?: string; message?: string; details?: Record<string, string[]>; request_id?: string }
}

/** Calls our backend only (`/api/v1`); the browser never talks to a third-party data source. */
export async function apiRequest<T>(path: string, init: RequestInit = {}): Promise<T> {
  let response: Response
  try {
    response = await fetch(new URL(`/api/v1${path}`, window.location.origin), {
      ...init,
      headers: { Accept: 'application/json', ...init.headers },
    })
  } catch {
    throw new ApiError({ kind: 'network', status: 0, message: USER_MESSAGES.network })
  }

  const body: unknown = await response.json().catch(() => null)

  if (!response.ok) {
    const envelope = (body as ErrorEnvelope | null)?.error
    const retryAfter = Number(response.headers.get('Retry-After'))
    throw new ApiError({
      kind: kindForStatus(response.status),
      status: response.status,
      message: envelope?.message ?? `Request failed with status ${response.status}`,
      code: envelope?.code,
      details: envelope?.details,
      requestId: envelope?.request_id ?? response.headers.get('X-Request-Id') ?? undefined,
      retryAfterSeconds: Number.isFinite(retryAfter) && retryAfter > 0 ? retryAfter : undefined,
    })
  }

  return (body as { data: T }).data
}
