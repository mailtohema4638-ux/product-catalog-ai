import { useState } from 'react'
import { Link } from 'react-router-dom'
import type { Product } from '../../api/types'
import { EnquiryDialog } from '../enquiry/EnquiryDialog'
import { formatMoney } from './format'
import { Rating } from './Rating'

export function ProductDetail({ product }: { product: Product }) {
  const [enquiring, setEnquiring] = useState(false)

  return (
    <article className="product-detail">
      <div className="product-detail__media">
        {product.image_url ? (
          <img src={product.image_url} alt={product.title} />
        ) : (
          <span className="product-card__placeholder" aria-hidden="true" />
        )}
      </div>

      <div className="product-detail__info">
        <p>
          <Link to={`/categories/${product.category.slug}`}>{product.category.name}</Link>
        </p>
        <h1>{product.title}</h1>
        <Rating rating={product.rating} />
        <p className="product-detail__price">{formatMoney(product.price)}</p>
        <p className="product-detail__description">{product.description}</p>

        <button type="button" className="button button--large" onClick={() => setEnquiring(true)}>
          Enquire
        </button>
      </div>

      {enquiring && <EnquiryDialog product={product} onClose={() => setEnquiring(false)} />}
    </article>
  )
}
