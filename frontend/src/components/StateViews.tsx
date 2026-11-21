import { ApiError, userMessage } from '../api/client'

export function Loading({ label = 'Loading…' }: { label?: string }) {
  return (
    <p className="state" role="status">
      {label}
    </p>
  )
}

interface ErrorStateProps {
  error: unknown
  onRetry?: () => void
}

export function ErrorState({ error, onRetry }: ErrorStateProps) {
  const requestId = error instanceof ApiError ? error.requestId : undefined

  return (
    <div className="state state--error" role="alert">
      <p>{userMessage(error)}</p>
      {requestId && <p className="state__meta">Reference: {requestId}</p>}
      {onRetry && (
        <button type="button" className="button button--secondary" onClick={onRetry}>
          Try again
        </button>
      )}
    </div>
  )
}

export function EmptyState({ children }: { children: React.ReactNode }) {
  return <p className="state">{children}</p>
}
