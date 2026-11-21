import { Link } from 'react-router-dom'
import type { Product } from '../../api/types'
import { formatMoney } from './format'
import { Rating } from './Rating'

export function ProductCard({ product }: { product: Product }) {
  return (
    <Link to={`/products/${encodeURIComponent(product.id)}`} className="product-card">
      <div className="product-card__media">
        {product.image_url ? (
          <img src={product.image_url} alt="" loading="lazy" />
        ) : (
          <span className="product-card__placeholder" aria-hidden="true" />
        )}
      </div>
      <h3 className="product-card__title">{product.title}</h3>
      <p className="product-card__meta">
        <span className="product-card__price">{formatMoney(product.price)}</span>
        <Rating rating={product.rating} />
      </p>
    </Link>
  )
}

export function ProductGrid({ products }: { products: Product[] }) {
  return (
    <ul className="product-grid">
      {products.map((product) => (
        <li key={product.id}>
          <ProductCard product={product} />
        </li>
      ))}
    </ul>
  )
}
