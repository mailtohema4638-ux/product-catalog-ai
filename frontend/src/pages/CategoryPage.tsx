import { useParams } from 'react-router-dom'
import { ApiError } from '../api/client'
import { EmptyState, ErrorState, Loading } from '../components/StateViews'
import { ProductGrid } from '../features/catalogue/ProductGrid'
import { useCategory } from '../features/catalogue/hooks'
import { usePageTitle } from '../hooks/usePageTitle'
import { NotFoundView } from './NotFoundPage'

export function CategoryPage() {
  const { slug = '' } = useParams()
  const { data, error, isPending, refetch } = useCategory(slug)
  usePageTitle(data?.category.name ?? '')

  if (error instanceof ApiError && error.kind === 'not_found') {
    return <NotFoundView message="We couldn't find that category." />
  }

  return (
    <>
      {isPending && <Loading label="Loading products…" />}
      {error && <ErrorState error={error} onRetry={() => void refetch()} />}
      {data && (
        <>
          <h1>{data.category.name}</h1>

          <section aria-labelledby="top-rated-heading">
            <h2 id="top-rated-heading">Top rated in {data.category.name}</h2>
            {data.top_rated.length > 0 ? (
              <ProductGrid products={data.top_rated} />
            ) : (
              <EmptyState>No products in this category yet.</EmptyState>
            )}
          </section>

          {data.others.length > 0 && (
            <section aria-labelledby="more-heading">
              <h2 id="more-heading">More in {data.category.name}</h2>
              <ProductGrid products={data.others} />
            </section>
          )}
        </>
      )}
    </>
  )
}
