import { screen, waitFor, within } from '@testing-library/react'
import { http, HttpResponse } from 'msw'
import { apiError, server } from '../../test/server'
import { makeProduct } from '../../test/fixtures'
import { renderApp } from '../../test/render'

const product = makeProduct({ id: '12', title: 'WD 4TB Gaming Drive' })

async function openDialog() {
  server.use(http.get('/api/v1/products/12', () => HttpResponse.json({ data: product })))
  const rendered = renderApp('/products/12')
  await rendered.user.click(await screen.findByRole('button', { name: 'Enquire' }))
  const dialog = await screen.findByRole('dialog', { name: 'Enquire about this product' })
  return { ...rendered, dialog }
}

async function fillValid(user: ReturnType<typeof renderApp>['user'], dialog: HTMLElement) {
  await user.type(within(dialog).getByLabelText(/Your name/), 'Jane Doe')
  await user.type(within(dialog).getByLabelText(/Email address/), 'jane@example.com')
}

describe('enquiry', () => {
  it('opens a form for the product when Enquire is clicked', async () => {
    const { dialog } = await openDialog()

    expect(within(dialog).getByText('WD 4TB Gaming Drive')).toBeInTheDocument()
    expect(within(dialog).getByLabelText(/Your name/)).toBeInTheDocument()
    expect(within(dialog).getByLabelText(/Email address/)).toBeInTheDocument()
    expect(within(dialog).getByLabelText('Phone (optional)')).toBeInTheDocument()
    expect(within(dialog).getByLabelText('Message (optional)')).toBeInTheDocument()
  })

  it('submits the details and thanks the customer', async () => {
    let payload: unknown
    server.use(
      http.post('/api/v1/enquiries', async ({ request }) => {
        payload = await request.json()
        return HttpResponse.json({ data: { reference: '01ABCDEF' } }, { status: 201 })
      }),
    )
    const { user, dialog } = await openDialog()

    await user.type(within(dialog).getByLabelText(/Your name/), '  Jane Doe ')
    await user.type(within(dialog).getByLabelText(/Email address/), 'jane@example.com')
    await user.type(within(dialog).getByLabelText('Phone (optional)'), '+1 555 0100')
    await user.type(within(dialog).getByLabelText('Message (optional)'), 'Is this in stock?')
    await user.click(within(dialog).getByRole('button', { name: 'Send enquiry' }))

    expect(await within(dialog).findByRole('heading', { name: 'Thank you' })).toBeInTheDocument()
    expect(within(dialog).getByText(/Reference: 01ABCDEF/)).toBeInTheDocument()
    expect(payload).toEqual({
      product_id: '12',
      name: 'Jane Doe',
      email: 'jane@example.com',
      phone: '+1 555 0100',
      message: 'Is this in stock?',
      website: '',
    })
  })

  it('sends only the required fields when the optional ones are blank', async () => {
    let payload: Record<string, unknown> = {}
    server.use(
      http.post('/api/v1/enquiries', async ({ request }) => {
        payload = (await request.json()) as Record<string, unknown>
        return HttpResponse.json({ data: { reference: 'R' } }, { status: 201 })
      }),
    )
    const { user, dialog } = await openDialog()

    await fillValid(user, dialog)
    await user.click(within(dialog).getByRole('button', { name: 'Send enquiry' }))

    await within(dialog).findByRole('heading', { name: 'Thank you' })
    expect(payload).not.toHaveProperty('phone')
    expect(payload).not.toHaveProperty('message')
  })

  it('closes after the thank you message', async () => {
    server.use(http.post('/api/v1/enquiries', () => HttpResponse.json({ data: { reference: 'R' } }, { status: 201 })))
    const { user, dialog } = await openDialog()

    await fillValid(user, dialog)
    await user.click(within(dialog).getByRole('button', { name: 'Send enquiry' }))
    await user.click(await within(dialog).findByRole('button', { name: 'Close' }))

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  it('can be cancelled without sending anything', async () => {
    const { user, dialog } = await openDialog()

    await user.click(within(dialog).getByRole('button', { name: 'Cancel' }))

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument())
  })

  describe('validation', () => {
    it('shows inline errors, focuses the first invalid field and sends nothing', async () => {
      const { user, dialog } = await openDialog()

      await user.click(within(dialog).getByRole('button', { name: 'Send enquiry' }))

      expect(within(dialog).getByText('Please enter your name (at least 2 characters).')).toBeInTheDocument()
      expect(within(dialog).getByText('Please enter your email address.')).toBeInTheDocument()
      expect(within(dialog).getByLabelText(/Your name/)).toHaveFocus()
      expect(within(dialog).getByLabelText(/Your name/)).toHaveAttribute('aria-invalid', 'true')
      expect(within(dialog).getByLabelText(/Your name/)).toHaveAccessibleDescription(
        'Please enter your name (at least 2 characters).',
      )
      // No POST handler registered: a request would fail the test.
    })

    it('rejects a malformed email address', async () => {
      const { user, dialog } = await openDialog()

      await user.type(within(dialog).getByLabelText(/Your name/), 'Jane')
      await user.type(within(dialog).getByLabelText(/Email address/), 'not-an-email')
      await user.click(within(dialog).getByRole('button', { name: 'Send enquiry' }))

      expect(within(dialog).getByText('Please enter a valid email address.')).toBeInTheDocument()
    })

    it('rejects a phone number with letters', async () => {
      const { user, dialog } = await openDialog()

      await fillValid(user, dialog)
      await user.type(within(dialog).getByLabelText('Phone (optional)'), 'call me')
      await user.click(within(dialog).getByRole('button', { name: 'Send enquiry' }))

      expect(within(dialog).getByText('Please enter a valid phone number.')).toBeInTheDocument()
    })

    it('shows server-side field errors next to the fields', async () => {
      server.use(
        http.post('/api/v1/enquiries', () =>
          apiError(422, 'validation_failed', 'Invalid', { details: { email: ['The email field must be a valid email address.'] } }),
        ),
      )
      const { user, dialog } = await openDialog()

      await fillValid(user, dialog)
      await user.click(within(dialog).getByRole('button', { name: 'Send enquiry' }))

      expect(await within(dialog).findByText('The email field must be a valid email address.')).toBeInTheDocument()
      expect(within(dialog).getByLabelText(/Email address/)).toHaveAttribute('aria-invalid', 'true')
    })
  })

  describe('failures', () => {
    it.each([
      [429, 'too_many_requests', 'Too many attempts'],
      [503, 'catalogue_unavailable', 'temporarily unavailable'],
      [500, 'server_error', 'Something went wrong'],
    ])('shows a friendly message for HTTP %i and keeps the form so nothing is lost', async (status, code, text) => {
      server.use(http.post('/api/v1/enquiries', () => apiError(status, code, 'internal detail')))
      const { user, dialog } = await openDialog()

      await fillValid(user, dialog)
      await user.click(within(dialog).getByRole('button', { name: 'Send enquiry' }))

      expect(await within(dialog).findByRole('alert')).toHaveTextContent(text)
      expect(within(dialog).queryByText('internal detail')).not.toBeInTheDocument()
      expect(within(dialog).getByLabelText(/Your name/)).toHaveValue('Jane Doe')
      expect(within(dialog).getByRole('button', { name: 'Send enquiry' })).toBeEnabled()
    })

    it('shows a network message when the server cannot be reached', async () => {
      server.use(http.post('/api/v1/enquiries', () => HttpResponse.error()))
      const { user, dialog } = await openDialog()

      await fillValid(user, dialog)
      await user.click(within(dialog).getByRole('button', { name: 'Send enquiry' }))

      expect(await within(dialog).findByRole('alert')).toHaveTextContent("couldn't reach the server")
    })

    it('prevents double submission while sending', async () => {
      let calls = 0
      let release: () => void = () => {}
      server.use(
        http.post('/api/v1/enquiries', async () => {
          calls += 1
          await new Promise<void>((resolve) => (release = resolve))
          return HttpResponse.json({ data: { reference: 'R' } }, { status: 201 })
        }),
      )
      const { user, dialog } = await openDialog()

      await fillValid(user, dialog)
      const send = within(dialog).getByRole('button', { name: 'Send enquiry' })
      await user.click(send)

      expect(await within(dialog).findByRole('button', { name: 'Sending…' })).toBeDisabled()
      await user.click(within(dialog).getByRole('button', { name: 'Sending…' }))
      expect(calls).toBe(1)

      release()
      expect(await within(dialog).findByRole('heading', { name: 'Thank you' })).toBeInTheDocument()
    })
  })

  describe('bot protection', () => {
    it('hides the honeypot from people and assistive technology, and sends it empty', async () => {
      let payload: Record<string, unknown> = {}
      server.use(
        http.post('/api/v1/enquiries', async ({ request }) => {
          payload = (await request.json()) as Record<string, unknown>
          return HttpResponse.json({ data: { reference: 'R' } }, { status: 201 })
        }),
      )
      const { user, dialog } = await openDialog()

      const honeypot = dialog.querySelector<HTMLInputElement>('input[name="website"]')
      expect(honeypot).not.toBeNull()
      expect(honeypot!.closest('[aria-hidden="true"]')).not.toBeNull()
      expect(honeypot).toHaveAttribute('tabindex', '-1')

      await fillValid(user, dialog)
      await user.click(within(dialog).getByRole('button', { name: 'Send enquiry' }))
      await within(dialog).findByRole('heading', { name: 'Thank you' })

      expect(payload.website).toBe('')
    })
  })
})
