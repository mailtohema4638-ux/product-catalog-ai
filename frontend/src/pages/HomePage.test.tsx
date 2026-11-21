import { screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { apiError, server } from '../test/server'
import { featured } from '../test/fixtures'
import { renderApp } from '../test/render'

describe('home page', () => {
  it('shows the 5 featured products with formatted prices', async () => {
    server.use(http.get('/api/v1/featured', () => HttpResponse.json({ data: featured })))

    renderApp('/')

    expect(await screen.findByRole('heading', { level: 1, name: 'Top rated products' })).toBeInTheDocument()
    const grid = await screen.findByRole('list', { name: '' })
    const tiles = within(grid).getAllByRole('listitem')

    expect(tiles).toHaveLength(5)
    expect(tiles[0]).toHaveTextContent('WD 4TB Gaming Drive')
    expect(tiles[0]).toHaveTextContent('$114.00')
    expect(tiles[3]).toHaveTextContent('$9.85')
    expect(tiles[4]).toHaveTextContent('$695.00')
  })

  it('shows a loading state first', () => {
    server.use(http.get('/api/v1/featured', () => new Promise(() => {})))

    renderApp('/')

    expect(screen.getByRole('status')).toHaveTextContent(/loading/i)
  })

  it('shows a friendly error with a reference, and recovers on retry', async () => {
    let calls = 0
    server.use(
      http.get('/api/v1/featured', () => {
        calls += 1
        return calls === 1 ? apiError(503, 'catalogue_unavailable') : HttpResponse.json({ data: featured })
      }),
    )

    const { user } = renderApp('/')

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('temporarily unavailable')
    expect(alert).toHaveTextContent('Reference: req-123')
    expect(alert).not.toHaveTextContent('Server message')

    await user.click(screen.getByRole('button', { name: 'Try again' }))

    expect(await screen.findByText('WD 4TB Gaming Drive')).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('shows an empty state when nothing is featured', async () => {
    server.use(http.get('/api/v1/featured', () => HttpResponse.json({ data: [] })))

    renderApp('/')

    expect(await screen.findByText('No featured products yet.')).toBeInTheDocument()
  })

  it('shows the category navigation and links to each category', async () => {
    server.use(http.get('/api/v1/featured', () => HttpResponse.json({ data: featured })))

    renderApp('/')

    const nav = await screen.findByRole('navigation', { name: 'Categories' })
    expect(within(nav).getByRole('link', { name: 'Electronics' })).toHaveAttribute('href', '/categories/electronics')
    expect(within(nav).getByRole('link', { name: "Men's Clothing" })).toHaveAttribute('href', '/categories/mens-clothing')
  })

  it('still works when the categories fail to load', async () => {
    server.use(
      http.get('/api/v1/categories', () => apiError(503, 'catalogue_unavailable')),
      http.get('/api/v1/featured', () => HttpResponse.json({ data: featured })),
    )

    renderApp('/')

    expect(await screen.findByText('WD 4TB Gaming Drive')).toBeInTheDocument()
    expect(screen.queryByRole('navigation', { name: 'Categories' })).not.toBeInTheDocument()
  })
})
