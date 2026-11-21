import { Link } from 'react-router-dom'
import { usePageTitle } from '../hooks/usePageTitle'

export function NotFoundView({ message = "We couldn't find that page." }: { message?: string }) {
  usePageTitle('Not found')

  return (
    <section className="state">
      <h1>Not found</h1>
      <p>{message}</p>
      <Link to="/">Back to the home page</Link>
    </section>
  )
}

export function NotFoundPage() {
  return <NotFoundView />
}
