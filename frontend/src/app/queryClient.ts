import { QueryClient } from '@tanstack/react-query'
import { ApiError } from '../api/client'

/** Retry once, and only for failures that can plausibly be transient. */
function shouldRetry(failureCount: number, error: unknown): boolean {
  return failureCount < 1 && error instanceof ApiError && (error.kind === 'unavailable' || error.kind === 'network')
}

export function createQueryClient(options: { retry?: boolean } = {}): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 5 * 60 * 1000,
        refetchOnWindowFocus: false,
        retry: options.retry === false ? false : shouldRetry,
      },
    },
  })
}
