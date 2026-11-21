import { screen } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { server } from '../test/server'
import { featured, makeProduct } from '../test/fixtures'
import { renderApp } from '../test/render'

describe('search', () => {
  it('searching shows only the products that match', async () => {
    const requested: string[] = []
    server.use(
      http.get('/api/v1/featured', () => HttpResponse.json({ data: featured })),
      http.get('/api/v1/products', ({ request }) => {
        const q = new URL(request.url).searchParams.get('q') ?? ''
        requested.push(q)
        return HttpResponse.json({ data: featured.filter((p) => p.title.toLowerCase().includes(q.toLowerCase())) })
      }),
    )

    const { user, router } = renderApp('/')
    await screen.findByText('WD 4TB Gaming Drive')

    await user.type(screen.getByRole('searchbox', { name: 'Search products' }), '  jacket ')
    await user.click(screen.getByRole('button', { name: 'Search' }))

    expect(await screen.findByRole('heading', { level: 1, name: 'Results for “jacket”' })).toBeInTheDocument()
    expect(await screen.findByText('Mens Cotton Jacket')).toBeInTheDocument()
    expect(screen.getByText('1 product found')).toBeInTheDocument()
    expect(screen.queryByText('WD 4TB Gaming Drive')).not.toBeInTheDocument()
    expect(requested).toEqual(['jacket'])
    expect(router.state.location.search).toBe('?q=jacket')
  })

  it('shows an empty state when nothing matches', async () => {
    server.use(http.get('/api/v1/products', () => HttpResponse.json({ data: [] })))

    renderApp('/search?q=banana')

    expect(await screen.findByText('No products match “banana”. Try a different word.')).toBeInTheDocument()
  })

  it('does not search for a blank term', async () => {
    server.use(http.get('/api/v1/featured', () => HttpResponse.json({ data: featured })))
    // No /products handler: any request would fail the test via onUnhandledRequest.

    const { user, router } = renderApp('/')
    await screen.findByText('WD 4TB Gaming Drive')

    await user.type(screen.getByRole('searchbox', { name: 'Search products' }), '   ')
    await user.click(screen.getByRole('button', { name: 'Search' }))

    expect(router.state.location.pathname).toBe('/')
  })

  it('asks for a term when the page is opened without one', async () => {
    renderApp('/search')

    expect(await screen.findByText('Enter a search term to find products.')).toBeInTheDocument()
  })

  it('renders hostile search text as plain text, never as markup', async () => {
    const hostile = '<img src=x onerror=alert(1)>'
    server.use(http.get('/api/v1/products', () => HttpResponse.json({ data: [] })))

    renderApp(`/search?q=${encodeURIComponent(hostile)}`)

    expect(await screen.findByRole('heading', { level: 1 })).toHaveTextContent(`Results for “${hostile}”`)
    expect(document.querySelector('main img')).toBeNull()
  })

  it('shows product titles from the API as text, never as markup', async () => {
    server.use(
      http.get('/api/v1/products', () =>
        HttpResponse.json({ data: [makeProduct({ id: '1', title: '<b>Bold</b> <script>x()</script>' })] }),
      ),
    )

    renderApp('/search?q=bold')

    expect(await screen.findByText('<b>Bold</b> <script>x()</script>')).toBeInTheDocument()
    expect(document.querySelector('main b, main script')).toBeNull()
  })
})
