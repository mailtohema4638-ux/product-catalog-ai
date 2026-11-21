import { useNavigate, useSearchParams } from 'react-router-dom'
import { useState } from 'react'

export function SearchBar() {
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const [term, setTerm] = useState(params.get('q') ?? '')

  function submit(event: React.SyntheticEvent<HTMLFormElement>) {
    event.preventDefault()
    const trimmed = term.trim()
    if (trimmed) {
      navigate(`/search?${new URLSearchParams({ q: trimmed })}`)
    }
  }

  return (
    <form role="search" className="search-bar" onSubmit={submit}>
      <label htmlFor="search-input" className="visually-hidden">
        Search products
      </label>
      <input
        id="search-input"
        type="search"
        name="q"
        value={term}
        maxLength={100}
        placeholder="Search products"
        autoComplete="off"
        onChange={(event) => setTerm(event.target.value)}
      />
      <button type="submit" className="button">
        Search
      </button>
    </form>
  )
}
