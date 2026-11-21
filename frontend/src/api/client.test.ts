import { http, HttpResponse } from 'msw'
import { server } from '../test/server'
import { ApiError, apiRequest, userMessage } from './client'

async function failure(promise: Promise<unknown>): Promise<ApiError> {
  try {
    await promise
  } catch (error) {
    return error as ApiError
  }
  throw new Error('Expected the request to fail')
}

describe('apiRequest', () => {
  it('unwraps the data envelope', async () => {
    server.use(http.get('/api/v1/thing', () => HttpResponse.json({ data: { ok: true } })))

    await expect(apiRequest('/thing')).resolves.toEqual({ ok: true })
  })

  it('only ever calls our own origin under /api/v1', async () => {
    let seen = ''
    server.use(
      http.get('/api/v1/thing', ({ request }) => {
        seen = request.url
        return HttpResponse.json({ data: 1 })
      }),
    )

    await apiRequest('/thing')

    const url = new URL(seen)
    expect(url.origin).toBe(window.location.origin)
    expect(url.pathname.startsWith('/api/v1/')).toBe(true)
  })

  it('normalises a validation envelope, keeping field details and the request id', async () => {
    server.use(
      http.get('/api/v1/thing', () =>
        HttpResponse.json(
          { error: { code: 'validation_failed', message: 'Nope', details: { name: ['Required'] }, request_id: 'abc' } },
          { status: 422 },
        ),
      ),
    )

    const error = await failure(apiRequest('/thing'))

    expect(error).toBeInstanceOf(ApiError)
    expect(error).toMatchObject({ kind: 'validation', status: 422, code: 'validation_failed', requestId: 'abc' })
    expect(error.details).toEqual({ name: ['Required'] })
  })

  it.each([
    [404, 'not_found'],
    [429, 'rate_limited'],
    [502, 'unavailable'],
    [503, 'unavailable'],
    [504, 'unavailable'],
    [500, 'unknown'],
    [418, 'unknown'],
  ])('maps HTTP %i to kind %s', async (status, kind) => {
    server.use(http.get('/api/v1/thing', () => HttpResponse.json({}, { status })))

    expect((await failure(apiRequest('/thing'))).kind).toBe(kind)
  })

  it('reads Retry-After when present', async () => {
    server.use(http.get('/api/v1/thing', () => HttpResponse.json({}, { status: 429, headers: { 'Retry-After': '12' } })))

    expect((await failure(apiRequest('/thing'))).retryAfterSeconds).toBe(12)
  })

  it('treats a non-JSON gateway error page as unavailable rather than crashing', async () => {
    server.use(http.get('/api/v1/thing', () => new HttpResponse('<html>Bad Gateway</html>', { status: 502 })))

    const error = await failure(apiRequest('/thing'))

    expect(error.kind).toBe('unavailable')
  })

  it('reports network failures as their own kind', async () => {
    server.use(http.get('/api/v1/thing', () => HttpResponse.error()))

    expect((await failure(apiRequest('/thing'))).kind).toBe('network')
  })
})

describe('userMessage', () => {
  it('never exposes server or exception text', () => {
    const error = new ApiError({ kind: 'unavailable', status: 503, message: 'cURL error 28 at 10.0.0.5' })

    expect(userMessage(error)).not.toContain('cURL')
    expect(userMessage(error)).toContain('temporarily unavailable')
  })

  it('has a safe default for anything unknown', () => {
    expect(userMessage(new Error('boom'))).toBe('Something went wrong. Please try again.')
    expect(userMessage('x')).toBe('Something went wrong. Please try again.')
  })
})
