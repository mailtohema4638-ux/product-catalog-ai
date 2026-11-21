import { NavLink } from 'react-router-dom'
import { useCategories } from '../catalogue/hooks'

/** Navigation degrades quietly: if categories fail to load the rest of the site still works. */
export function CategoryNav() {
  const { data: categories } = useCategories()

  if (!categories?.length) {
    return null
  }

  return (
    <nav aria-label="Categories" className="category-nav">
      <ul>
        {categories.map((category) => (
          <li key={category.slug}>
            <NavLink to={`/categories/${category.slug}`}>{category.name}</NavLink>
          </li>
        ))}
      </ul>
    </nav>
  )
}
