import { screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import type { CategoryPage } from '../api/types'
import { apiError, server } from '../test/server'
import { makeProduct } from '../test/fixtures'
import { renderApp } from '../test/render'

const electronics: CategoryPage = {
  category: { slug: 'electronics', name: 'Electronics' },
  top_rated: [makeProduct({ id: '12', title: 'WD 4TB Gaming Drive' }), makeProduct({ id: '11', title: 'Silicon Power SSD' })],
  others: [makeProduct({ id: '9', title: 'WD 2TB Elements', rating: { average: 3.3, count: 203 } })],
}

describe('category page', () => {
  it('shows top rated products first, then the additional products in that category', async () => {
    server.use(http.get('/api/v1/categories/electronics', () => HttpResponse.json({ data: electronics })))

    renderApp('/categories/electronics')

    expect(await screen.findByRole('heading', { level: 1, name: 'Electronics' })).toBeInTheDocument()

    const topRated = screen.getByRole('region', { name: 'Top rated in Electronics' })
    expect(within(topRated).getAllByRole('listitem')).toHaveLength(2)
    expect(topRated).toHaveTextContent('WD 4TB Gaming Drive')

    const more = screen.getByRole('region', { name: 'More in Electronics' })
    expect(within(more).getAllByRole('listitem')).toHaveLength(1)
    expect(more).toHaveTextContent('WD 2TB Elements')

    // Top rated precedes the rest on the page.
    expect(topRated.compareDocumentPosition(more) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy()
  })

  it('omits the "more" section when every product is top rated', async () => {
    server.use(
      http.get('/api/v1/categories/electronics', () => HttpResponse.json({ data: { ...electronics, others: [] } })),
    )

    renderApp('/categories/electronics')

    await screen.findByRole('heading', { level: 1, name: 'Electronics' })
    expect(screen.queryByRole('region', { name: /More in/ })).not.toBeInTheDocument()
  })

  it('shows not found for an unknown category', async () => {
    server.use(http.get('/api/v1/categories/nope', () => apiError(404, 'not_found')))

    renderApp('/categories/nope')

    expect(await screen.findByText("We couldn't find that category.")).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Back to the home page' })).toHaveAttribute('href', '/')
  })

  it('shows an error state when the catalogue is unavailable', async () => {
    server.use(http.get('/api/v1/categories/electronics', () => apiError(503, 'catalogue_unavailable')))

    renderApp('/categories/electronics')

    expect(await screen.findByRole('alert')).toHaveTextContent('temporarily unavailable')
  })
})
