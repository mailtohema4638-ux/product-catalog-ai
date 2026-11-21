import type { RouteObject } from 'react-router-dom'
import { CategoryPage } from '../pages/CategoryPage'
import { HomePage } from '../pages/HomePage'
import { NotFoundPage } from '../pages/NotFoundPage'
import { ProductPage } from '../pages/ProductPage'
import { SearchPage } from '../pages/SearchPage'
import { Layout, RouteError } from './Layout'

export const routes: RouteObject[] = [
  {
    path: '/',
    element: <Layout />,
    errorElement: <RouteError />,
    children: [
      { index: true, element: <HomePage /> },
      { path: 'categories/:slug', element: <CategoryPage /> },
      { path: 'search', element: <SearchPage /> },
      { path: 'products/:id', element: <ProductPage /> },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
]
