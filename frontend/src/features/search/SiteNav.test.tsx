import { screen, waitFor, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { apiError, server } from '../../test/server'
import { categories, makeProduct } from '../../test/fixtures'
import { renderApp } from '../../test/render'

const drive = makeProduct({ id: '12', title: 'WD 4TB Gaming Drive' }) // Electronics
const jacket = makeProduct({
  id: '3',
  title: 'Mens Cotton Jacket',
  category: { slug: 'mens-clothing', name: "Men's Clothing" },
})

const nav = () => screen.getByRole('navigation', { name: 'Main' })
const tab = (name: string) => within(nav()).getByRole('link', { name })

describe('the header tab for the category being browsed', () => {
  it('is highlighted while viewing one of its products', async () => {
    server.use(http.get('/api/v1/products/12', () => HttpResponse.json({ data: drive })))

    renderApp('/products/12')
    await screen.findByRole('heading', { level: 1, name: 'WD 4TB Gaming Drive' })

    await waitFor(() => expect(tab('Electronics')).toHaveClass('active'))
    expect(tab('Electronics')).toHaveAttribute('aria-current', 'true')
    expect(tab("Men's Clothing")).not.toHaveClass('active')
    expect(tab("Men's Clothing")).not.toHaveAttribute('aria-current')
    expect(tab('Home')).not.toHaveAttribute('aria-current')
  })

  it('is highlighted after searching and opening a result', async () => {
    server.use(
      http.get('/api/v1/products', () => HttpResponse.json({ data: [drive, jacket] })),
      http.get('/api/v1/products/3', () => HttpResponse.json({ data: jacket })),
    )

    const { user } = renderApp('/search?q=a')
    await user.click(await screen.findByRole('link', { name: /Mens Cotton Jacket/ }))

    await screen.findByRole('heading', { level: 1, name: 'Mens Cotton Jacket' })
    await waitFor(() => expect(tab("Men's Clothing")).toHaveClass('active'))
    expect(tab('Electronics')).not.toHaveClass('active')
  })

  it('is highlighted when opening a product from the home page', async () => {
    server.use(
      http.get('/api/v1/featured', () => HttpResponse.json({ data: [drive] })),
      http.get('/api/v1/products/12', () => HttpResponse.json({ data: drive })),
    )

    const { user } = renderApp('/')
    await user.click(await screen.findByRole('link', { name: /WD 4TB Gaming Drive/ }))

    await waitFor(() => expect(tab('Electronics')).toHaveClass('active'))
  })

  it('moves to the new category when the customer picks another one, and clears on Home', async () => {
    server.use(
      http.get('/api/v1/products/12', () => HttpResponse.json({ data: drive })),
      http.get('/api/v1/categories/mens-clothing', () =>
        HttpResponse.json({ data: { category: categories[1], top_rated: [], others: [] } }),
      ),
      http.get('/api/v1/featured', () => HttpResponse.json({ data: [drive] })),
    )

    const { user } = renderApp('/products/12')
    await waitFor(() => expect(tab('Electronics')).toHaveClass('active'))

    await user.click(tab("Men's Clothing"))
    await screen.findByRole('heading', { level: 1, name: "Men's Clothing" })
    expect(tab("Men's Clothing")).toHaveClass('active')
    expect(tab("Men's Clothing")).toHaveAttribute('aria-current', 'page')
    expect(tab('Electronics')).not.toHaveClass('active')

    await user.click(tab('Home'))
    await screen.findByRole('heading', { level: 1, name: 'Top rated products' })
    expect(tab('Home')).toHaveClass('active')
    for (const category of categories) {
      expect(tab(category.name)).not.toHaveClass('active')
    }
  })

  it('does not make an extra product request just to highlight the tab', async () => {
    let requests = 0
    server.use(
      http.get('/api/v1/products/12', () => {
        requests += 1
        return HttpResponse.json({ data: drive })
      }),
    )

    renderApp('/products/12')
    await waitFor(() => expect(tab('Electronics')).toHaveClass('active'))

    expect(requests).toBe(1)
  })

  it('highlights nothing for a product that does not exist', async () => {
    server.use(http.get('/api/v1/products/999', () => apiError(404, 'not_found')))

    renderApp('/products/999')
    await screen.findByText("We couldn't find that product.")

    expect(within(nav()).queryAllByRole('link').filter((link) => link.classList.contains('active'))).toHaveLength(0)
  })

  it('keeps the category page itself marked as the current page', async () => {
    server.use(
      http.get('/api/v1/categories/electronics', () =>
        HttpResponse.json({ data: { category: categories[0], top_rated: [], others: [] } }),
      ),
    )

    renderApp('/categories/electronics')
    await screen.findByRole('heading', { level: 1, name: 'Electronics' })

    await waitFor(() => expect(tab('Electronics')).toHaveAttribute('aria-current', 'page'))
  })
})
