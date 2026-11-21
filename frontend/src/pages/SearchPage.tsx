import { useSearchParams } from 'react-router-dom'
import { EmptyState, ErrorState, Loading } from '../components/StateViews'
import { ProductGrid } from '../features/catalogue/ProductGrid'
import { useSearch } from '../features/catalogue/hooks'
import { usePageTitle } from '../hooks/usePageTitle'

export function SearchPage() {
  const [params] = useSearchParams()
  const term = (params.get('q') ?? '').trim()
  usePageTitle(term ? `Search: ${term}` : 'Search')
  const { data, error, isFetching, refetch } = useSearch(term)

  if (!term) {
    return (
      <>
        <h1>Search</h1>
        <EmptyState>Enter a search term to find products.</EmptyState>
      </>
    )
  }

  return (
    <section aria-labelledby="search-heading">
      <h1 id="search-heading">Results for “{term}”</h1>
      {isFetching && !data && <Loading label="Searching…" />}
      {error && <ErrorState error={error} onRetry={() => void refetch()} />}
      {data &&
        (data.length > 0 ? (
          <>
            <p className="results-count" role="status">
              {data.length} {data.length === 1 ? 'product' : 'products'} found
            </p>
            <ProductGrid products={data} />
          </>
        ) : (
          <EmptyState>No products match “{term}”. Try a different word.</EmptyState>
        ))}
    </section>
  )
}
