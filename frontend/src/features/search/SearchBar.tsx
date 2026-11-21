import { useRef, useState } from 'react'
import { useLocation, useNavigate, useSearchParams } from 'react-router-dom'

export function SearchBar() {
  const navigate = useNavigate()
  const location = useLocation()
  const [params] = useSearchParams()
  const [term, setTerm] = useState(params.get('q') ?? '')
  const inputRef = useRef<HTMLInputElement>(null)

  // Keep the box in step with where the customer is: empty on Home and on category pages (using
  // the menu is a fresh start), showing the searched word on the results page (so Back to results
  // is never an empty box over results), and left alone on product pages so the word stays while
  // the customer looks at a result.
  const here = `${location.pathname}${location.search}`
  const [seen, setSeen] = useState(here)
  if (here !== seen) {
    setSeen(here)
    if (location.pathname === '/' || location.pathname.startsWith('/categories/')) {
      setTerm('')
    } else if (location.pathname === '/search') {
      setTerm(params.get('q') ?? '')
    }
  }

  function submit(event: React.SyntheticEvent<HTMLFormElement>) {
    event.preventDefault()
    const trimmed = term.trim()
    if (trimmed) {
      navigate(`/search?${new URLSearchParams({ q: trimmed })}`)
    }
  }

  function clear() {
    setTerm('')
    inputRef.current?.focus()
  }

  return (
    <form role="search" className="search-bar" onSubmit={submit}>
      <label htmlFor="search-input" className="visually-hidden">
        Search products
      </label>
      <div className="search-bar__field">
        <input
          ref={inputRef}
          id="search-input"
          type="search"
          name="q"
          value={term}
          maxLength={100}
          placeholder="Search products"
          autoComplete="off"
          onChange={(event) => setTerm(event.target.value)}
        />
        {term !== '' && (
          <button type="button" className="search-bar__clear" aria-label="Clear search" onClick={clear}>
            <svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true" focusable="false">
              <path d="M3 3l10 10M13 3L3 13" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
            </svg>
          </button>
        )}
      </div>
      <button type="submit" className="button">
        Search
      </button>
    </form>
  )
}
