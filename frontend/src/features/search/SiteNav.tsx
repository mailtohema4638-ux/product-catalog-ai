import { Link, NavLink, useMatch } from 'react-router-dom'
import { useCategories, useProduct } from '../catalogue/hooks'

/**
 * Site navigation: Home is always available, and the categories are added once they load.
 * If categories fail to load the rest of the site still works and there is still a way home.
 *
 * The category being browsed is highlighted: on its own page, and also while viewing one of its
 * products (however the customer got there: search, home or the category itself).
 */
export function SiteNav() {
  const { data: categories } = useCategories()
  const categoryMatch = useMatch('/categories/:slug')
  const productMatch = useMatch('/products/:id')
  // Same query key as the product page, so this reuses its request rather than adding one.
  const { data: product } = useProduct(productMatch?.params.id ?? '')

  const currentSlug = categoryMatch?.params.slug ?? product?.category.slug

  return (
    <nav aria-label="Main" className="site-nav">
      <ul>
        <li>
          {/* `end` states the intent (Home matches only "/"); React Router already treats the root link this way. */}
          <NavLink to="/" end>
            Home
          </NavLink>
        </li>
        {categories?.map((category) => {
          const isCurrent = category.slug === currentSlug

          return (
            <li key={category.slug}>
              <Link
                to={`/categories/${category.slug}`}
                className={isCurrent ? 'active' : undefined}
                // The category's own page is the current page; a product page is only within its section.
                aria-current={isCurrent ? (categoryMatch ? 'page' : 'true') : undefined}
              >
                {category.name}
              </Link>
            </li>
          )
        })}
      </ul>
    </nav>
  )
}
