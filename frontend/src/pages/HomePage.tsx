import { EmptyState, ErrorState, Loading } from '../components/StateViews'
import { ProductGrid } from '../features/catalogue/ProductGrid'
import { useFeatured } from '../features/catalogue/hooks'
import { usePageTitle } from '../hooks/usePageTitle'

export function HomePage() {
  usePageTitle('')
  const { data, error, isPending, refetch } = useFeatured()

  return (
    <section aria-labelledby="featured-heading">
      <h1 id="featured-heading">Top rated products</h1>
      {isPending && <Loading label="Loading top rated products…" />}
      {error && <ErrorState error={error} onRetry={() => void refetch()} />}
      {data && (data.length > 0 ? <ProductGrid products={data} /> : <EmptyState>No featured products yet.</EmptyState>)}
    </section>
  )
}
