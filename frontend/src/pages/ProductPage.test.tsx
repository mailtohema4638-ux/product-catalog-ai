import { screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { apiError, server } from '../test/server'
import { featured, makeProduct } from '../test/fixtures'
import { renderApp } from '../test/render'

describe('product page', () => {
  it('clicking a product tile opens its details page', async () => {
    const product = makeProduct({
      id: '12',
      title: 'WD 4TB Gaming Drive',
      description: 'Expand your PS4 gaming experience.',
    })
    server.use(
      http.get('/api/v1/featured', () => HttpResponse.json({ data: featured })),
      http.get('/api/v1/products/12', () => HttpResponse.json({ data: product })),
    )

    const { user, router } = renderApp('/')
    await user.click(await screen.findByRole('link', { name: /WD 4TB Gaming Drive/ }))

    expect(await screen.findByRole('heading', { level: 1, name: 'WD 4TB Gaming Drive' })).toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/products/12')
    expect(screen.getByText('Expand your PS4 gaming experience.')).toBeInTheDocument()
    expect(screen.getByText('$114.00')).toBeInTheDocument()
    expect(within(screen.getByRole('main')).getByRole('link', { name: 'Electronics' })).toHaveAttribute(
      'href',
      '/categories/electronics',
    )
    expect(screen.getByRole('img', { name: 'WD 4TB Gaming Drive' })).toHaveAttribute('src', 'https://images.test/12.jpg')
    expect(screen.getByRole('button', { name: 'Enquire' })).toBeInTheDocument()
  })

  it('exposes the rating to assistive technology', async () => {
    server.use(http.get('/api/v1/products/12', () => HttpResponse.json({ data: makeProduct({ id: '12', title: 'Drive' }) })))

    renderApp('/products/12')

    expect(await screen.findByLabelText('Rated 4.8 out of 5 from 400 reviews')).toBeInTheDocument()
  })

  it('copes with a product that has no image or reviews', async () => {
    server.use(
      http.get('/api/v1/products/7', () =>
        HttpResponse.json({
          data: makeProduct({ id: '7', title: 'Plain Thing', image_url: null, rating: { average: 0, count: 0 } }),
        }),
      ),
    )

    renderApp('/products/7')

    expect(await screen.findByRole('heading', { level: 1, name: 'Plain Thing' })).toBeInTheDocument()
    expect(screen.getByText('No reviews yet')).toBeInTheDocument()
    expect(screen.queryByRole('img')).not.toBeInTheDocument()
  })

  it('shows not found for an unknown product', async () => {
    server.use(http.get('/api/v1/products/999', () => apiError(404, 'not_found')))

    renderApp('/products/999')

    expect(await screen.findByText("We couldn't find that product.")).toBeInTheDocument()
  })

  it('shows not found for an unknown page', async () => {
    renderApp('/no/such/page')

    expect(await screen.findByRole('heading', { name: 'Not found' })).toBeInTheDocument()
  })
})
