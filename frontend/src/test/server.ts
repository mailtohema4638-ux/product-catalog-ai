import { http, HttpResponse } from 'msw'
import { setupServer } from 'msw/node'
import { categories } from './fixtures'

/** Every page renders the category nav, so it is mocked by default; tests override anything else. */
export const server = setupServer(http.get('/api/v1/categories', () => HttpResponse.json({ data: categories })))

export const apiError = (status: number, code: string, message = 'Server message', extra: object = {}) =>
  HttpResponse.json({ error: { code, message, request_id: 'req-123', ...extra } }, { status })
