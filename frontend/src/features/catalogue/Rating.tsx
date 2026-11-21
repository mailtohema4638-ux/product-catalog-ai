import type { Product } from '../../api/types'

export function Rating({ rating }: { rating: Product['rating'] }) {
  if (rating.count === 0) {
    return <span className="rating rating--none">No reviews yet</span>
  }

  return (
    <span className="rating" aria-label={`Rated ${rating.average.toFixed(1)} out of 5 from ${rating.count} reviews`}>
      <span aria-hidden="true">★ {rating.average.toFixed(1)}</span>
      <span aria-hidden="true" className="rating__count">
        ({rating.count})
      </span>
    </span>
  )
}
