import { NavLink } from 'react-router-dom'
import { useCategories } from '../catalogue/hooks'

/**
 * Site navigation: Home is always available, and the categories are added once they load.
 * If categories fail to load the rest of the site still works and there is still a way home.
 */
export function SiteNav() {
  const { data: categories } = useCategories()

  return (
    <nav aria-label="Main" className="site-nav">
      <ul>
        <li>
          {/* `end` states the intent (Home matches only "/"); React Router already treats the root link this way. */}
          <NavLink to="/" end>
            Home
          </NavLink>
        </li>
        {categories?.map((category) => (
          <li key={category.slug}>
            <NavLink to={`/categories/${category.slug}`}>{category.name}</NavLink>
          </li>
        ))}
      </ul>
    </nav>
  )
}
