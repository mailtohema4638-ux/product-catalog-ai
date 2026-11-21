import { useParams } from 'react-router-dom'
import { ApiError } from '../api/client'
import { ErrorState, Loading } from '../components/StateViews'
import { ProductDetail } from '../features/catalogue/ProductDetail'
import { useProduct } from '../features/catalogue/hooks'
import { usePageTitle } from '../hooks/usePageTitle'
import { NotFoundView } from './NotFoundPage'

export function ProductPage() {
  const { id = '' } = useParams()
  const { data, error, isPending, refetch } = useProduct(id)
  usePageTitle(data?.title ?? '')

  if (error instanceof ApiError && error.kind === 'not_found') {
    return <NotFoundView message="We couldn't find that product." />
  }

  return (
    <>
      {isPending && <Loading label="Loading product…" />}
      {error && <ErrorState error={error} onRetry={() => void refetch()} />}
      {data && <ProductDetail product={data} />}
    </>
  )
}
