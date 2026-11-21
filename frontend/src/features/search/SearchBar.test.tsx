import { act, screen, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { server } from '../../test/server'
import { categories, makeProduct } from '../../test/fixtures'
import { renderApp } from '../../test/render'

const pens = makeProduct({ id: 'gel-pens', title: 'Gel Pens, Assorted Colours' })
const chair = makeProduct({ id: 'office-chair', title: 'Ergonomic Office Chair' })
const catalogue = [pens, chair]

type User = ReturnType<typeof renderApp>['user']

const box = () => screen.getByRole('searchbox', { name: 'Search products' })
const clearButton = () => screen.queryByRole('button', { name: 'Clear search' })
const tab = (name: string) => within(screen.getByRole('navigation', { name: 'Main' })).getByRole('link', { name })

async function search(user: User, word: string) {
  await user.type(box(), word)
  await user.click(screen.getByRole('button', { name: 'Search' }))
}

beforeEach(() => {
  server.use(
    http.get('/api/v1/featured', () => HttpResponse.json({ data: catalogue })),
    http.get('/api/v1/products', ({ request }) => {
      const q = (new URL(request.url).searchParams.get('q') ?? '').toLowerCase()
      return HttpResponse.json({ data: catalogue.filter((p) => p.title.toLowerCase().includes(q)) })
    }),
    http.get('/api/v1/products/:id', ({ params }) => {
      const product = catalogue.find((p) => p.id === params.id)
      return product ? HttpResponse.json({ data: product }) : HttpResponse.json({}, { status: 404 })
    }),
    http.get('/api/v1/categories/electronics', () =>
      HttpResponse.json({ data: { category: categories[0], top_rated: [], others: [] } }),
    ),
  )
})

describe('the clear (X) button', () => {
  it('only appears when there is something to clear', async () => {
    const { user } = renderApp('/')
    await screen.findByRole('heading', { level: 1, name: 'Top rated products' })
    expect(clearButton()).not.toBeInTheDocument()

    await user.type(box(), 'a')
    expect(clearButton()).toBeInTheDocument()

    await user.clear(box())
    expect(clearButton()).not.toBeInTheDocument()
  })

  it('empties the box and puts the cursor back in it, without searching or leaving the page', async () => {
    const { user, router } = renderApp('/')
    await screen.findByRole('heading', { level: 1, name: 'Top rated products' })
    await user.type(box(), 'chair')

    await user.click(clearButton()!)

    expect(box()).toHaveValue('')
    expect(box()).toHaveFocus()
    expect(clearButton()).not.toBeInTheDocument()
    expect(router.state.location.pathname).toBe('/')
  })

  it('leaves the results on screen when cleared from the results page', async () => {
    const { user, router } = renderApp('/search?q=pens')
    expect(await screen.findByText('Gel Pens, Assorted Colours')).toBeInTheDocument()
    expect(box()).toHaveValue('pens')

    await user.click(clearButton()!)

    expect(box()).toHaveValue('')
    expect(router.state.location.search).toBe('?q=pens')
    expect(screen.getByText('Gel Pens, Assorted Colours')).toBeInTheDocument()
  })
})

describe('the searched word', () => {
  it('stays in the box while the customer looks at a result', async () => {
    const { user } = renderApp('/')
    await screen.findByRole('heading', { level: 1, name: 'Top rated products' })
    await search(user, 'pens')

    await user.click(await screen.findByRole('link', { name: /Gel Pens/ }))

    await screen.findByRole('heading', { level: 1, name: 'Gel Pens, Assorted Colours' })
    expect(box()).toHaveValue('pens')
  })

  it.each([
    ['from the results page', '/search?q=pens', 'Gel Pens, Assorted Colours'],
    ['from a product page', '/products/gel-pens', 'Gel Pens, Assorted Colours'],
  ])('is cleared on choosing a category tab %s', async (_where, start, ready) => {
    const { user } = renderApp(start)
    await screen.findByText(ready)
    if (start.startsWith('/products')) {
      await user.type(box(), 'pens')
    }
    expect(box()).toHaveValue('pens')

    await user.click(tab('Electronics'))

    await screen.findByRole('heading', { level: 1, name: 'Electronics' })
    expect(box()).toHaveValue('')
  })

  it('is shown when the results page is opened directly', async () => {
    renderApp('/search?q=gold')

    await screen.findByText(/No products match/)
    expect(box()).toHaveValue('gold')
  })

  it('is cleared on going back to Home, ready for a fresh search', async () => {
    const { user } = renderApp('/')
    await screen.findByRole('heading', { level: 1, name: 'Top rated products' })
    await search(user, 'pens')
    await user.click(await screen.findByRole('link', { name: /Gel Pens/ }))
    await screen.findByRole('heading', { level: 1, name: 'Gel Pens, Assorted Colours' })
    expect(box()).toHaveValue('pens')

    await user.click(tab('Home'))

    await screen.findByRole('heading', { level: 1, name: 'Top rated products' })
    expect(box()).toHaveValue('')

    await search(user, 'chair')
    expect(await screen.findByRole('heading', { level: 1, name: 'Results for “chair”' })).toBeInTheDocument()
    expect(box()).toHaveValue('chair')
    expect(screen.getByText('Ergonomic Office Chair')).toBeInTheDocument()
  })

  it('is also cleared by the site name in the header, which links home', async () => {
    const { user } = renderApp('/search?q=pens')
    await screen.findByText('Gel Pens, Assorted Colours')

    await user.click(screen.getByRole('link', { name: 'Product Catalogue' }))

    await screen.findByRole('heading', { level: 1, name: 'Top rated products' })
    expect(box()).toHaveValue('')
  })

  it('is restored with the results when the customer presses Back to them', async () => {
    const { user, router } = renderApp('/')
    await screen.findByRole('heading', { level: 1, name: 'Top rated products' })
    await search(user, 'pens')
    await user.click(await screen.findByRole('link', { name: /Gel Pens/ }))
    await screen.findByRole('heading', { level: 1, name: 'Gel Pens, Assorted Colours' })
    await user.click(tab('Home'))
    await screen.findByRole('heading', { level: 1, name: 'Top rated products' })
    expect(box()).toHaveValue('')

    await act(() => router.navigate(-1)) // product page
    await screen.findByRole('heading', { level: 1, name: 'Gel Pens, Assorted Colours' })
    await act(() => router.navigate(-1)) // results

    expect(await screen.findByRole('heading', { level: 1, name: 'Results for “pens”' })).toBeInTheDocument()
    expect(box()).toHaveValue('pens')
  })
})
