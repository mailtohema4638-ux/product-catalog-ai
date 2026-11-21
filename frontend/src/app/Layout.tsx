import { Link, Outlet, useRouteError } from 'react-router-dom'
import { CategoryNav } from '../features/search/CategoryNav'
import { SearchBar } from '../features/search/SearchBar'

export function Layout() {
  return (
    <>
      <a href="#main-content" className="skip-link">
        Skip to content
      </a>
      <header className="site-header">
        <div className="container site-header__inner">
          <Link to="/" className="brand">
            Product Catalogue
          </Link>
          <SearchBar />
        </div>
        <div className="container">
          <CategoryNav />
        </div>
      </header>
      <main id="main-content" className="container">
        <Outlet />
      </main>
    </>
  )
}

/** Last line of defence for unexpected render errors: never a blank screen. */
export function RouteError() {
  useRouteError()

  return (
    <main className="container">
      <div className="state state--error" role="alert">
        <h1>Something went wrong</h1>
        <p>Please refresh the page or go back to the home page.</p>
        <Link to="/">Back to the home page</Link>
      </div>
    </main>
  )
}
